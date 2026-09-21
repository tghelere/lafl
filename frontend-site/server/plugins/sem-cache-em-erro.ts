/**
 * Nenhuma resposta 5xx do site pode ser guardada por ninguém: nem pelo navegador, nem por um
 * proxy no caminho, nem pela CDN que um dia entre na frente.
 *
 * É a outra metade do 503 de `app/utils/apiPageError.ts`. O status diz ao buscador "volte
 * depois"; sem isto, um intermediário pode servir essa mesma página de erro a quem chegar em
 * seguida — e aí a instabilidade de dez minutos da API vira uma hora de site fora do ar, já
 * sem nenhuma API envolvida.
 *
 * **Por que o gancho é o `error`, e só ele.** Medido nesta sessão, com o probe registrando os
 * quatro ganchos: numa resposta de erro do Nuxt, `beforeResponse`, `render:response` e
 * `afterResponse` NÃO chegam a rodar — o único que roda é `error`. É o mesmo buraco que
 * obrigou o bloqueio de indexação da homologação a ter duas metades (ver
 * `server/plugins/staging-noindex.ts`), aqui encontrado pelo outro lado.
 *
 * **Por que definir o cabeçalho no evento basta.** O tratador de erro padrão do Nitro só
 * escreve `cache-control: no-cache` quando o evento ainda não tem esse cabeçalho:
 * `if (statusCode === 404 || !getResponseHeader(event, 'cache-control'))`
 * (`nitropack/dist/runtime/internal/error/prod.mjs`). Definindo antes, o `no-store` sobrevive.
 * A condição também explica por que 404 fica de fora aqui: para ela o Nitro sobrescreve de
 * qualquer jeito — e `no-cache` numa 404 está certo, é revalidação, não cache eterno.
 */
export default defineNitroPlugin((nitroApp) => {
  nitroApp.hooks.hook('error', (erro, { event }) => {
    if (!event) {
      return
    }

    const status = (erro as { statusCode?: number }).statusCode ?? 500

    if (status < 500) {
      return
    }

    setHeader(event, 'Cache-Control', 'no-store')
  })

  // Resposta 5xx que NÃO nasce de uma exceção — uma rota de `server/` que devolve o status na
  // mão — não passa pelo gancho acima, e passa por este. Os dois juntos não deixam sobrar 5xx
  // cacheável.
  nitroApp.hooks.hook('beforeResponse', (event) => {
    if (getResponseStatus(event) < 500) {
      return
    }

    setHeader(event, 'Cache-Control', 'no-store')
  })
})
