import type { H3Event } from 'h3'

const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/
const VARIANT = /^\d{2,4}\.webp$/

/**
 * Imagem da biblioteca do painel servida no domínio do SITE — `/midia/{uuid}` e
 * `/midia/{uuid}/{largura}.webp`. É o `src` que o conteúdo das páginas traz (ver
 * App\Support\Media\MediaUrl e App\Actions\Media\ExpandContentImages).
 *
 * Mesmo desenho do PDF de transparência (server/routes/transparencia/documentos/): proxy PURO
 * para uma rota da API que espelha o caminho. Qual largura servir, se a imagem pode ser vista
 * (a marcada como de assistido responde 404), ETag e cache — tudo decisão da API.
 *
 * Por que no domínio do site: o arquivo fica fora do webroot da API (docs/protecao-de-dados.md),
 * a imagem conta como conteúdo do próprio site para a busca, e o endereço não muda quando o
 * arquivo é substituído pelo painel.
 */
export async function proxyMedia(event: H3Event, uuid: string | undefined, variant?: string): Promise<Buffer | null> {
  if (!UUID.test(uuid ?? '') || (variant !== undefined && !VARIANT.test(variant))) {
    throw createError({ statusCode: 404, statusMessage: 'Imagem não encontrada' })
  }

  const { apiUrl } = useRuntimeConfig(event).public
  const path = variant === undefined ? uuid : `${uuid}/${variant}`

  let upstream: Response

  try {
    upstream = await fetch(`${apiUrl}/api/v1/public/media/${path}`, {
      headers: {
        accept: 'image/webp',
        // Revalidação ponta a ponta: o navegador pergunta "mudou?" e a API responde 304 sem
        // corpo. Sem repassar, cada revalidação baixaria a imagem inteira da API.
        ...(getRequestHeader(event, 'if-none-match') ? { 'if-none-match': getRequestHeader(event, 'if-none-match')! } : {}),
      },
    })
  } catch {
    // API fora do ar é 503, não 404: um 404 aqui poderia ser guardado como "esta imagem não
    // existe" (ver docs/decisoes/0019-falha-da-api-responde-503-nao-404.md).
    throw createError({ statusCode: 503, statusMessage: 'Service Unavailable' })
  }

  for (const header of ['cache-control', 'etag', 'last-modified']) {
    const value = upstream.headers.get(header)

    if (value) {
      setHeader(event, header, value)
    }
  }

  if (upstream.status === 304) {
    setResponseStatus(event, 304)

    return null
  }

  if (upstream.status === 404) {
    throw createError({ statusCode: 404, statusMessage: 'Imagem não encontrada' })
  }

  if (!upstream.ok) {
    throw createError({ statusCode: 503, statusMessage: 'Service Unavailable' })
  }

  setHeader(event, 'content-type', 'image/webp')
  setHeader(event, 'x-content-type-options', 'nosniff')

  // Buffer, como o PDF: uma derivada tem dezenas a poucas centenas de KB (a original, maior,
  // nunca passa por aqui), e o corpo inteiro permite responder com Content-Length correto.
  return Buffer.from(await upstream.arrayBuffer())
}
