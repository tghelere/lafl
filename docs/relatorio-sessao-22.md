# Sessão 22 — O diagnóstico dos dois sintomas, e o defeito que ele revelou

Dois sintomas apareceram juntos no ambiente local, depois de tudo funcionar:

- (a) o login do painel com `dev@laranaliafranco.local` falhava;
- (b) a maioria das páginas do site Nuxt respondia 404.

| Etapa | Commit |
|---|---|
| 1 — causa raiz e como diagnosticá-la | `7372ba6` |
| 2 — o setup do zero, verificado | `520d8c1` |
| 3 — 404 x 503 | `fd9eb6e` |
| 4 — página de erro própria | `c50e067` |

## Causa raiz

**O motor do Docker Desktop estava parado.** Postgres e Redis foram junto; nada no código
tinha mudado, e por isso rebuildar o front não resolvia.

A evidência, colhida antes de mexer em qualquer coisa:

```
$ docker compose ps
The command 'docker' could not be found in this WSL 2 distro.

$ docker info
failed to connect to the docker API at npipe:////./pipe/dockerDesktopLinuxEngine:
  The system cannot find the file specified.

$ tail backend/storage/logs/laravel.log
local.ERROR: Connection refused {"exception":"[object] (RedisException…
  #10 Illuminate\Cache\RedisStore->get('public-page:edu...')
  #15 App\Actions\Content\ResolvePublicPageBySlug.php(30)
  #16 App\Http\Controllers\Api\V1\Public\PageController.php(37)
```

Vale notar o que a mensagem do Docker esconde: "could not be found in this WSL 2 distro"
parece problema de **integração WSL** (o toggle que o README já documenta), e não é — é o motor
parado. Com o motor de pé, o mesmo binário funciona sem tocar em toggle nenhum.

Subir `docker compose up -d` resolveu os dois sintomas. Nenhum dado foi perdido: os containers
estavam parados, não removidos, e o volume do Postgres estava intacto.

### As hipóteses da tarefa, uma a uma

Todas foram conferidas contra o banco e o código, e **todas são falsas**. Ficam registradas
porque descartá-las é o que sobra de útil na próxima vez:

| Hipótese | O que a conferência mostrou |
|---|---|
| Banco recriado sem seed | 26 páginas presentes, 23 publicadas — inclusive `educacao-infantil` |
| Seeders de usuários/páginas alterados | `git log` sem alteração; seed num banco descartável deu estado idêntico |
| URL da API mudou com o trabalho de deploy | `frontend-site/.env` idêntico ao `.env.example` |
| Validação `email:dns` rejeitando `.local` | `LoginRequest` usa `['required', 'email']`, sem `dns` |
| Usuário dev desativado ou sem senha | 1 linha em `users`, `deactivated_at` nulo, hash `$2y$12$…` |
| Throttle de login travado | O throttle vive no Redis, que estava fora do ar |
| `SANCTUM_STATEFUL_DOMAINS` / `SESSION_DOMAIN` alterados | Fluxo completo csrf-cookie → login → 200 com `super_admin` |

Nada disso envolveu `.env` local, então `.env.example` e o passo a passo de setup do README não
precisaram mudar — o que mudou no README foi a seção de diagnóstico (etapa 1).

## O defeito que o diagnóstico revelou

Uma hora foi gasta procurando conteúdo apagado. Não por falta de método: **porque o site
afirmava que o conteúdo não existia.** As sete páginas que leem o CMS faziam

```ts
if (error.value) {
  throw createError({ statusCode: 404, statusMessage: 'Página não encontrada', fatal: true })
}
```

e `error.value` é verdadeiro para qualquer falha — 404, 500, 429, timeout, conexão recusada.

Em desenvolvimento isso custa tempo. Em produção custa o índice: 404 diz ao Google "este
conteúdo não existe" e, repetido, tira o endereço da busca; 503 diz "não consigo responder
agora" e ele mantém o endereço e volta depois. Num site que existe em boa parte para que a
prestação de contas seja encontrada, o defeito é grande — e o episódio do Docker é exatamente
a forma que ele teria no ar.

Isso virou a etapa 3 e a ADR
`docs/decisoes/0019-falha-da-api-responde-503-nao-404.md`.

## Etapa 1 — a causa raiz e como diagnosticá-la (`7372ba6`)

