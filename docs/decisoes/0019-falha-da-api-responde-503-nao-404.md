# 0019 — Falha da API responde 503; só ausência de conteúdo responde 404

## Contexto

Toda página do site que lê conteúdo do CMS fazia, desde que passou a ler da API:

```ts
const { data, error } = await usePublicPage('educacao-infantil')

if (error.value) {
  throw createError({ statusCode: 404, statusMessage: 'Página não encontrada', fatal: true })
}
```

Sete páginas, o mesmo bloco. E `error.value` é verdadeiro para **qualquer** falha: 404 da API,
500 da API, 429, timeout, conexão recusada, resposta ilegível. Tudo virava 404.

Isso confunde duas afirmações que o buscador lê de formas opostas:

| Status | O que afirma | O que o Google faz |
|---|---|---|
| 404 | "este conteúdo não existe" | trata como removido; repetido, tira do índice |
| 503 | "não consigo responder agora" | mantém o endereço e volta depois |

O site existe, em boa parte, para que a prestação de contas da instituição seja **encontrada**
(ver ADR 0017 e 0018). Anunciar como removido tudo o que o site publica, a cada instabilidade
da API, desfaz exatamente isso.

Não era hipótese. Na sessão 22 o motor do Docker Desktop parou na máquina de desenvolvimento;
Postgres e Redis foram junto; a API passou a responder 500 em toda página. O sintoma observado
foi "a maioria das páginas do site retorna 404" — e uma hora de diagnóstico foi gasta
procurando conteúdo apagado, seeder alterado e URL de API trocada, porque **o site estava
afirmando que o conteúdo não existia**. Em produção, o mesmo episódio teria essa afirmação
registrada pelo Google.

## Decisão

**Só o 404 vindo da API vira 404 do site. Qualquer outra falha vira 503, com
`Cache-Control: no-store`.**

A decisão mora em `frontend-site/app/utils/apiPageError.ts`, num lugar só, e a checagem é por
igualdade a `404` — nunca por "não é 2xx". Conexão recusada e timeout não chegam com status
nenhum (o `createError` do Nuxt os normaliza para 500), então a regra falha para o lado do 503,
que é o lado certo: errar para o 503 custa uma visita adiada; errar para o 404 custa o endereço
no índice.

Três peças sustentam isso:

1. **`apiPageError.ts`** decide o status a partir do erro.
2. **Teto de tempo em `usePublicPage`** (4s, sem retentativa). A chamada acontece dentro do
   SSR: sem teto, uma API que aceita a conexão e não responde prende a renderização pelo tempo
   que o Node aguentar, e o visitante olha uma aba em branco até o 504 de algum proxy.
   Retentativa ficou de fora porque dobraria o pior caso justamente quando a API está mal — e o
   503 já diz ao buscador para voltar depois, o que transfere a retentativa para ele.
3. **`server/plugins/sem-cache-em-erro.ts`** põe `no-store` em toda 5xx. Sem isso, um proxy
   pode servir a página de erro a quem chegar depois, e dez minutos de instabilidade da API
   viram uma hora de site fora do ar já sem API nenhuma envolvida.

O gancho do plugin é o `error`, e só ele. Medido nesta sessão com um probe registrando os
quatro ganchos: numa resposta de erro do Nuxt, `beforeResponse`, `render:response` e
`afterResponse` não chegam a rodar. É o mesmo buraco que obrigou o bloqueio de indexação da
homologação a ter duas metades (`server/plugins/staging-noindex.ts`), encontrado pelo outro
lado. Definir o cabeçalho no evento basta porque o tratador padrão do Nitro só escreve
`no-cache` quando ainda não existe um
(`if (statusCode === 404 || !getResponseHeader(event, 'cache-control'))`).

Não é raciocínio novo no projeto: `server/routes/sitemap.xml.ts` já respondia 503 em vez de
servir um sitemap truncado com 200 (sessão 21), pelo mesmo motivo. O que faltava era aplicar
isso às páginas.

## Consequências

- **Rascunho continua sendo 404**, e precisa continuar: rascunho é ausência real de conteúdo
  público, a API responde 404, e o site repete. Há teste de ponta a ponta trancando isso
  (`e2e/tests/layout/pagina-de-erro-do-site.spec.ts`), porque é a metade da regra que
  silenciosamente se inverteria primeiro.
- **A página de documentos de transparência entrou na mesma regra.** Ela *é* o acervo; a falha
  era engolida num `?? []` e a página saía em 200 dizendo "Nenhum documento encontrado" — a
  instituição anunciando que não presta contas, no endereço que existe para provar o
  contrário. Filtro que não casa com nada continua 200 com lista vazia: ali a resposta veio e
  está certa.
- **A home fica de fora da regra do 503, decidido.** O conteúdo dela é fixo no `.vue`; só as
  idades vêm da API (`useInstitutionFacts`) e degradam para nada. Derrubar a home inteira em
  503 por causa de um número seguiu desproporcional — a saída foi a segunda opção que este
  documento deixava em aberto: uma frase alternativa completa, sem nenhum número calculado, no
  lugar das duas linhas de registro (fundação e tempo de bazar) quando `useInstitutionFacts`
  falha. Ver `frontend-site/app/pages/index.vue`. Cobertura de ponta a ponta dos dois casos
  (com e sem os números) em `e2e/tests/paginas/numeros-calculados.spec.ts` e
  `e2e/tests/site-sem-api/home-sem-api.spec.ts` — o segundo só é alcançável numa terceira
  instância do site com a API fora do ar (`playwright.config.ts`, projeto `site-sem-api`, novo
  nesta sessão), pelo mesmo motivo do item abaixo.
- **O 503 passou a ser exercitável pela bateria de e2e.** A mesma terceira instância do site
  usada acima cobre o que o `webServer` normal não alcança para as páginas que leem o CMS: API
  de pé o tempo todo lá, e a chamada que precisaria falhar é do SSR, fora do `page.route()`. Ver
  `e2e/tests/site-sem-api/pagina-sem-api.spec.ts`: 503 com `Cache-Control: no-store`, e
  telefone/WhatsApp da sede (lidos de `frontend-site/app/config/institution.ts`, nunca da API)
  no lugar dos links de seção que `app/error.vue` mostra no 404.
