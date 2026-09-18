/**
 * O PDF de transparência servido no domínio do SITE, em
 * `/transparencia/documentos/{ano}/{slug}.pdf`.
 *
 * Por que não mandar o visitante direto à API: é o site que precisa ser indexado. Um PDF
 * hospedado em `api.dominio` conta como conteúdo de outro host na busca, e a instituição
 * depende de que o balanço apareça no Google junto das páginas que falam dele. Além disso a
 * URL antiga (`/api/v1/public/transparency-documents/{uuid}/download`) não tinha nome nenhum:
 * nem o buscador nem a pessoa que salva o arquivo sabiam o que era.
 *
 * O arquivo é `[slug].ts` e não `[slug].get.ts` de propósito: com o sufixo de método o Nitro
 * casa só GET, e um HEAD (o que `curl -I`, e vários rastreadores, mandam antes de baixar)
 * caía na página 404 do site. Medido nesta sessão contra o build de produção.
 *
 * Esta rota é proxy PURO: não decide 404, não decide redirect, não conta download. Quem
 * decide é a API, cuja rota espelha este caminho segmento a segmento (ver
 * App\Support\Transparency\DocumentUrl e routes/api_v1.php) — regra 1 do CLAUDE.md, e o mesmo
 * desenho do proxy dos formulários em server/api/forms/[tipo].post.ts.
 */
export default defineEventHandler(async (event) => {
  const { year, slug } = getRouterParams(event)
  const { apiUrl } = useRuntimeConfig(event).public

  // Só o que a API aceitaria de qualquer forma. Barrar aqui evita transformar um caminho
  // qualquer (ex.: um ".map" pedido por engano) numa chamada à API.
  if (!/^\d{4}$/.test(year ?? '') || !/^[a-z0-9-]+\.pdf$/.test(slug ?? '')) {
    throw createError({ statusCode: 404, statusMessage: 'Documento não encontrado' })
  }

  const upstream = await fetch(`${apiUrl}/api/v1/public/transparency-documents/${year}/${slug}`, {
    // `manual`: o 301 de ano trocado precisa chegar ao navegador do visitante, não ser
    // seguido às escondidas por este hop servidor-a-servidor — é o buscador que precisa ver
    // o redirect para trocar a URL que ele tem indexada.
    redirect: 'manual',
    headers: {
      // O User-Agent que interessa é o de quem está do outro lado, não o deste servidor: é
      // por ele que a API não soma robô na contagem de download (ver
      // App\Support\Http\KnownBots). Sem este repasse, todo acesso chegaria à API com a
      // assinatura do Node e a contagem viraria ficção.
      'user-agent': getRequestHeader(event, 'user-agent') ?? '',
      'accept': 'application/pdf',
    },
  })

  if (upstream.status === 301 || upstream.status === 302) {
    const location = upstream.headers.get('location')

    if (location) {
      return sendRedirect(event, location, 301)
    }
  }

  if (!upstream.ok) {
    throw createError({ statusCode: 404, statusMessage: 'Documento não encontrado' })
  }

  setHeader(event, 'Content-Type', 'application/pdf')
  setHeader(
    event,
    'Content-Disposition',
    upstream.headers.get('content-disposition') ?? `inline; filename=${slug}`,
  )
  setHeader(event, 'Cache-Control', upstream.headers.get('cache-control') ?? 'public, max-age=3600')

  // Buffer em vez de stream: o upload é limitado a 20 MB pela API
  // (StoreTransparencyDocumentRequest), então o pior caso cabe na memória sem drama, e o
  // corpo inteiro nas mãos permite responder com Content-Length correto.
  return Buffer.from(await upstream.arrayBuffer())
})
