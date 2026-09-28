export type SubmissionListItem = Record<string, unknown> & {
  uuid: string
  status: string
  status_label: string
  /**
   * "Recebido em" já formatado (dd/mm/aaaa HH:mm) no fuso institucional pela API — ver
   * App\Support\InstitutionalTime no backend. O painel nunca converte fuso: o servidor fica
   * nos Estados Unidos e o navegador de quem usa o painel pode estar em qualquer lugar, então
   * quem decide o fuso de exibição é a API, uma vez, para todo mundo.
   */
  created_at_label: string | null
}

export type SubmissionDetail = SubmissionListItem & {
  internal_note: string | null
  handled_by: string | null
  handled_at: string | null
}

export type SubmissionListResponse = {
  data: SubmissionListItem[]
  meta: {
    current_page: number
    last_page: number
    total: number
  }
}

export type DashboardEntry = {
  type: string
  label: string
  pending: number
}
