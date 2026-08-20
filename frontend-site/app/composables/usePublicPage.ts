export type PublicPage = {
  slug: string
  title: string
  content: string
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
    $fetch(`${config.public.apiUrl}/api/v1/public/pages/${slug}`),
  )
}
