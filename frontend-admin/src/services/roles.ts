import { httpClient } from '@/services/http'
import type { RoleOption } from '@/types/users'

export async function fetchRoles(): Promise<RoleOption[]> {
  const { data } = await httpClient.get<{ data: RoleOption[] }>('/api/v1/roles')

  return data.data
}
