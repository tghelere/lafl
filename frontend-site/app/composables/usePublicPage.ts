/**
 * Imagem da biblioteca pronta para um `<img>` (App\Actions\Media\BuildPublicPageImages). O
 * `sizes` não vem da API: quanto da tela a imagem ocupa é decisão do layout (ver AppImagem.vue).
 */
export type PublicImage = {
  src: string
  srcset: string
  width: number
  height: number
  alt: string
  caption: string | null
}

export type PublicPage = {
  slug: string
  title: string
  content: string
  /**
   * Capa e galeria, editadas no painel ("Imagens desta página"). A capa não aparece na própria
   * página: é o que as outras usam para representá-la (o cartão de "O que fazemos" e o
   * destaque da página inicial).
   */
  images: {
    cover: PublicImage | null
    gallery: PublicImage[]
  }
  meta_title: string | null
  meta_description: string | null
}

export type PublicPageEnvelope =
  | { data: PublicPage }
  | { redirect_to: string }

/**
 * Busca uma página institucional pelo slug completo (ex.: "quem-somos/nossa-historia").
 * O envelope pode ser conteúdo (`data`) ou um sinal de slug antigo (`redirect_to`) — quem
 * decide o que fazer com isso é a página, não este composable (ver
 * App\Http\Controllers\Api\V1\Public\PageController no backend, que nunca devolve 301 real
 * neste hop servidor-a-servidor).
 */
export function usePublicPage(slug: string) {
  const config = useRuntimeConfig()

  return useAsyncData<PublicPageEnvelope>(`public-page:${slug}`, () =>
    $fetch(`${config.public.apiUrl}/api/v1/public/pages/${slug}`, {
      // Esta chamada acontece dentro do SSR: enquanto ela não termina, o visitante olha para
      // uma aba em branco e o rastreador segura uma conexão. Sem teto, uma API que aceita a
      // conexão e não responde (banco travado, fila de PHP-FPM cheia) prende a renderização
      // pelo tempo que o Node aguentar. Com teto, vira uma falha em quatro segundos, que
      // `lancarErroDePagina` converte em 503 — resposta honesta e rápida, em vez de uma
      // espera que termina em 504 do proxy.
      timeout: 4000,
      // Sem retentativa, de propósito. Ela dobraria o pior caso (quatro segundos viram oito)
      // justamente quando a API está mal, e o 503 já diz ao buscador para voltar depois —
      // quem retenta é ele, sem segurar ninguém esperando.
      retry: 0,
    }),
  )
}
