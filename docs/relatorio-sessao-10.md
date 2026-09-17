# Relatório — Sessão 10 (Bateria de ponta a ponta com Playwright)

> Escopo fechado, definido no início da sessão: dar ao painel administrativo a primeira rede
> de proteção automatizada, começando por testes de ponta a ponta contra a pilha real, provar
> que esses testes pegam defeito de verdade, e levar tudo para o CI. Este relatório registra a
> prova (defeito a defeito), as decisões tomadas sem consulta e as armadilhas encontradas no
> caminho.

## Por que ponta a ponta antes de teste de componente

O painel não tinha nenhum teste automatizado. Os defeitos encontrados nele até aqui foram
todos de integração:

- busca com `LIKE` sensível a maiúsculas — comportamento do PostgreSQL que o SQLite escondia
  (sessão 9);
- reserialização de HTML pelo editor de texto rico ao abrir e salvar uma página;
- colisão de CSS entre componentes;
- reuso da mesma instância de componente pelo vue-router ao ir de "criar usuário" para
  "editar usuário".

Nenhum desses quatro apareceria num teste de componente com a API simulada. A cobertura
começa onde os defeitos estão.

## Etapa 1 — Infraestrutura

### O terceiro banco

`lar_analia_franco_e2e`, no mesmo PostgreSQL do `docker-compose.yml`, com ambiente próprio em
`backend/.env.e2e` (commitado, só valores fictícios, com exceção explícita no `.gitignore` —
mesmo arranjo de `.env.testing`).

São três **bases**, um motor só. A regra de "nunca um segundo banco" continua valendo como
sempre valeu: ela é sobre o motor (nunca SQLite), não sobre o nome da base. O que motiva a
separação é outra coisa — dois dos três rodam `migrate:fresh`:

| Banco | Ambiente | Quem usa |
|---|---|---|
| `lar_analia_franco` | `backend/.env` | desenvolvimento |
| `lar_analia_franco_test` | `backend/.env.testing` | Pest **e** os scripts de concorrência |
| `lar_analia_franco_e2e` | `backend/.env.e2e` | bateria de ponta a ponta |

A bateria ter banco próprio (em vez de reaproveitar o de teste) é o que permite rodar Pest e
e2e ao mesmo tempo sem uma execução destruir a outra. Pest e os scripts de concorrência
continuam compartilhando `lar_analia_franco_test`, e continuam não podendo rodar juntos — está
registrado em `CLAUDE.md`.

### A guarda

`php artisan e2e:prepare` é o único caminho que recria o banco de e2e, e **recusa rodar** se o
ambiente não for `e2e` **ou** se o banco resolvido não for `lar_analia_franco_e2e`. Duas
checagens, não uma, pelo mesmo motivo de `backend/scripts/concorrencia/bootstrap.php`: a de
ambiente cobre o engano comum (esquecer `APP_ENV=e2e` e cair no banco de desenvolvimento); a
do nome do banco é quem impede o desastre de verdade, porque barraria mesmo se `.env.e2e` um
dia for editado para apontar para outro lugar.

Conferido na prática: rodado sem `APP_ENV=e2e`, o comando recusa e imprime o ambiente e o
banco que resolveu.

O comando também cria o banco quando ele ainda não existe (conectando-se ao `postgres` do
mesmo servidor). Isso evita o passo manual que o banco de teste exige em volumes anteriores ao
`init-test-db.sql` — o primeiro `npm run test:e2e` de quem clona o repositório funciona sem
preparo nenhum.

### A pilha sob teste

Tudo em modo de produção, nunca `dev`:

| Serviço | Porta | Como sobe |
|---|---|---|
| API (Laravel) | 8100 | `php artisan serve` com `APP_ENV=e2e` |
| Painel (Vue) | 5175 | `npm run build` + `vite preview` |
| Site (Nuxt) | 3100 | `npm run build` + `node .output/server/index.mjs` |

Portas distintas das de desenvolvimento (8000/5173/3000), para a bateria poder rodar com o
ambiente de desenvolvimento no ar na mesma máquina. Isso não é conforto: a porta 8001, que era
a primeira escolha, já estava ocupada nesta máquina por outro processo — colisão de porta é o
tipo de coisa que transforma "a bateria quebrou" em meia hora de investigação.

O passo de `build` fica **dentro** do comando do `webServer`, e não num script à parte, para
que `npx playwright test` sozinho também teste o artefato certo. Custa um build por execução
(a bateria inteira leva ~44 s, build incluído).

### Decisões tomadas sem consulta

