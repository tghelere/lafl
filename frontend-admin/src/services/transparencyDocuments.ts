import { httpClient } from '@/services/http'
import type { TransparencyDocument, TransparencyDocumentListResponse } from '@/types/transparency'

/**
 * `GET /api/v1/transparency-documents` (App\Http\Controllers\Api\V1\TransparencyDocumentController::index)
 * não aceita filtro por ano/tipo — só pagina o total, ordenado por `updated_at`. Filtrar aqui
 * não é um bug do endpoint (ele nunca prometeu filtro), então não alteramos o backend por
 * isso: buscamos até o teto de `per_page` que o Controller aceita (100 — `min($request->
 * integer('per_page', 15), 100)`) numa chamada só e filtramos/paginamos no cliente. O acervo
 * de exemplo tem 12 documentos hoje; o real está estimado em ~70 (ver docs/roadmap.md,
 * seção de cache de transparência) — ainda cabe. Se o acervo passar de 100 documentos, isto
 * para de trazer a lista inteira e o filtro por ano/tipo no painel passa a operar só sobre a
 * primeira página; nesse ponto o Controller precisa aprender `year`/`type` como o Action
 * público (ListPublicTransparencyDocuments) já faz.
 */
export async function fetchTransparencyDocumentList(): Promise<TransparencyDocumentListResponse> {
  const { data } = await httpClient.get<TransparencyDocumentListResponse>('/api/v1/transparency-documents', {
    params: { per_page: 100 },
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
