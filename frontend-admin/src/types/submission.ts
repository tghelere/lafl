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
  /**
   * Lido por alguém da equipe — não "lido por mim". A leitura é compartilhada (ver
   * docs/decisoes/0021-leitura-separada-do-status-de-atendimento.md).
   */
  is_read: boolean
}

export type SubmissionDetail = SubmissionListItem & {
  internal_note: string | null
  handled_by: string | null
  handled_at: string | null
  /** Leitura compartilhada pela equipe — quem abriu primeiro, e quando. */
  read_at_label: string | null
  read_by: string | null
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
  /** Slug da rota do painel, calculado pela API — nunca por um mapa reverso mantido aqui. */
  resource: string
  unread: number
}
