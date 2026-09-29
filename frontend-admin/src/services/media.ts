import { apiUrl } from '@/config'
import { httpClient } from '@/services/http'
import type { Media, MediaListResponse } from '@/types/media'

export type MediaListParams = {
  search?: string
  page?: number
  per_page?: number
  /** Só o que pode ir para o site — o seletor de imagem do editor. */
  publishable?: boolean
}

export async function fetchMediaList(params: MediaListParams): Promise<MediaListResponse> {
  const { data } = await httpClient.get<MediaListResponse>('/api/v1/media', {
    params: { ...params, publishable: params.publishable ? 1 : undefined },
  })

  return data
}

export async function fetchMedia(uuid: string): Promise<Media> {
  const { data } = await httpClient.get<{ data: Media }>(`/api/v1/media/${uuid}`)

  return data.data
}

export type MediaDetailsPayload = {
  alt: string
  caption: string
  /** `null` = a pessoa ainda não respondeu. A API exige a resposta; aqui ela vai como está. */
  depicts_assisted_minor: boolean | null
}

function appendDetails(formData: FormData, payload: MediaDetailsPayload): void {
  formData.append('alt', payload.alt)
  formData.append('caption', payload.caption)

  if (payload.depicts_assisted_minor !== null) {
    formData.append('depicts_assisted_minor', payload.depicts_assisted_minor ? '1' : '0')
  }
}

export async function uploadMedia(file: File | null, payload: MediaDetailsPayload): Promise<Media> {
  const formData = new FormData()

  if (file) {
    formData.append('file', file)
  }

  appendDetails(formData, payload)

  const { data } = await httpClient.post<{ data: Media }>('/api/v1/media', formData)

  return data.data
}

export async function updateMediaDetails(uuid: string, payload: MediaDetailsPayload): Promise<Media> {
  const { data } = await httpClient.put<{ data: Media }>(`/api/v1/media/${uuid}`, {
    alt: payload.alt,
    caption: payload.caption || null,
    depicts_assisted_minor: payload.depicts_assisted_minor,
  })

  return data.data
}

/** POST, não PUT: o PHP só lê corpo multipart em POST (mesmo motivo de transparencyDocuments.ts). */
export async function replaceMediaFile(uuid: string, file: File): Promise<Media> {
  const formData = new FormData()
  formData.append('file', file)

  const { data } = await httpClient.post<{ data: Media }>(`/api/v1/media/${uuid}/file`, formData)

  return data.data
}

export async function deleteMedia(uuid: string): Promise<void> {
  await httpClient.delete(`/api/v1/media/${uuid}`)
}

/**
 * Endereço de pré-visualização no painel a partir só do uuid — para a imagem já inserida no
 * editor, que no conteúdo só guarda `/midia/{uuid}`. Rota autenticada da API, a mesma de
 * `preview_url`; o `<img>` leva o cookie de sessão porque painel e API estão sob o mesmo
 * domínio raiz (docs/decisoes/0003-sanctum-cookie-mode.md).
 */
export function mediaPreviewUrl(uuid: string, width = 640): string {
  return `${apiUrl}/api/v1/media/${uuid}/file/${width}.webp`
}
