# Sessão 23 — As duas pendências do 503 (ADR 0019), fechadas

Continuação direta da sessão 22: a home sem os números institucionais e os textos de
`app/error.vue`, as duas coisas que aquela sessão deixou registradas e não decididas.

| Etapa | Commit |
|---|---|
| 1 — home sem API mostra frase alternativa completa | `cdf2854` |
| 2 — textos de `error.vue` e contato no 503 | `a64b124` |
| 3 — fim da duplicação de endereço/telefone/WhatsApp | `66b820f` |

## Etapa 1 — a home sem `useInstitutionFacts` (`cdf2854`)

A sessão 22 tinha deixado as duas linhas de registro (fundação e tempo de bazar) simplesmente
sumirem quando a API falha — sem decidir se isso bastava. Não bastava: sumir sem substituto
deixa a seção "A instituição em números" com só duas linhas em vez de quatro, sem dizer por
quê. Agora entra uma frase completa no lugar das duas, sem nenhum número calculado (`app/pages/
index.vue`, classe `.ledger__fallback`).

A cobertura de ponta a ponta dos dois casos exigiu resolver o mesmo problema que a sessão 22
já tinha registrado para o 503: o caminho "sem dados" depende de uma chamada do SSR, fora do
`page.route()` do Playwright. A solução, orientada pelo usuário em vez de introduzir um
framework de teste de componente novo (Vitest foi cogitado e recusado — este projeto testa só
com Pest e Playwright, pilha real): uma **terceira instância do mesmo build do site**, com
`NUXT_PUBLIC_API_URL` apontado para uma porta fechada, subida como um quarto item do array
`webServer` de `playwright.config.ts`, num projeto Playwright próprio (`site-sem-api`).

Verificado nesta sessão, antes de escrever a configuração: os itens do array `webServer` do
Playwright sobem **em sequência**, cada um esperando o anterior responder antes de começar
(`TaskRunner.run`, em `node_modules/playwright/lib/runner/index.js`) — não em paralelo. É o que
torna seguro o quarto item só rodar `node .output/server/index.mjs`, sem `npm run build`: o
build já terminou no item anterior quando ele começa. A verificação de saúde do Playwright
aceita qualquer status abaixo de 404, e por isso a URL de saúde do quarto servidor é a home —
ela responde 200 mesmo sem API, o que só é verdade depois da mudança desta etapa.

O caso "com dados" ganhou teste próprio em `numeros-calculados.spec.ts` (bateria normal, API de
pé): não existia cobertura nenhuma da renderização das quatro linhas antes desta sessão.

## Etapa 2 — textos de `error.vue` e contato no 503 (`a64b124`)

Textos definidos pela instituição, substituindo os da sessão 22:

| | Antes | Agora |
|---|---|---|
| 404, título | Página não encontrada | (sem mudança) |
| 404, texto | O endereço que você abriu não existe ou foi movido... | Não encontramos esta página. Ela pode ter mudado de endereço. |
| 503, título | Página indisponível no momento | Instabilidade momentânea |
| 503, texto | Não conseguimos carregar o conteúdo desta página agora... | O site está com uma instabilidade momentânea. Tente novamente em alguns minutos. |

No 503, no lugar dos links de seção (que no 404 continuam), entram telefone e WhatsApp da sede,
com endereço — `tel:` e `wa.me` reais. Os três dados vêm de `app/config/institution.ts`, arquivo
novo desta sessão: nunca da API, de propósito — se ela caiu é exatamente quando um canal
alternativo mais importa. O WhatsApp é o do Bazar (único número confirmado, ver
`docs/contexto.md`), usado como canal geral da instituição, do mesmo jeito que `/contato` já
faz.

O 503 ganhou cobertura de ponta a ponta reaproveitando a terceira instância da etapa 1
(`e2e/tests/site-sem-api/pagina-sem-api.spec.ts`): status, `Cache-Control: no-store`, ausência
dos links de seção, telefone/WhatsApp com o `href` certo, e a tela cabendo em 375px e 1280px
sem rolagem horizontal.

## Etapa 3 — fim da duplicação de endereço/telefone/WhatsApp (`66b820f`)