- **Um worker só, sem paralelismo.** O backend sob teste é o servidor embutido do PHP e os
  testes compartilham um banco. Paralelizar trocaria tempo de relógio por falha intermitente,
  que é justamente o que uma bateria de e2e não pode ter. A bateria inteira leva 44 s.
- **Variáveis `VITE_*` passadas pelo ambiente do processo**, não por um `.env.e2e` no
  `frontend-admin`. O `loadEnv` do Vite dá precedência a `process.env` sobre o `.env` do
  projeto, então o build da bateria aponta para a porta certa sem um quarto arquivo de
  ambiente para manter em dia. Conferido no build de verdade.
- **Índices próprios do Redis** (3 e 4, contra 0 e 1 do desenvolvimento). Sem isso, o
  `cache:clear` que a bateria roda no começo derrubaria a sessão aberta no navegador de
  desenvolvimento e o cache de página pública dele.
- **Um `cache:clear` no preparo**, que zera o limitador de tentativas de login entre
  execuções. Sem ele, duas execuções dentro do mesmo minuto somariam tentativas no mesmo balde
  e a segunda começaria esbarrando no throttle — falha intermitente sem relação com o que se
  está testando.
- **`PlaceholderPdf` extraído** para `database/seeders/Support/`, compartilhado entre o
  `TransparencyDocumentsSeeder` e o `E2eSeeder`. Duas cópias divergiriam na primeira vez que
  uma delas mudasse.
- **`ContentPagesSeeder` e `TransparencyDocumentsSeeder` passam a aceitar o ambiente `e2e`**
  na guarda de ambiente (antes: só `local` e `testing`), para a bateria rodar contra volume
  realista de conteúdo em vez de três páginas de mentira. `DevSuperAdminSeeder` ficou de fora
  de propósito: o `E2eSeeder` cria os próprios super_admins, e ampliar aquela lista sugeriria
  um uso que não existe.

## Etapa 2 — Os testes

24 testes, todos verdes. Regras seguidas: nenhum `waitForTimeout`, só asserções que esperam
sozinhas, seletores por papel e texto visível (nenhum `data-testid` foi necessário), cada teste
criando e limpando os próprios dados.

**Autenticação e acesso** (9): senha certa, senha errada com mensagem genérica, conta
desativada com mensagem específica; menu e Início de `financeiro`, de `comunicacao` e de quem
acumula `financeiro` + `contraturno`; rota de outro papel aberta pela URL mostrando acesso
negado dentro do layout, com menu e URL preservados; `comunicacao` sem opção de criar nem de
excluir página.

**Usuários** (5): criação com dois papéis, link de definição de senha, a pessoa entrando por
outro navegador e vendo o menu dos dois papéis; desativação com a sessão da pessoa aberta,
levando ao login de forma limpa na ação seguinte; troca da própria senha mantendo a sessão
depois de recarregar; autodesativação barrada com a mensagem da API; criar e em seguida editar
outro usuário sem recarregar (a regressão do reuso de componente).

**Transparência** (3): upload de PDF, publicação, aparição no site público, despublicação e
sumiço; filtro por ano alcançando documento fora da primeira página; exclusão com confirmação.

**Páginas** (7): ida e volta do editor em duas páginas (botão `class="btn"` e link externo);
edição de parágrafo aparecendo no site já na requisição seguinte; sanitização por dois
caminhos; busca por título ignorando maiúsculas; aviso ao sair com alteração não salva.

### Duas decisões que mudaram a forma dos testes

**A ida e volta do editor precisa de conteúdo em forma canônica.** A primeira intenção era
testar diretamente uma página do `ContentPagesSeeder`. Não funciona: aquele conteúdo é escrito
à mão, com quebra de linha e indentação entre as tags, e nunca passou pelo editor — a primeira
gravação pelo painel normaliza tudo isso de uma vez. Essa diferença é real, porém trivial, e
afogaria o que o teste existe para pegar. A saída foi plantar duas páginas cujo conteúdo já
está na forma exata que o editor produz (copiado de `contraturno` e de `bazar/visite-a-loja`,
para os casos serem os reais), e exigir, além de "antes === depois", que `class="btn"`,
`target`, `rel` e `href` continuem lá — senão a igualdade passaria igual se o editor tivesse
apagado os dois lados. Como regerar essas constantes está em `e2e/README.md`.

**A sanitização precisa de dois testes, não um.** O pedido dizia "colar conteúdo com `<script>`
e link `javascript:`". Colar no editor é o caminho da pessoa e vale ser testado — mas o Tiptap
limpa a colagem antes de enviar, então esse teste sozinho não prova nada sobre o backend. Foi
acrescentado um segundo, que manda o mesmo HTML direto para a API, sem passar pelo editor (o
cenário de um `curl`, de um script, ou de uma versão antiga do painel). A Etapa 3 confirmou a
suspeita: removendo a sanitização do `SavePage`, **só o segundo teste fica vermelho**.

