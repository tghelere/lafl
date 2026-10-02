import { httpClient } from '@/services/http'
import type { Partner, PartnerListResponse } from '@/types/partners'

export async function fetchPartnerList(page: number): Promise<PartnerListResponse> {
  const { data } = await httpClient.get<PartnerListResponse>('/api/v1/partners', { params: { page } })

  return data
}

export async function fetchPartner(uuid: string): Promise<Partner> {
  const { data } = await httpClient.get<{ data: Partner }>(`/api/v1/partners/${uuid}`)

  return data.data
}

export type SavePartnerPayload = {
  name: string
  url: string
  /** Vazio = ao criar, vai para o fim; ao editar, mantém. */
  position: string
  isActive: boolean
  logo: File | null
}

function toFormData(payload: SavePartnerPayload): FormData {
  const formData = new FormData()
  formData.append('name', payload.name)
  formData.append('url', payload.url)
  formData.append('position', payload.position)
  formData.append('is_active', payload.isActive ? '1' : '0')

  if (payload.logo) {
    formData.append('logo', payload.logo)
  }

  return formData
}

export async function createPartner(payload: SavePartnerPayload): Promise<Partner> {
  const { data } = await httpClient.post<{ data: Partner }>('/api/v1/partners', toFormData(payload))

  return data.data
}

/** POST com `_method=PUT`: multipart só é lido pelo PHP em POST (mesmo desenho de transparência). */
export async function updatePartner(uuid: string, payload: SavePartnerPayload): Promise<Partner> {
  const formData = toFormData(payload)
  formData.append('_method', 'PUT')

  const { data } = await httpClient.post<{ data: Partner }>(`/api/v1/partners/${uuid}`, formData)

  return data.data
}

export async function deletePartner(uuid: string): Promise<void> {
  await httpClient.delete(`/api/v1/partners/${uuid}`)
}
