import { httpClient } from '@/services/http'
import { type MediaDetailsPayload, uploadFormData } from '@/services/media'
import type { Media, PageImages } from '@/types/media'

/**
 * "Imagens desta página" (docs/decisoes/0025-imagens-da-pagina.md). Substituir o arquivo e
 * editar texto alternativo e legenda usam as funções de `media.ts`: arquivo e metadado são da
 * imagem, não da página.
 */
export async function fetchPageImages(pageUuid: string): Promise<PageImages> {
  const { data } = await httpClient.get<{ data: PageImages }>(`/api/v1/pages/${pageUuid}/images`)

  return data.data
}

/** A foto vai para a biblioteca e para o fim da galeria, ou para a capa, no lugar da atual. */
export async function uploadImageToPage(
  pageUuid: string,
  file: File | null,
  payload: MediaDetailsPayload,
  role: 'gallery' | 'cover',
): Promise<Media> {
  const formData = uploadFormData(file, payload)
  formData.append('role', role)

  const { data } = await httpClient.post<{ data: Media }>(`/api/v1/pages/${pageUuid}/images`, formData)

  return data.data
}

/** Uma posição para cima ou para baixo na galeria. Devolve a posição nova (a partir de 1) e o total. */
export async function moveGalleryImage(
  pageUuid: string,
  mediaUuid: string,
  direction: 'up' | 'down',
): Promise<{ position: number; count: number }> {
  const { data } = await httpClient.post<{ data: { position: number; count: number } }>(
    `/api/v1/pages/${pageUuid}/images/${mediaUuid}/move`,
    { direction },
  )

  return data.data
}

/** Troca a capa por uma imagem que já está na biblioteca. */
export async function setPageCover(pageUuid: string, mediaUuid: string): Promise<void> {
  await httpClient.put(`/api/v1/pages/${pageUuid}/images/cover`, { media: mediaUuid })
}

/** Tira da capa ou da galeria. A imagem continua na biblioteca. */
export async function removeImageFromPage(pageUuid: string, mediaUuid: string, role: 'cover' | 'gallery'): Promise<void> {
  await httpClient.delete(`/api/v1/pages/${pageUuid}/images/${mediaUuid}`, { params: { role } })
}
