import { httpClient } from '@/services/http'
import type { AuditListResponse, MediaAuditListResponse } from '@/types/audit'

export type AuditListParams = {
  /** Valor de App\Enums\FormSubmissionType (ex.: "contact_message"). */
  type?: string
  /** Uuid da conta, nunca id sequencial. */
  user?: string
  /** Uuid de um formulário — usado pela seção "Histórico de acessos" do detalhe. */
  record?: string
  from?: string
  to?: string
  page?: number
  per_page?: number
}

export async function fetchAuditLog(params: AuditListParams): Promise<AuditListResponse> {
  const { data } = await httpClient.get<AuditListResponse>('/api/v1/audit-logs', { params })

  return data
}

export type MediaAuditListParams = {
  /** Valor de App\Enums\MediaAuditEvent (ex.: "marked"). */
  event?: string
  user?: string
  from?: string
  to?: string
  page?: number
}

export async function fetchMediaAuditLog(params: MediaAuditListParams): Promise<MediaAuditListResponse> {
  const { data } = await httpClient.get<MediaAuditListResponse>('/api/v1/audit-logs/media', { params })

  return data
}
