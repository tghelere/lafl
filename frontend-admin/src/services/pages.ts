import { httpClient } from '@/services/http'
import type { ContentPage, ContentPageListResponse } from '@/types/pages'

export type ContentPageListParams = {
  search?: string
  page?: number
}

export async function fetchContentPageList(
  params: ContentPageListParams,
): Promise<ContentPageListResponse> {
  const { data } = await httpClient.get<ContentPageListResponse>('/api/v1/pages', { params })

  return data
}

export async function fetchContentPage(uuid: string): Promise<ContentPage> {
  const { data } = await httpClient.get<{ data: ContentPage }>(`/api/v1/pages/${uuid}`)

  return data.data
}

/**
 * `slug` e `status` vão no corpo porque a API exige os dois campos, mas esta fatia do painel
 * não os edita: são reenviados exatamente como vieram do servidor. Para quem não tem o papel
 * `direcao` o backend ignora esses dois campos de qualquer forma (ver
 * App\Http\Requests\Content\UpdatePageRequest::prepareForValidation) — a tela não é o que
 * garante o recorte, é só coerente com ele.
 */
export type SaveContentPagePayload = {
  slug: string
  title: string
  content: string
  meta_title: string | null
  meta_description: string | null
  status: string
}

export async function updateContentPage(
  uuid: string,
  payload: SaveContentPagePayload,
): Promise<ContentPage> {
  const { data } = await httpClient.put<{ data: ContentPage }>(`/api/v1/pages/${uuid}`, payload)

  return data.data
}
