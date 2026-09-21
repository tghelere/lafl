# Sessão 23 — As duas pendências do 503 (ADR 0019), fechadas

Continuação direta da sessão 22: a home sem os números institucionais e os textos de
`app/error.vue`, as duas coisas que aquela sessão deixou registradas e não decididas.

| Etapa | Commit |
|---|---|
| 1 — home sem API mostra frase alternativa completa | `cdf2854` |
| 2 — textos de `error.vue` e contato no 503 | `a64b124` |

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

## Decisões tomadas sem consulta

1. **A frase alternativa da home cobre fundação E tempo de bazar juntos**, não só a fundação —
   as duas linhas somem juntas hoje (mesmo `v-if`), então separar exigiria mudar essa estrutura
   sem necessidade. O texto evita qualquer número, inclusive o ano (1953/1968): o comentário do
   próprio `index.vue` já dizia "nunca digitados aqui", e a frase alternativa segue a mesma
   regra.
2. **`app/config/institution.ts` é um arquivo novo**, não reaproveita o texto já hardcoded em
   `AppFooter.vue`/`contato.vue`/`useOrganizationJsonLd.ts` (que já é uma duplicação conhecida
   e registrada — ver comentário daquele composable). Unificar os quatro lugares agora estava
   fora do pedido desta sessão; o comentário do arquivo novo registra a duplicação.
3. **O WhatsApp do 503 é o do Bazar**, usado como canal geral — não existe WhatsApp da sede
   confirmado (ver `docs/contexto.md`). Mesma decisão que `/contato` já tinha tomado.

## Verificação

| O quê | Resultado |
|---|---|
| Pint | passou |
| `nuxt build` + `nuxt generate` (site) | ok |
| Playwright, bateria inteira (`firefox` + `site-sem-api`) | 100/100 |

Conferido no navegador (MCP), não só por status: home com e sem API, em 375px e 1280px; 503 em
375px e 1280px, com telefone/WhatsApp visíveis e sem estouro horizontal; 404 com os textos
novos e os links de seção intactos.

## Pendente

Nada desta tarefa ficou pendente. Segue registrado, sem mudança nesta sessão: unificar os
quatro lugares que repetem endereço/telefone da instituição depende de ela virar entidade
editável no painel (`settings`, ver `docs/roadmap.md`).
