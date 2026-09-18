import type { MaybeRefOrGetter } from 'vue'

/**
 * Imagem Open Graph padrão: 1200×630, cópia manual de
 * `shared/brand/lar-analia-franco/og/og-padrao.png` (ver o LEIA-ME de lá — é consumida pelo
 * rastreador do Facebook/WhatsApp por URL absoluta, fora do grafo de módulos JS).
 */
const OG_IMAGE_PADRAO = '/og/og-padrao.png'

const OG_IMAGE_PADRAO_ALT =
  'Logo do Lar Anália Franco: uma casa amarela com a palavra LAR, ao lado do nome da instituição'

const NOME_DO_SITE = 'Lar Anália Franco'

export type PageSeoOptions = {
  /** Título completo da aba e do cartão de compartilhamento, já com o sufixo institucional. */
  title: MaybeRefOrGetter<string>
  description?: MaybeRefOrGetter<string | undefined>
  /**
   * Caminho canônico, quando NÃO é o caminho da rota atual. Só quem tem query string
   * significativa precisa disto (ver app/pages/transparencia/documentos.vue): sem isto o
   * canônico é `route.path`, que já descarta query e âncora.
   */
  canonicalPath?: MaybeRefOrGetter<string>
  /** Página que não deve ser indexada — formulário e confirmação pós-envio. */
  noindex?: boolean
  /** Caminho de imagem própria para o cartão, quando a padrão não serve. */
  imagePath?: string
  imageAlt?: string
  /** `article` em página de notícia, quando existirem; o resto do site é `website`. */
  ogType?: 'website' | 'article'
}

/**
 * Metadados de uma página: título, descrição, canônico absoluto, Open Graph e cartão do
 * Twitter/X. Um lugar só — antes cada página montava o seu `useSeoMeta` e, na prática,
 * nenhuma tinha canônico, `og:url` nem `og:image`.
 *
 * Toda página do site chama isto. Canônico ausente é o que faz o buscador escolher sozinho
 * qual endereço indexar quando o mesmo conteúdo responde em mais de um (com e sem query
 * string, por exemplo).
 *
 * Página `noindex` sai só com título, descrição e a diretiva de robô: canônico, Open Graph e
 * cartão existem para ser indexado e compartilhado, e essas páginas não podem ser nem uma
 * coisa nem outra — são formulário e confirmação de envio. Emitir canônico numa página que
 * pede para não ser indexada é mandar dois sinais que se contradizem.
 *
 * Absoluto, não relativo: `og:url`, `og:image` e `<link rel="canonical">` são lidos fora do
 * contexto do site (rastreador, pré-visualização de link em mensageiro), onde caminho
 * relativo não resolve. A base vem de `NUXT_PUBLIC_SITE_URL`.
 */
export function usePageSeo(options: PageSeoOptions) {
  const route = useRoute()
  const { siteUrl } = useRuntimeConfig().public

  const base = siteUrl.replace(/\/$/, '')
  const absolute = (path: string) => `${base}${path}`

  const title = () => toValue(options.title)
  const description = () => toValue(options.description)
  const canonical = () => absolute(toValue(options.canonicalPath) ?? route.path)
  const image = absolute(options.imagePath ?? OG_IMAGE_PADRAO)

  if (options.noindex) {
    useSeoMeta({ title, description, robots: 'noindex, nofollow' })

    return
  }

  useSeoMeta({
    title,
    description,

    ogTitle: title,
    ogDescription: description,
    ogUrl: canonical,
    ogType: options.ogType ?? 'website',
    ogLocale: 'pt_BR',
    ogSiteName: NOME_DO_SITE,
    ogImage: image,
    ogImageWidth: 1200,
    ogImageHeight: 630,
    ogImageAlt: options.imageAlt ?? OG_IMAGE_PADRAO_ALT,

    // Sem conta no X — o cartão existe porque é o formato que vários leitores de link além
    // do próprio X consomem, e sem ele o compartilhamento cai no cartão pequeno sem imagem.
    twitterCard: 'summary_large_image',
    twitterTitle: title,
    twitterDescription: description,
    twitterImage: image,
    twitterImageAlt: options.imageAlt ?? OG_IMAGE_PADRAO_ALT,
  })

  useHead({
    link: [{ rel: 'canonical', key: 'canonical', href: canonical }],
  })
}