Pedido à parte, depois das etapas 1 e 2: `AppFooter.vue`, `contato.vue` e
`useOrganizationJsonLd.ts` passaram a ler de `app/config/institution.ts` (que já existia desde
a etapa 2, só para `error.vue`) em vez de repetir o mesmo endereço e telefone à própria mão.
Fecha a decisão 2 abaixo, tomada na etapa 2 por estar fora do pedido daquela hora.

O arquivo guarda só as partes canônicas de cada local (`headquarters`, `bazaar`) — rua, bairro
em duas formas (completa para o JSON-LD, abreviada para prosa), DDD e número — e seis funções
pequenas derivam os formatos que cada consumidor já usava: `phone` ("(43) 3325-8060"),
`phoneHref` ("tel:+554333258060"), `jsonLdTelephone` ("+55 43 3325-8060", formato que o
schema.org já publicava), `addressLine` (a linha de prosa do rodapé/contato/erro), `mapQuery`
(o texto que `AppMapaLocal` manda para o link de mapa — vírgula em vez de travessão) e
`jsonLdStreetAddress` (rua + bairro por extenso, sem cidade/UF — campos à parte no schema.org).

Conferido no navegador, antes e depois, com `curl` contra o HTML servido: rodapé, `/contato`
(incluindo os dois links de mapa) e o bloco `<script type="application/ld+json">` da home saem
**byte a byte iguais** ao que eram antes da mudança — é um refactor puro, nenhum texto visível
mudou.

`bazar/agendar-coleta.vue` fica de fora de propósito: usa o mesmo número de WhatsApp do bazar,
mas com mensagem própria do contexto de agendamento de coleta (`institutionContact` deste
arquivo tem a mensagem padrão, de contato geral) — comentário novo no arquivo explica a
exclusão.

`docs/roadmap.md` ganhou uma nota na entrada pendente de `settings`: quando essa entidade
existir, ela substitui só `app/config/institution.ts` — os quatro consumidores continuam lendo
a mesma forma.

## Decisões tomadas sem consulta

1. **A frase alternativa da home cobre fundação E tempo de bazar juntos**, não só a fundação —
   as duas linhas somem juntas hoje (mesmo `v-if`), então separar exigiria mudar essa estrutura
   sem necessidade. O texto evita qualquer número, inclusive o ano (1953/1968): o comentário do
   próprio `index.vue` já dizia "nunca digitados aqui", e a frase alternativa segue a mesma
   regra.
2. **`app/config/institution.ts` nasceu na etapa 2 como arquivo novo**, sem reaproveitar o
   texto já hardcoded em `AppFooter.vue`/`contato.vue`/`useOrganizationJsonLd.ts` (duplicação
   conhecida e registrada desde antes desta sessão). Unificar os quatro lugares estava fora do
   pedido daquela etapa — fechado na etapa 3, a pedido, mais tarde na mesma sessão.
3. **O WhatsApp do 503 é o do Bazar**, usado como canal geral — não existe WhatsApp da sede
   confirmado (ver `docs/contexto.md`). Mesma decisão que `/contato` já tinha tomado.

## Verificação

| O quê | Resultado |
|---|---|
| Pint | passou |
| `nuxt build` (site, três vezes — uma por etapa) | ok |
| `nuxt generate` (site) | ok |
| Playwright, bateria inteira (`firefox` + `site-sem-api`) | 100/100, refeita depois da etapa 3 |

Conferido no navegador (MCP), não só por status: home com e sem API, em 375px e 1280px; 503 em
375px e 1280px, com telefone/WhatsApp visíveis e sem estouro horizontal; 404 com os textos
novos e os links de seção intactos. Etapa 3 conferida por `curl` contra o HTML servido, antes e
depois, comparando byte a byte (rodapé, `/contato`, JSON-LD da home).

## Pendente

Nada. A duplicação de endereço/telefone/WhatsApp que a etapa 2 tinha deixado registrada foi
fechada na etapa 3. O que sobrevive, sem mudança nesta sessão: a futura entidade `settings`
substituirá `app/config/institution.ts` — anotado em `docs/roadmap.md`.
