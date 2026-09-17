# Relatório — Sessão 16

Tarefa executada: `docs/tarefas/07-caminho-ate-a-producao.md` — o lado da aplicação do caminho
até a produção. O problema de partida: um deploy naquele estado subiria **sem conteúdo**
(`ContentPagesSeeder` só roda em `local`/`testing`/`e2e`) e **sem ninguém capaz de entrar**
(`DevSuperAdminSeeder`, mesma restrição, e nenhum outro caminho para a primeira conta).

## O que foi feito

### Etapa 1 — Conteúdo inicial (commit `37f41d2`)

`php artisan conteudo:importar-inicial` cria **apenas as páginas que ainda não existem**, em
qualquer ambiente. O texto saiu do seeder e passou a morar em
`App\Support\Content\InitialPages` — fonte única lida por dois caminhos com políticas
opostas, e essa diferença é o ponto:

- o `ContentPagesSeeder` continua usando `updateOrCreate`: em banco de desenvolvimento o que
  vale é o texto do repositório;
- o comando nunca toca em página existente: fora de desenvolvimento o texto é da instituição,
  editado pelo painel, e um `updateOrCreate` ali apagaria essa edição a cada deploy.

Duas decisões que a implementação obrigou a tomar:

**Página na lixeira conta como existente e não é recriada.** `pages.slug` é `UNIQUE` sem
filtro, então recriar o slug estouraria o índice — e "ressuscitar" o que alguém excluiu pelo
painel seria pior que sobrescrever. Elas aparecem em categoria própria no resumo.

**O cache negativo é esquecido por slug criado.** `ResolvePublicPageBySlug` cacheia por 10
minutos inclusive o `null`. Sem `Cache::forget`, uma página recém-importada responderia 404 no
site por até 10 minutos depois do deploy — o mesmo sintoma da armadilha já registrada no
`CLAUDE.md`, por outra causa.

Os dois testes que liam o conteúdo do seeder por reflexão (`ContentSanitizerTest`,
`ContentMarkerTest`) passaram a ler a classe nova direto, sem `ReflectionMethod`.

### Etapa 2 — Primeira conta de super administrador (commit `ce96519`)

`php artisan usuarios:criar-super-admin` pergunta nome e e-mail, cria a conta e imprime o link
de definição de senha (reaproveitando `GeneratePasswordLink`). **Senha não existe como entrada
em lugar nenhum do comando** — nem argumento, nem opção, nem pergunta: a conta nasce com senha
aleatória inutilizável e o acesso vem pelo link, então nada sensível passa por `ps`, histórico
de shell ou log de deploy.

Recusa rodar quando já existe super administrador ativo, com `--forcar` para a perda de
acesso. Auditoria: `created` e `roles_updated` vêm do `CreateUser`, `password_link_generated`
do `GeneratePasswordLink`, e um evento `super_admin_bootstrapped` registra o que aqueles não
têm como registrar — a conta nasceu pelo console, sem causador autenticado. Há teste provando
que **o token e a URL não aparecem em nenhum registro de auditoria** (regra 8 do `CLAUDE.md`).

### Etapa 3 — Worker e agendador (commit `dd34cb7`)

Serviços `queue` (`queue:work`) e `scheduler` (`schedule:work`) no `docker-compose.yml`, na
mesma imagem e no mesmo volume do `app`. Antes disso, em desenvolvimento, nada disparado por
fila ou agendamento rodava: a notificação por e-mail dos formulários ficava parada no Redis e
os dois expurgos por retenção nunca eram executados — defeito que só apareceria em produção.

**Conferido com a pilha no ar**, não só por leitura: criei um `ContactMessage` com
`expires_at` no passado, rodei `schedule:test` do `PurgeExpiredFormSubmissions`, e o container
`queue` executou o job (`... 25.69ms DONE` no log) com a linha sumindo do banco.

### Etapa 4 — Ambiente `staging` (commit `de8cace`)

**Site inteiro não indexável em homologação.** `NUXT_PUBLIC_ENVIRONMENT=staging` faz toda
resposta sair com `X-Robots-Tag: noindex, nofollow` e o `robots.txt` bloquear tudo.
Homologação fica num domínio público com o conteúdo real da instituição; indexada, compete com
o site de verdade na busca orgânica, de que a instituição depende.

Isto foi a parte que mais exigiu medição, e três tentativas caíram:

| Mecanismo | Alcança | Não alcança |
|---|---|---|
| `server/middleware/` | SSR, rota de servidor, **404** | rota prerenderizada, arquivo estático de `public/` |
| plugin Nitro, gancho `beforeResponse` | SSR, prerenderizada, **estático** | resposta de erro (404) |
| `routeRules` com `headers` | tudo | é resolvido em tempo de **build** |

