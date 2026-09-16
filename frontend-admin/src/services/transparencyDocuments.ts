import { httpClient } from '@/services/http'
import type { TransparencyDocument, TransparencyDocumentListResponse } from '@/types/transparency'

export type TransparencyDocumentListParams = {
  year?: number
  type?: string
  page?: number
}

/**
 * `GET /api/v1/transparency-documents` filtra e pagina no servidor (App\Http\Controllers\
 * Api\V1\TransparencyDocumentController::index) — mesma semântica do endpoint público
 * (ano/tipo ausentes não filtram, tipo desconhecido é ignorado).
 */
export async function fetchTransparencyDocumentList(
  params: TransparencyDocumentListParams,
): Promise<TransparencyDocumentListResponse> {
  const { data } = await httpClient.get<TransparencyDocumentListResponse>('/api/v1/transparency-documents', {
    params,
  })

  return data
}

export async function fetchTransparencyDocument(uuid: string): Promise<TransparencyDocument> {
  const { data } = await httpClient.get<{ data: TransparencyDocument }>(`/api/v1/transparency-documents/${uuid}`)

  return data.data
}

export type SaveTransparencyDocumentPayload = {
  title: string
  year: number
  type: string
  published: boolean
  file: File | null
}

function toFormData(payload: SaveTransparencyDocumentPayload): FormData {
  const formData = new FormData()
  formData.append('title', payload.title)
  formData.append('year', String(payload.year))
  formData.append('type', payload.type)
  formData.append('published', payload.published ? '1' : '0')

  if (payload.file) {
    formData.append('file', payload.file)
  }

  return formData
}

export async function createTransparencyDocument(
  payload: SaveTransparencyDocumentPayload,
): Promise<TransparencyDocument> {
  const { data } = await httpClient.post<{ data: TransparencyDocument }>(
    '/api/v1/transparency-documents',
    toFormData(payload),
  )

  return data.data
}

/**
 * PUT com corpo multipart não é confiável (PHP só faz parse de multipart em POST) — o padrão
 * do Laravel é POST com `_method=PUT` (method spoofing), que o Controller já aceita porque a
 * rota é `apiResource` (o framework lê `_method` do corpo antes de rotear).
 */
export async function updateTransparencyDocument(
  uuid: string,
  payload: SaveTransparencyDocumentPayload,
): Promise<TransparencyDocument> {
  const formData = toFormData(payload)
  formData.append('_method', 'PUT')

  const { data } = await httpClient.post<{ data: TransparencyDocument }>(
    `/api/v1/transparency-documents/${uuid}`,
    formData,
  )

  return data.data
}

export async function deleteTransparencyDocument(uuid: string): Promise<void> {
  await httpClient.delete(`/api/v1/transparency-documents/${uuid}`)
}
