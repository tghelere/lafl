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