O `routeRules` seria o mais simples e é justamente o errado: um pacote gerado sem a variável e
servido em homologação ficaria indexável — o engano exato contra o qual isto existe. Ficaram
o plugin e o middleware, cujos buracos são complementares; juntos não sobra caminho de
resposta descoberto. `/robots.txt` saiu de `nitro.prerender.routes` pelo mesmo raciocínio:
gravado no build, o mesmo pacote diria "Allow: /" nos dois ambientes.

O teste (`e2e/tests/homologacao/noindex.spec.ts`, 20 casos) sobe uma **segunda instância do
mesmo `.output`** com a variável ligada e compara com a instância normal, caminho de resposta
por caminho de resposta. Se o bloqueio estivesse gravado no pacote, as duas responderiam igual
e metade dos casos falharia.

**Mailer de homologação.** `MAIL_ALWAYS_TO` reendereça todo e-mail para um endereço único e
descarta cc e bcc. Em staging os `FORM_RECIPIENT_*` são os endereços **reais** da instituição,
de propósito — é assim que se testa a configuração que vai para produção —, e sem isso um
teste de formulário viraria e-mail na caixa de alguém.

**Seeders de desenvolvimento.** As guardas já existiam e estavam corretas; o que faltava era
teste. `DevelopmentSeedersEnvironmentTest` trava os três em `staging` e `production`, com o
contrapositivo em `testing` para o teste não passar por um `environment()` sempre falso.

**`backend/.env.example`** ganhou uma seção documentando o que muda em staging e production,
sem nenhum valor real.

### Etapa 5 — Pacote de deploy (commits `b9a5916`, `a255a2b`)

`scripts/deploy/empacotar.sh` monta, a partir de `git archive HEAD`, um pacote com três
diretórios — `backend/` (composer `--no-dev`, podado), `site/` (build do Nuxt), `painel/`
(build do Vite) — mais um arquivo `RELEASE` com o commit de origem. Motivo em
`docs/decisoes/0014-pacote-de-deploy-minimo.md`: **o servidor é da instituição**, e o que é
propriedade da Softhing — código-fonte dos frontends, documentação, testes, decisões — não
precisa estar lá para o site funcionar.

Três coisas que a implementação obrigou a decidir:

**A poda vem antes de `composer dump-autoload`.** O classmap otimizado é gerado a partir da
árvore que existia na hora; podar depois dele deixa entradas apontando para arquivo que não
existe mais, e o efeito é erro fatal **só em produção**.

**A varredura de `stubs/`, `pint.json` e afins não entra em `vendor/`.** O Laravel carrega os
`stubs/` do próprio framework nos comandos `make:*` e os polyfills do Symfony resolvem classe
a partir de `Resources/stubs/`. A regra da ADR é sobre o que é nosso, não sobre quebrar
dependência para economizar bytes. O que é varrido do pacote inteiro é o que não tem execução
possível: `.md`, `.env*`, `.vue`, `.ts`, `.map`.

**O pacote precisa levar o esqueleto de `storage/`** (`a255a2b`). `git archive` não exporta
diretório vazio e `backend/storage/logs/` não tem arquivo versionado dentro — o pacote saía
sem ele. Só não quebrava porque o Monolog cria o diretório na primeira escrita, o que depende
de o usuário do PHP-FPM ter permissão no pai. **Encontrado na simulação do primeiro deploy**,
procurando o log do e-mail reendereçado; não teria aparecido em leitura de código.

A verificação é a parte que dá sentido ao script: ele varre o pacote pronto atrás de cada item
proibido e **sai com erro sem gerar nada** se achar algum, além de conferir a lista do que
precisa estar presente. Conferi que a verificação não é decorativa plantando um `docs/`, um
`.md`, um `.env`, um `.vue`, um `phpunit.xml` e um `tests/` numa cópia do pacote: as
expressões acusaram os seis.

### Etapa 6 — Documento de deploy (commit `a1377a2`)

`docs/deploy.md`: requisitos do servidor nas versões do CI e do compose, os dois ambientes no
mesmo VPS com base e processos separados, os cinco processos permanentes, a checklist de
variáveis **com o que dá errado quando cada uma está errada**, a ordem do primeiro deploy, a
regra de CDN/cache de HTML que estava no roadmap, e backup. `docs/arquitetura.md` passou a
apontar para ele.

## Verificação

| O quê | Resultado |
|---|---|
| Pest | 397 passando (era 375 no início da sessão) |
| Pint | limpo |
| Larastan | 0 erros |
| `npm run build` (site e painel), `npm run lint` (painel), `npm run generate` | tudo verde |
| Playwright | 63 passando (43 antes; 20 novos) |
| `empacotar.sh` | 7.673 arquivos, 72M em diretório, 21M no tarball, 8 verificações de proibidos e 18 de exigidos passando |

### Simulação do primeiro deploy