Entrada nova no Troubleshooting do README, com a ordem de conferência e a evidência que separa
as duas coisas: **a API responde 500, não 404**. Página que de fato não existe é que responde
404.

## Etapa 2 — o setup do zero, verificado (`520d8c1`)

Conferido de verdade, num banco descartável (`lar_analia_franco_setupcheck`, criado e
derrubado na sessão) para não encostar no banco de desenvolvimento: `migrate --seed` produz o
dev ativo e 23 páginas publicadas — estado idêntico ao do banco de desenvolvimento, o que
descartou de vez a hipótese do seeder.

O que passou a existir é a verificação automatizada disso
(`backend/tests/Feature/Seeders/SetupDoZeroTest.php`): o login acontece pelo **endpoint real**
e o conteúdo é lido pelo **endpoint real**, porque o que quebraria o setup — usuário sem papel,
conta desativada, senha que não confere, página semeada como rascunho — só aparece
atravessando a pilha inteira. O rascunho é conferido pelo contrapositivo: um seeder que
publicasse tudo passaria no teste das publicadas e estragaria em silêncio o estado de rascunho.

Um defeito real foi corrigido de passagem: `DevSuperAdminSeeder` usava `updateOrCreate`, que só
escreve as colunas que recebe. Um dev desativado pelo CRUD do painel continuava desativado
depois de reseedar — o login era recusado logo depois de rodar o seeder que existe para
devolver o acesso. `migrate:fresh --seed` nunca caía nesse caso (recria a tabela); `db:seed`
sozinho, sim.

Cruzamento adicional, feito à mão: os **28 alvos de navegação** de `navigation.ts` e
`siteNav.ts` resolvem, num banco recém-semeado, para página publicada do CMS ou para `.vue`
próprio. Nenhum aponta para rascunho.

## Etapa 3 — 404 x 503 (`fd9eb6e`)

A regra e o desenho estão na ADR 0019. Em resumo: só o 404 vindo da API vira 404 do site;
qualquer outra falha vira 503 com `Cache-Control: no-store`. Três peças —
`app/utils/apiPageError.ts` (decide), teto de 4s sem retentativa em `usePublicPage` (a chamada
segura o SSR) e `server/plugins/sem-cache-em-erro.ts` (o `no-store`).

Duas coisas foram **medidas**, não supostas:

- numa resposta de erro do Nuxt, dos quatro ganchos do Nitro só o `error` roda —
  `beforeResponse`, `render:response` e `afterResponse` não chegam a ser chamados. Foi um probe
  registrando os quatro que mostrou isso; o desenho do plugin saiu daí, não de um palpite;
- `statusMessage` acentuada sai mutilada na linha de status do HTTP (latin-1):
  `Serviço temporariamente indisponível` chegava como `Servio temporariamente indisponvel`. Por
  isso `statusMessage` é ASCII curto — quem escreve o que a pessoa lê é `app/error.vue`, em
  português.

A página de documentos de transparência entrou na mesma regra: ela *é* o acervo, e a falha era
engolida num `?? []` que servia 200 dizendo "Nenhum documento encontrado".

## Etapa 4 — página de erro própria (`c50e067`)

`frontend-site/app/error.vue`, dentro do layout do site, em pt-BR. No 404, links para as
seções principais lidos de `app/config/navigation.ts` (a mesma fonte do cabeçalho e do rodapé).
No 503, **sem** esses links: o que falhou foi a API, e toda página de seção lê a API — seriam
becos sem saída.

Cobertura de ponta a ponta do 404 em `e2e/tests/layout/pagina-de-erro-do-site.spec.ts`, quatro
casos, incluindo rascunho do CMS respondendo 404 e não 503 — a metade da regra que
silenciosamente se inverteria primeiro.

### Uma correção de rumo que vale registrar

Chegou a entrar um gancho `router.afterEach(() => clearError())` na página de erro, com um
comentário afirmando que sem ele a pessoa ficava presa ao clicar num link do cabeçalho.
Aquilo era **erro meu de leitura**: o que eu tinha visto era a minha própria instância de teste
na porta 3100, cujo `Origin` o CORS do backend não autoriza — o clique funcionava, e a página
de destino falhava depois, por CORS. Liberando a porta temporariamente, o comportamento
apareceu limpo; removendo o gancho e reconstruindo, continuou limpo. O Nuxt já sai do estado de
erro sozinho na troca de rota. O gancho saiu, em vez de ficar como código sem função com um
comentário que o justificava errado.

