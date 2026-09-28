import { httpClient } from '@/services/http'
import type { SubmissionDetail, SubmissionListResponse } from '@/types/submission'

export type SubmissionListParams = {
  status?: string
  read?: string
  from?: string
  to?: string
  page?: number
}

/**
 * Camada única de acesso HTTP para os cinco formulários recebidos (ver docs/convencoes.md) —
 * genérica por `resource` (o slug da rota, ex.: "program-applications") porque a API já segue
 * o mesmo padrão para os cinco (ver docs/estrutura-site.md §4.5).
 */
export async function fetchSubmissionList(
  resource: string,
  params: SubmissionListParams,
): Promise<SubmissionListResponse> {
  const { data } = await httpClient.get<SubmissionListResponse>(`/api/v1/${resource}`, { params })

  return data
}

export async function fetchSubmissionDetail(resource: string, uuid: string): Promise<SubmissionDetail> {
  const { data } = await httpClient.get<{ data: SubmissionDetail }>(`/api/v1/${resource}/${uuid}`)

  return data.data
}

/**
 * Devolve o registro ao estado "não lido", para a equipe inteira. Não existe o contrário: abrir
 * o detalhe já marca como lido (ver
 * docs/decisoes/0021-leitura-separada-do-status-de-atendimento.md), por isso um DELETE sobre
 * `/read` e nenhum POST.
 */
export async function markSubmissionUnread(resource: string, uuid: string): Promise<SubmissionDetail> {
  const { data } = await httpClient.delete<{ data: SubmissionDetail }>(`/api/v1/${resource}/${uuid}/read`)

  return data.data
}

export async function updateSubmissionStatus(
  resource: string,
  uuid: string,
  payload: { status: string; internal_note: string | null },
): Promise<SubmissionDetail> {
  const { data } = await httpClient.patch<{ data: SubmissionDetail }>(
    `/api/v1/${resource}/${uuid}/status`,
    payload,
  )

  return data.data
}
