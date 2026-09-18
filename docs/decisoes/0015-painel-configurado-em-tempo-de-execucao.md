# 0015 — O painel lê a configuração em tempo de execução

## Contexto

A ADR 0014 fechou *o que* vai para o servidor e deixou explícito um problema em aberto, para
ser decidido na tarefa 07b:

> O Vite grava o valor de `VITE_API_URL`, `VITE_SITE_URL` e
> `VITE_SESSION_IDLE_TIMEOUT_MINUTES` **dentro** do bundle, em tempo de build. (…) "promover
> para produção o mesmo pacote já validado em homologação" vale para o backend e para o site,
> mas **o painel precisa ser rebuildado com as variáveis de produção**. Ou isso, ou o painel
> passa a ler a configuração em tempo de execução — decisão para a 07b.

O desenho da 07b não deixa a escolha em aberto: produção publica **o mesmo pacote já validado
em homologação**, por tag. Um pacote em que o painel é específico do ambiente não é
promovível — o que for testado em homologação não é o que vai para o ar.

E o modo de falhar é ruim. Um painel de produção construído com `VITE_API_URL` de homologação
**abre normalmente**: a tela pinta, o login pede usuário e senha. O que ele faz é autenticar
contra o banco de homologação e editar o conteúdo de homologação, com a barra do navegador
mostrando o endereço de produção. Ninguém vê o erro; alguém percebe dias depois que a edição
"não aparece no site". O contrário — painel de homologação apontando para a API de produção —
é pior: edição real feita a partir de um ambiente de teste.

## Decisão

**O painel lê a configuração de `window.__LAF_CONFIG__`, definido por `/config.js`, servido
fora do bundle e reescrito no servidor a cada publicação.** As `VITE_*` continuam existindo
como origem secundária, usada quando o valor de execução está vazio.

| Peça | Papel |
|---|---|
| `frontend-admin/public/config.js` | valores vazios; entra no `dist/` e é o arquivo que o servidor sobrescreve |
| `frontend-admin/index.html` | `<script src="/config.js">` no `<head>`, antes do módulo |
| `frontend-admin/src/config.ts` | fonte única: valor de execução, senão o do build, senão o padrão |
| `shared/painel-config.js` (servidor) | o arquivo de verdade do ambiente, gerado por `infra/criar-ambiente.sh` |
| `infra/publicar.sh` | aponta `painel/config.js` da release para o `shared/` do ambiente |

Três detalhes que a implementação obrigou a decidir:

**O `<script>` fica no `<head>`, clássico, não `type="module"`.** No `<body>` também
funcionaria — módulo é adiado, clássico não —, mas passaria a depender dessa regra de ordem
para uma coisa cujo erro é silencioso: sem `__LAF_CONFIG__`, o `baseURL` do axios cai no valor
do build e o painel fala com a API errada, sem nenhum sinal. O Vite move o
`<script type="module">` para o `<head>` no build; o nosso precisa estar antes dele.

**Valor vazio significa "usa o do build".** É o que mantém `npm run dev` e a bateria de ponta
a ponta funcionando sem ninguém reescrever arquivo nenhum: ali não há servidor de deploy, e as
`VITE_*` do `.env` continuam valendo como sempre valeram.

**`/config.js` e `/index.html` saem com `Cache-Control: no-store`** (ver
`infra/modelos/nginx-painel.conf`). Os dois não têm hash no nome e mudam a cada publicação;
cacheados, o navegador continuaria carregando a configuração — ou o índice — do ambiente
anterior, que é exatamente o defeito que esta ADR existe para eliminar.

## Consequências

**O pacote passa a ser promovível inteiro.** Backend, site e painel: o artefato validado em
homologação é byte a byte o que vai para produção, e o que muda é o `.env` e o `config.js`, os
dois criados no servidor e que nunca saem de lá. Isso torna a tag `v*` da 07b uma promoção de
verdade, e não um rebuild com outro nome.

**Uma requisição a mais no carregamento do painel**, de um arquivo de algumas dezenas de
bytes, sem cache. Irrelevante para uma ferramenta atrás de login.

**`config.js` errado quebra o painel inteiro**, e não uma tela só — é o preço de ter uma fonte
única. Em troca, quebra de forma visível e no lugar certo, em vez de funcionar apontando para
o banco errado.

**O site (Nuxt) não precisou de nada disto.** As `NUXT_PUBLIC_*` já são lidas em tempo de
execução pelo Nitro, e é por isso que o mesmo `.output` responde como homologação ou como
produção só trocando variável de ambiente do processo (ver
`frontend-site/server/plugins/staging-noindex.ts`).
