/**
 * Segunda metade do bloqueio de indexação da homologação — a primeira é
 * server/plugins/staging-noindex.ts, que explica o desenho inteiro.
 *
 * Existe porque os dois ganchos têm buracos complementares, medidos nesta sessão contra o
 * build de produção:
 *
 * - o `beforeResponse` do plugin alcança arquivo estático e rota prerenderizada, mas NÃO a
 *   resposta de erro: a página 404 saía sem cabeçalho nenhum;
 * - este middleware alcança a 404 (roda antes de a rota deixar de ser encontrada), mas não
 *   alcança arquivo estático nem rota prerenderizada, servidos antes dele.
 *
 * Juntos não sobra nada. `setHeader` sobrescreve, então a resposta que passa pelos dois sai
 * com um cabeçalho só.
 */
export default defineEventHandler((event) => {
  if (!isStaging(event)) {
    return
  }

  setHeader(event, 'X-Robots-Tag', 'noindex, nofollow')
})
