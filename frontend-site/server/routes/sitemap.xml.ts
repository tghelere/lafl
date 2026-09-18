/**
 * Sitemap gerado a cada requisição, a partir do conteúdo real: as rotas fixas indexáveis,
 * todas as páginas publicadas do CMS e todos os documentos de transparência publicados.
 *
 * Por que não é mais prerenderizado (saiu de `nitro.prerender.routes` nesta sessão): um
 * sitemap gravado no build congela o acervo do dia do build. Publicar um balanço pelo painel
 * passaria a exigir novo deploy para que o Google soubesse que ele existe — exatamente o que
 * o painel existe para evitar, e o mesmo raciocínio que já tirou dali as páginas do CMS e o
 * robots.txt (ver o comentário em nuxt.config.ts).
 *
 * Fica de fora, de propósito: o que é `noindex` (/como-ajudar/voluntariado,
 * /contraturno/apoiar e as cinco /obrigado/*) e o que não está publicado — rascunho e
 * documento não publicado nem chegam aqui, porque a API pública só devolve publicado.
 */

/**
 * Páginas cujo conteúdo é fixo no `.vue`, sem registro em `pages` — a API não sabe que
 * existem. Acrescentar aqui ao criar página nova de conteúdo fixo e indexável.
 */
const ROTAS_FIXAS = [
  '/',
  '/o-que-fazemos',
  '/politica-de-privacidade',
  '/contato',
  '/transparencia/documentos',
  '/bazar/agendar-coleta',
  '/contraturno/inscricao',
]

/**
 * Slug do CMS cujo endereço no site é OUTRO. Hoje só um: "como-ajudar/doar" é servido em
 * /doar (ver app/pages/doar.vue), e o endereço antigo responde 301 — listar o antigo no
 * sitemap seria pedir ao buscador que indexasse um redirect.
 */
const CAMINHO_POR_SLUG: Record<string, string> = {
  'como-ajudar/doar': '/doar',
}

type ItemPaginado = { slug?: string; path?: string; updated_at?: string; published_at?: string }
type RespostaPaginada = { data: ItemPaginado[]; meta?: { current_page: number; last_page: number } }

/** Teto de segurança: acervo real tem dezenas de itens, não milhares. */
const MAXIMO_DE_PAGINAS = 50

async function buscarTudo(endpoint: string, porPagina: number): Promise<ItemPaginado[]> {
  const itens: ItemPaginado[] = []
  let pagina = 1
  let ultima = 1

  do {
    const resposta = await $fetch<RespostaPaginada>(endpoint, {
      query: { per_page: porPagina, page: pagina },
    })

    itens.push(...resposta.data)
    ultima = resposta.meta?.last_page ?? 1
    pagina++
  } while (pagina <= ultima && pagina <= MAXIMO_DE_PAGINAS)

  return itens
}

function escaparXml(valor: string): string {
  return valor
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&apos;')
}

function url(base: string, caminho: string, lastmod?: string): string {
  const loc = escaparXml(`${base}${caminho}`)
  const data = lastmod ? `\n    <lastmod>${escaparXml(lastmod)}</lastmod>` : ''

  return `  <url>\n    <loc>${loc}</loc>${data}\n  </url>`
}

export default defineEventHandler(async (event) => {
  const { siteUrl, apiUrl } = useRuntimeConfig(event).public

  // Sem NUXT_PUBLIC_SITE_URL (ver CLAUDE.md, "Estado do projeto": domínio ainda não
  // registrado) o <loc> sai relativo, o que a especificação do sitemap não aceita. Segue
  // saindo assim mesmo, como já era antes: em desenvolvimento ninguém consome este arquivo, e
  // em servidor a variável está sempre definida — é o robots.txt que decide anunciar ou não o
  // sitemap, e ele só anuncia quando há domínio.
  const base = siteUrl ? siteUrl.replace(/\/$/, '') : ''

  let paginas: ItemPaginado[]
  let documentos: ItemPaginado[]

  try {
    // Em série, não em paralelo: são duas chamadas locais e baratas, e uma falha de cada vez
    // é mais fácil de ler no log do que duas rejeições simultâneas.
    paginas = await buscarTudo(`${apiUrl}/api/v1/public/pages`, 100)
    documentos = await buscarTudo(`${apiUrl}/api/v1/public/transparency-documents`, 50)
  } catch {
    // 503, não um sitemap parcial: o buscador tenta de novo mais tarde e mantém o último bom.
    // Um arquivo truncado servido com 200 declararia que o acervo encolheu.
    throw createError({ statusCode: 503, statusMessage: 'Sitemap indisponível' })
  }

  const urls = [
    ...ROTAS_FIXAS.map((caminho) => url(base, caminho)),
    ...paginas.map((pagina) =>
      url(base, CAMINHO_POR_SLUG[pagina.slug ?? ''] ?? `/${pagina.slug}`, pagina.updated_at),
    ),
    // O caminho do PDF vem pronto da API (campo `path`) — o site não monta URL de documento
    // (ver App\Support\Transparency\DocumentUrl).
    ...documentos.map((documento) => url(base, documento.path ?? '', documento.published_at)),
  ]

  setHeader(event, 'Content-Type', 'application/xml; charset=utf-8')

  return `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
${urls.join('\n')}
</urlset>
`
})
