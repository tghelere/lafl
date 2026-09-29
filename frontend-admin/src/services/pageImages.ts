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

/** A foto vai para a biblioteca e para o fim da galeria da página. */
export async function uploadImageToPage(pageUuid: string, file: File | null, payload: MediaDetailsPayload): Promise<Media> {
  const { data } = await httpClient.post<{ data: Media }>(`/api/v1/pages/${pageUuid}/images`, uploadFormData(file, payload))

  return data.data
}

/** Tira da capa ou da galeria. A imagem continua na biblioteca. */
export async function removeImageFromPage(pageUuid: string, mediaUuid: string, role: 'cover' | 'gallery'): Promise<void> {
  await httpClient.delete(`/api/v1/pages/${pageUuid}/images/${mediaUuid}`, { params: { role } })
}