## Decisões tomadas sem consulta

1. **A página de documentos de transparência entrou na regra do 503.** A tarefa falava das
   páginas que viravam 404; aquela nunca virou — engolia a falha e servia 200 com a lista
   vazia. Entrou porque é o mesmo defeito com fachada pior: a instituição anunciando que não
   presta contas, no endereço que existe para provar o contrário.
2. **A home ficou de fora.** O conteúdo dela é fixo; só as idades vêm da API e já degradam para
   nada. Derrubar a home inteira por causa de um número é desproporcional — mas servi-la em 200
   com a frase incompleta também não está certo. Anotado no roadmap, sem decisão.
3. **`statusMessage` em inglês**, contra a convenção de pt-BR para o que a pessoa lê, pelo
   motivo latin-1 acima. Ninguém lê essa linha.
4. **Um quarto banco, descartável, para conferir o setup do zero**, em vez de rodar
   `migrate:fresh` no banco de desenvolvimento. Criado e derrubado dentro da sessão.

## Verificação

| O quê | Resultado |
|---|---|
| Pint | passou |
| PHPStan (Larastan) | 0 erros |
| Pest | 443/443 |
| `nuxt build` + `nuxt generate` (site) | ok |
| `npm run build` + `npm run lint` (painel) | ok |
| Playwright (bateria inteira) | 95/95 |

Conferido no navegador, e não só por status:

- **login do painel** com `dev@laranaliafranco.local` / `password` — entra, e o painel carrega
  com o menu de `super_admin` (era o sintoma (a));
- **404 do site** em 1280px e em 375px — cabeçalho, rodapé, tipografia e botão da marca certos,
  sem estouro horizontal; botão de voltar e links de seção navegam;
- **503**, com o Nitro de produção apontado para uma porta fechada — mesma moldura, sem os
  links de seção, `Cache-Control: no-store`.

Contra o **build de produção**, com a API no ar e fora do ar:

| Rota | API no ar | API fora do ar |
|---|---|---|
| `/quem-somos` | 200 | 503 `no-store` |
| `/transparencia/documentos` | 200 | 503 `no-store` |
| `/pagina-que-nao-existe` | 404 `no-cache` | 503 `no-store` |
| `/quem-somos/missao-visao-valores` (rascunho) | 404 `no-cache` | — |

## Como reproduzir o setup do zero

Nada mudou no passo a passo — ele já estava certo. Da raiz do repositório:

```bash
docker compose up -d                    # exige o motor do Docker Desktop NO AR
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate:fresh --seed

cd frontend-admin && npm install && npm run dev    # http://localhost:5173
cd frontend-site  && npm install && npm run dev    # http://localhost:3000
```

Sem Docker para PHP/Node (que é como esta máquina roda), só a infraestrutura sobe em container:

```bash
docker compose up -d postgres redis mailpit
cd backend && php artisan migrate:fresh --seed && php artisan serve
```

O que isso entrega, agora com teste trancando: o dev `dev@laranaliafranco.local` / `password`
com papel `super_admin` e **ativo**, e as 23 páginas publicadas respondendo no site (mais 3 em
rascunho, de propósito). Se o login falhar ou as páginas não abrirem depois disso, o
Troubleshooting do README tem a ordem de conferência — e a primeira pergunta é se o motor do
Docker está no ar.

## Pendente

- **A home e os números institucionais** fora da regra de 503 (decisão 2 acima) — anotado em
  `docs/roadmap.md`, sem decisão.
- **O 503 não tem teste de ponta a ponta.** O `webServer` da bateria mantém a API de pé, e a
  chamada que precisaria falhar é do SSR, fora do alcance do `page.route()`. Foi conferido à
  mão contra o build de produção, e o arquivo de teste diz isso em vez de fingir cobertura.
  Cobrir de verdade exigiria um `webServer` extra apontado para uma porta fechada — factível,
  não feito aqui.
- **Nada precisa de conferência humana no navegador** para esta sessão: as duas telas e o login
  foram vistos. Vale, ainda assim, um olhar humano no texto das duas mensagens de erro — são
  voltadas ao visitante e a instituição pode preferir outras palavras.