Feita **a partir do pacote podado**, não do repositório — é o que prova que o que vai para o
servidor de fato roda. Base temporária `lar_analia_franco_deploy_sim`, criada e removida ao
final, índices 7 e 8 do Redis (limpos ao final), `APP_ENV=staging`, `.env` criado à mão dentro
do pacote (que não traz nenhum, como a regra manda).

1. `migrate --force` — 16 migrations;
2. `RoleSeeder`;
3. `conteudo:importar-inicial` — 26 criadas; rodado de novo: 0 criadas, 26 já existentes;
4. `usuarios:criar-super-admin` — conta criada e link impresso; repetido, recusou com a
   mensagem certa;
5. `config:cache`, `route:cache`, `view:cache`;
6. API, painel e site servidos do pacote (portas 8101, 5176, 3102);
7. **no navegador**: abri o link de definição de senha, defini a senha, entrei no painel como
   `super_admin` e a tela de Páginas listou as 26 páginas importadas;
8. `/quem-somos/nossa-historia` abriu no site com o conteúdo do CMS, e toda resposta saiu com
   `X-Robots-Tag: noindex, nofollow` (era `staging`), com `robots.txt` em `Disallow: /`;
9. formulário de contato enviado: destinatário configurado `atendimento@example.invalid`,
   **entregue a `homologacao@example.invalid`** — `MAIL_ALWAYS_TO` funcionando;
10. `TransparencyDocument::count()` = 0, confirmando que o seeder de PDFs em branco não rodou.

Tudo removido ao final: banco, índices de Redis, `.pacotes/`, capturas.

## Decisões tomadas sem consulta

1. **O conteúdo inicial mora em `app/`, não junto do seeder.** `Database\Seeders\` está no
   autoload normal e sobreviveria ao pacote, mas um comando de produção dependendo de
   `Database\Seeders\*` fica frágil: basta alguém podar `database/seeders` do pacote um dia.
2. **Layout do pacote: `backend/`, `site/`, `painel/`**, em vez de repetir
   `frontend-site/.output` e `frontend-admin/dist`. Um diretório por processo do servidor é o
   que faz sentido para quem olha uma release; a 07b consome o que está documentado.
3. **`storage/framework/testing` sai do pacote** — só existe para a suíte Pest.
4. **Source maps são removidos.** Não têm `sourcesContent` (não vazam código), mas nomeiam os
   arquivos de origem, não são executados por ninguém e o pacote existe justamente para não
   levar estrutura de código ao servidor.
5. **`--permitir-arvore-suja` existe**, mas avisa que o pacote sai de `HEAD` e não do
   diretório de trabalho. Sem ela eu não conseguiria conferir o script durante a própria
   sessão.
6. **Os quatro arquivos de tarefa das outras sessões (06, 07b, 08, 09) ficaram sem commit.**
   Chegaram sem versionar no início da sessão e, pela regra de `docs/tarefas/README.md`, cada
   um entra no primeiro commit da sessão que o executar. Entraram por engano num commit meu e
   eu os tirei de lá antes de seguir — é a única coisa que `git status` ainda mostra.

## O que ficou de fora

- **Provisionamento do servidor, Nginx, systemd, certificados, deploy automático**: é a tarefa
  07b, explicitamente.
- **`npm run generate` não emite mais `robots.txt`**, consequência de tirá-lo do prerender.
  O site já não era hospedável como estático (formulários, `/transparencia/documentos`,
  redirect de slug antigo exigem o Nitro), então não há perda prática hoje; registrado no
  roadmap para o caso de isso mudar.
- **O painel continua amarrado ao ambiente em tempo de build.** O Vite grava `VITE_API_URL`,
  `VITE_SITE_URL` e `VITE_SESSION_IDLE_TIMEOUT_MINUTES` dentro do bundle. O site não tem esse
  problema (lê `NUXT_PUBLIC_*` em execução). Em consequência, "promover para produção o mesmo
  pacote validado em homologação", previsto na 07b, vale para o backend e para o site mas
  **não para o painel**, que precisa ser rebuildado. A alternativa (painel lendo um
  `config.json` servido ao lado do `index.html`) é decisão da 07b. Registrado na ADR 0014 e no
  roadmap.
- **`MAIL_ALWAYS_TO` não tem cobertura de e2e**, só Pest — a bateria não envia e-mail.

## O que precisa de conferência humana

- **O texto de `docs/deploy.md`**, em especial a tabela de variáveis e a ordem do primeiro
  deploy: ele é o roteiro que a 07b vai automatizar, e um erro ali vira um erro no servidor.
- **A decisão da ADR 0014 sobre o que não vai para o servidor da instituição** é de negócio,
  não técnica. Registrei o raciocínio; a palavra final é do Thyago.
- **Nada de interface mudou nesta sessão** — o que conferi no navegador foi o fluxo de deploy
  (link de senha → login → painel → site), não aparência. As duas capturas que tirei foram da
  simulação e não ficaram no repositório.
