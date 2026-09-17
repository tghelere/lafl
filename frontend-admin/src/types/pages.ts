export interface ContentPage {
  id: string
  slug: string
  title: string
  content: string
  meta_title: string | null
  meta_description: string | null
  status: string
  status_label: string
  published_at: string | null
  created_at: string | null
  updated_at: string | null
}

export interface ContentPageListResponse {
  data: ContentPage[]
  meta: {
    current_page: number
    last_page: number
    total: number
  }
}

/**
 * Marcador que o conteúdo de uma página pode usar para publicar um número calculado (ver
 * App\Enums\ContentMarker no backend). `marker` é o que se escreve no texto; `value` é o que
 * o site publica hoje, e vem da API — o painel nunca calcula nada disso.
 */
export interface ContentMarker {
  name: string
  marker: string
  label: string
  value: string
}
