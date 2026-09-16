import { httpClient } from '@/services/http'
import type { UserAccount, UserAccountListResponse } from '@/types/users'

export type UserListParams = {
  search?: string
  status?: 'active' | 'inactive'
  page?: number
}

export async function fetchUserList(params: UserListParams): Promise<UserAccountListResponse> {
  const { data } = await httpClient.get<UserAccountListResponse>('/api/v1/users', { params })

  return data
}

export async function fetchUser(uuid: string): Promise<UserAccount> {
  const { data } = await httpClient.get<{ data: UserAccount }>(`/api/v1/users/${uuid}`)

  return data.data
}

export type SaveUserPayload = {
  name: string
  email: string
  roles: string[]
}

export async function createUser(payload: SaveUserPayload): Promise<UserAccount> {
  const { data } = await httpClient.post<{ data: UserAccount }>('/api/v1/users', payload)

  return data.data
}

export async function updateUser(uuid: string, payload: SaveUserPayload): Promise<UserAccount> {
  const { data } = await httpClient.put<{ data: UserAccount }>(`/api/v1/users/${uuid}`, payload)

  return data.data
}

export async function deactivateUser(uuid: string): Promise<UserAccount> {
  const { data } = await httpClient.patch<{ data: UserAccount }>(`/api/v1/users/${uuid}/deactivate`)

  return data.data
}

export async function reactivateUser(uuid: string): Promise<UserAccount> {
  const { data } = await httpClient.patch<{ data: UserAccount }>(`/api/v1/users/${uuid}/reactivate`)

  return data.data
}

/**
 * A URL só existe nesta resposta — ver App\Actions\Users\GeneratePasswordLink no backend.
 * Chamar de novo invalida a anterior, mesmo que ainda não usada.
 */
export async function generateUserPasswordLink(uuid: string): Promise<string> {
  const { data } = await httpClient.post<{ data: { url: string } }>(`/api/v1/users/${uuid}/password-link`)

  return data.data.url
}
