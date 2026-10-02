/** Espelha App\Http\Resources\PartnerResource. */
export type Partner = {
  uuid: string
  name: string
  url: string | null
  position: number
  is_active: boolean
  /** Rota autenticada da API: é por ela que o painel mostra a logo (inclusive de parceiro inativo). */
  logo_url: string
  created_at: string | null
  updated_at: string | null
}

export type PartnerListResponse = {
  data: Partner[]
  meta: {
    current_page: number
    last_page: number
    total: number
  }
}
