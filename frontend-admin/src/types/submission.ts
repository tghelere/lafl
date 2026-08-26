export type SubmissionListItem = Record<string, unknown> & {
  uuid: string
  status: string
  status_label: string
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
