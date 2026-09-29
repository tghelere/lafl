/** Espelha App\Http\Resources\MediaResource. */
export type MediaUsage = {
  uuid: string
  title: string
  slug: string
  status: string
  /** Onde, nessa página: no texto, na capa ou na galeria (App\Actions\Media\FindMediaUsages). */
  places: Array<'content' | 'cover' | 'gallery'>
}

export type Media = {
  id: string
  alt: string
  caption: string | null
  /** Quem fotografou, ou de onde veio. Aparece na ampliação do site, junto da legenda. */
  credit: string | null
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

/** Espelha App\Http\Controllers\Api\V1\PageImageController::index. */
export type PageImages = {
  cover: Media | null
  gallery: Media[]
  /** As do meio do texto, na ordem do conteúdo SALVO. */
  content: Media[]
  /** Onde o site mostra a capa desta página (App\Support\Content\CoverPlacements). Vazio = em lugar nenhum. */
  cover_shown_on: string[]
}
