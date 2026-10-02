/** Logo de um parceiro, pronta para um `<img>` (App\Http\Resources\Public\PartnerResource). */
export type PublicPartner = {
  name: string
  /** Endereço do parceiro, http(s). Sem ele o cartão não é link. */
  url: string | null
  logo: {
    src: string
    srcset: string
    width: number
    height: number
  }
}

/**
 * Parceiros ativos, na ordem definida no painel. Falha da API vira lista vazia, e a lista vazia
 * não desenha nada: a página de parceiros continua mostrando o texto, que é o conteúdo dela. É
 * uma seção que complementa o texto, e não vale derrubar a página inteira por ela.
 */
export function usePublicPartners() {
  const config = useRuntimeConfig()

  return useAsyncData<PublicPartner[]>('public-partners', async () => {
    try {
      const response = await $fetch<{ data: PublicPartner[] }>(`${config.public.apiUrl}/api/v1/public/partners`, {
        query: { per_page: 100 },
        timeout: 4000,
        retry: 0,
      })

      return response.data
    } catch {
      return []
    }
  })
}
