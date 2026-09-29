/**
 * Endereço antigo das fotos do site, `/fotos/{secao}/{chave}-{largura}.{webp,jpg}`. Até a sessão
 * 28 eram arquivos em public/fotos/; hoje as fotos vêm da biblioteca, em /midia/ (ver
 * docs/decisoes/0025-imagens-da-pagina.md). O que um buscador indexou continua levando à mesma
 * foto, com 301.
 *
 * Proxy PURO, como o PDF de transparência (server/routes/transparencia/documentos/): quem sabe
 * para onde cada endereço vai, e se a foto ainda pode ser vista, é a API. Aqui só se repassa o
 * 301.
 *
 * O mapa de /contato continua sendo arquivo em public/fotos/contato/, e o Nitro serve arquivo
 * estático antes de chegar a qualquer rota: esta não o alcança.
 *
 * Sem sufixo de método (`.get.ts`), para o HEAD de um rastreador também receber o 301.
 */
export default defineEventHandler(async (event) => {
  const { section, file } = getRouterParams(event)

  // Só a forma que existia. Barrar aqui evita transformar um caminho qualquer numa chamada à API.
  if (!/^[a-z-]+$/.test(section ?? '') || !/^[a-z0-9-]+-\d{2,4}\.(webp|jpg)$/.test(file ?? '')) {
    throw createError({ statusCode: 404, statusMessage: 'Imagem não encontrada' })
  }

  const { apiUrl } = useRuntimeConfig(event).public
  let upstream: Response

  try {
    upstream = await fetch(`${apiUrl}/api/v1/public/legacy-photos/${section}/${file}`, { redirect: 'manual' })
  } catch {
    // API fora do ar é 503, não 404 (ver docs/decisoes/0019-falha-da-api-responde-503-nao-404.md).
    throw createError({ statusCode: 503, statusMessage: 'Service Unavailable' })
  }

  const location = upstream.headers.get('location')

  if (upstream.status === 301 && location) {
    return sendRedirect(event, location, 301)
  }

  if (upstream.status === 404) {
    throw createError({ statusCode: 404, statusMessage: 'Imagem não encontrada' })
  }

  throw createError({ statusCode: 503, statusMessage: 'Service Unavailable' })
})
