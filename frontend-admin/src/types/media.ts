/** Espelha App\Http\Resources\MediaResource. */
export type MediaUsage = {
  uuid: string
  title: string
  slug: string
  status: string
}

export type Media = {
  id: string
  alt: string
  caption: string | null
  depicts_assisted_minor: boolean
  /** Pode ir para o site. Decidido pela API (App\Models\Media::isPublishable). */
  publishable: boolean
  /** O `src` que o editor grava na página — `null` quando a imagem não pode ir para o site. */
  src: string | null
  /** Rota autenticada da API: é por ela que o painel mostra a imagem. */
  preview_url: string
  mime: string
  size: number
  width: number
  height: number
  widths: number[]
  version: number
  /** Só no detalhe (GET /media/{id}). */
  usages?: MediaUsage[]
  can: { update: boolean; delete: boolean }
  created_at: string | null
  updated_at: string | null
}

export type MediaListResponse = {
  data: Media[]
  meta: {
    current_page: number
    last_page: number
    total: number
  }
}