## Armadilha encontrada: `browser.newContext()` herda o `storageState` do teste

Custou a maior parte do tempo de depuração da sessão e merece registro.

Vários testes precisam de duas pessoas usando o sistema ao mesmo tempo (o super_admin cria a
conta; a pessoa define a senha e entra). O segundo navegador foi aberto com
`browser.newContext()`, esperando um contexto limpo.

Dentro de um teste, o `browser.newContext()` do `@playwright/test` **herda as opções declaradas
no `test.use()`, inclusive o `storageState`**. O "segundo navegador" nascia, portanto, com o
cookie de sessão do super_admin. Quando a segunda pessoa fazia login, o
`SessionGuard::updateSession` do Laravel migrava a sessão destruindo o id anterior — que era o
mesmo id — e derrubava a sessão do primeiro contexto.

O sintoma aparecia longe da causa: o teste seguinte, que nem tinha segundo contexto, caía na
tela de login. Por um bom tempo a suspeita foi de rehash de senha no login, de
`AuthenticateSession` e do cliente de API — todas descartadas medindo (o hash não mudava, o
cliente de API funcionava quando fazia o próprio login, e a sessão no Redis estava vazia em vez
de ausente). O que resolveu foi imprimir o id de sessão decifrado dos dois contextos lado a
lado: eram iguais.

A correção é `pageInCleanContext(browser)`, que sempre passa um `storageState` vazio explícito,
com o porquê escrito no lugar. Está documentado em `e2e/README.md`.

Duas outras, menores:

- **`getByRole('textbox')` não acha a área do Tiptap.** O motor de papéis do Playwright não
  atribui papel implícito de textbox a um `contenteditable` — só a campo de formulário ou a
  quem declara `role`. O localizador passou a ser `getByLabel('Conteúdo da página')`, que é o
  mesmo nome acessível que um leitor de tela anuncia.
- **"Nenhum `<script>` no site" não pode ser verificado no documento inteiro.** O HTML do Nuxt
  tem `<script>` legítimo próprio (importmap, entrada do bundle, payload do SSR). A asserção
  olha só o trecho vindo do CMS (`div.page-content`), mais uma sentinela que não pode aparecer
  em lugar nenhum do documento.

## Etapa 3 — Prova de que os testes pegam defeito

Três defeitos introduzidos localmente, um de cada vez, sem commit, e revertidos com
`git checkout` logo depois. Árvore limpa ao final (`git status --porcelain` vazio).

| Defeito introduzido | Teste que ficou vermelho | O que a falha mostrou |
|---|---|---|
| `AppSidebar.hasAccess()` deixa de consultar o valor do mapa de acesso | 3 testes: `financeiro › vê no menu apenas Transparência`, `comunicacao › vê no menu apenas Páginas`, `usuário com financeiro + contraturno › vê os dois blocos e a soma dos cards` | menu com 9 links onde devia ter 2 (e 4, no caso do acúmulo) |
| `SavePage` grava `$data->content` sem passar pelo `ContentSanitizer` | 1 teste: `script e link javascript: enviados direto à API também não chegam ao site público` | `<script>window.__e2eSanitizacaoFalhou = true</script>` gravado e servido íntegro |
| Middleware `active` (`EnsureUserIsActive`) removido das rotas autenticadas | 1 teste: `desativar a conta de quem está logado leva essa pessoa ao login, sem tela quebrada` | a pessoa desativada seguiu navegando: URL em `/admin/volunteer-applications` onde devia estar `/login` |

O segundo caso é o mais informativo. O teste de **colagem no editor** continuou **verde** com a
sanitização removida — o Tiptap limpa o `<script>` antes de enviar. Se a bateria tivesse só o
teste que o pedido descrevia, a remoção da sanitização do backend passaria despercebida. É por
isso que os dois existem, e é por isso que o segundo é o que protege a regra.

## Etapa 4 — CI

Job `e2e` no `.github/workflows/ci.yml`, com PostgreSQL 16 e Redis 7 como serviços (mesmas
versões principais do `docker-compose.yml`, pelo mesmo motivo de sempre). O job sobe a pilha
pelo `webServer` do Playwright e roda a bateria com um comando.

Nenhuma variável de banco é declarada no job, de propósito: toda a configuração vem de
`backend/.env.e2e`. O job `backend` duplica essas chaves e tem um comentário longo explicando
por quê (etapas que rodam fora do fluxo de teste); aqui não há esse motivo, e duplicar reabriria
a chance de as duas fontes divergirem em silêncio.

