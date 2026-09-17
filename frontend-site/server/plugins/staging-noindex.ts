/**
 * Em `staging`, TODA resposta do site sai com `X-Robots-Tag: noindex, nofollow` — página
 * SSR, rota de API, redirect, 404, arquivo prerenderizado e arquivo estático de `public/`.
 *
 * O gancho `beforeResponse` do Nitro é o único ponto que alcança as três coisas ao mesmo
 * tempo. Medido nesta sessão contra o build de produção, com o servidor no ar:
 *
 * - middleware de `server/middleware/` NÃO roda para rota prerenderizada nem para arquivo
 *   estático (o handler de assets responde antes) — foi a primeira tentativa, e ela deixava
 *   `/o-que-fazemos`, `/sitemap.xml`, as fotos e os favicons sem cabeçalho nenhum;
 * - `routeRules` com `headers` alcança tudo, mas é resolvido em tempo de BUILD: um pacote
 *   gerado sem a variável e servido em homologação ficaria indexável, que é exatamente o
 *   engano contra o qual isto existe;
 * - `<meta name="robots">` só existiria no HTML, e é o PDF e o JSON da API que mais
 *   vazariam para a busca.
 *
 * Fora de `staging` o gancho não toca em nada.
 */
export default defineNitroPlugin((nitroApp) => {
  nitroApp.hooks.hook('beforeResponse', (event) => {
    if (!isStaging(event)) {
      return
    }

    setHeader(event, 'X-Robots-Tag', 'noindex, nofollow')
  })
})
