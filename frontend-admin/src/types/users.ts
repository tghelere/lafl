export type UserAccount = {
  id: string
  name: string
  email: string
  roles: string[]
  active: boolean
  deactivated_at: string | null
  created_at: string | null
}

export type UserAccountListResponse = {
  data: UserAccount[]
  meta: {
    current_page: number
    last_page: number
    total: number
  }
}

/**
 * Espelha GET /api/v1/roles (App\Http\Controllers\Api\V1\RoleController) — nome e descrição
 * vêm só de App\Enums\Role no backend, nunca reescritos aqui.
 */
export type RoleOption = {
  value: string
  label: string
  description: string
}