Em falha, `playwright-report` e `test-results` sobem como artefato — trace, captura e vídeo de
cada teste que quebrou, com 14 dias de retenção.

Repetição no CI é no máximo **uma**. Teste que falhou e passou na repetição sai marcado como
**instável** num bloco próprio no fim da saída do job e numa tabela no resumo do GitHub
Actions, com o motivo da primeira falha (`e2e/reporters/flaky-reporter.ts`). Repetição não
conserta teste instável, só esconde — o bloco fica lá até alguém resolver.

### Tempo do job

Primeira execução no CI (run `35192234148`, todos os quatro jobs verdes de primeira):

| Job | Tempo |
|---|---|
| `e2e (Playwright, pilha completa)` | **2 min 59 s** |
| `backend (Pint, Larastan, Pest, composer audit)` | 1 min 1 s |
| `frontend-site (build, npm audit)` | 38 s |
| `frontend-admin (lint, build, npm audit)` | 20 s |
| **run completo** (jobs em paralelo) | **3 min 3 s** |

Dos 2 min 59 s do job de e2e, os **testes em si são 1 min 24 s** (24 testes, 1 worker) — o
resto é preparo: instalar dependências de quatro projetos, baixar o Firefox (com cache) e
construir os dois frontends. O job de e2e passa a ser o caminho crítico do CI: o run todo
saiu de ~1 min para ~3 min. É um custo aceitável pelo que ele cobre, e o primeiro lugar a
otimizar (cache do `composer`/`npm`) se um dia incomodar.

## Etapa 5 — Registro

`docs/decisoes/0011-e2e-com-playwright-contra-a-pilha-real.md` registra a decisão de
arquitetura: por que ponta a ponta antes de teste de componente, por que a pilha em modo de
produção, por que um terceiro banco (e por que isso não reabre a decisão 0002), por que só
Firefox e por que um worker só — com as alternativas descartadas e o motivo de cada uma.

`CLAUDE.md` passou a registrar:

- os três bancos, com quem usa cada um e qual arquivo de ambiente configura cada um;
- que Pest, os scripts de concorrência e a bateria de e2e não podem rodar ao mesmo tempo onde
  compartilham banco;
- que **toda funcionalidade nova do painel ganha um teste de ponta a ponta do fluxo
  principal** — um fluxo por funcionalidade, não cobertura exaustiva.

O `README.md` da raiz ganhou a seção de ponta a ponta e a nota sobre o terceiro banco;
`e2e/README.md` tem o detalhe: como rodar, as regras de escrita dos testes, a armadilha do
`storageState` herdado, como regerar a forma canônica do editor e o que fazer com o artefato de
uma falha no CI.

## Verificação final

- bateria verde **três vezes seguidas**, sem nenhuma intermitência: 24/24 em 43,3 s, 43,8 s e
  43,7 s;
- Pest 336/336, Pint e Larastan (nível alto) verdes;
- `frontend-admin`: `npm run lint` e `npm run build` verdes;
- `frontend-site`: `npm run build` (SSR) e `npm run generate` (SSG) verdes;
- banco de desenvolvimento intacto: 1 usuário, 27 páginas, 12 documentos — e zero contas
  `@e2e.local`, ou seja, nada da bateria vazou para lá;
- CI: os quatro jobs verdes de primeira no run `35192234148` — `e2e` incluído, com 24/24.

## O que precisa de conferência humana

Nada de interface mudou nesta sessão — a entrega é infraestrutura de teste, documentação e um
job de CI. As duas alterações em código de produção foram temporárias, para a prova da Etapa 3,
e foram revertidas.

A conferência no navegador foi feita pela própria bateria: 24 testes dirigiram as telas reais
num Firefox real, três vezes seguidas sem intermitência. O que a bateria **não** verifica, e
continua dependendo de olho humano: aparência (alinhamento, contraste, tipografia),
comportamento em tela estreita e acessibilidade além do nome acessível dos elementos que os
testes localizam.

## O que ficou de fora

- **Só Firefox.** Uma bateria pequena rodando bem em um navegador de verdade vale mais que a
  mesma bateria repetida em três. Acrescentar Chromium é uma linha em `projects` se um dia
  fizer falta.
- **Os cinco formulários recebidos** (listagem, detalhe, mudança de status) não têm teste de
  ponta a ponta ainda. A tela é uma só, genérica, e o pedido desta sessão listou outros fluxos;
  entram quando a regra de "toda funcionalidade nova ganha um e2e" alcançar a próxima mexida
  ali.
- **Acessibilidade e responsividade** não são verificadas automaticamente. Seriam outra bateria,
  com outras ferramentas.
