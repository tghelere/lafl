import type { APIResponse } from '@playwright/test'

import { AdminApi } from './api'

export type CreatedUser = { id: string; name: string; email: string; roles: string[] }

export type CreatedPage = { id: string; slug: string; title: string; content: string }

function ok(response: APIResponse, what: string): APIResponse {
  if (!response.ok()) {
    throw new Error(`${what} respondeu ${response.status()}`)
  }

  return response
}

/**
 * Cria pela API a página que um teste vai editar pela interface. Criar página é ação de
 * `direcao`/`super_admin` e o painel não oferece a tela (ver App\Policies\PagePolicy) — a API
 * é o caminho honesto para montar o cenário, em vez de um teste depender do conteúdo que
 * outro teste deixou para trás.
 */
export async function createPage(
  api: AdminApi,
  data: { slug: string; title: string; content: string; status?: 'draft' | 'published' },
): Promise<CreatedPage> {
  const response = ok(
    await api.post('/api/v1/pages', {
      slug: data.slug,
      title: data.title,
      content: data.content,
      meta_title: null,
      meta_description: null,
      status: data.status ?? 'published',
    }),
    'POST /api/v1/pages',
  )

  const body = (await response.json()) as { data: CreatedPage }

  return body.data
}

export async function deletePage(api: AdminApi, uuid: string): Promise<void> {
  await api.delete(`/api/v1/pages/${uuid}`)
}

export async function createUser(
  api: AdminApi,
  data: { name: string; email: string; roles: string[] },
): Promise<CreatedUser> {
  const response = ok(await api.post('/api/v1/users', data), 'POST /api/v1/users')
  const body = (await response.json()) as { data: CreatedUser }

  return body.data
}

/** A URL de /definir-senha só existe nesta resposta — ver App\Actions\Users\GeneratePasswordLink. */
export async function generatePasswordLink(api: AdminApi, uuid: string): Promise<string> {
  const response = ok(
    await api.post(`/api/v1/users/${uuid}/password-link`),
    `POST /api/v1/users/${uuid}/password-link`,
  )
  const body = (await response.json()) as { data: { url: string } }

  return body.data.url
}

/** uuid da conta de quem está autenticado neste cliente. */
export async function currentUserId(api: AdminApi): Promise<string> {
  const response = ok(await api.get('/api/v1/auth/user'), 'GET /api/v1/auth/user')
  const body = (await response.json()) as { data: { id: string } }

  return body.data.id
}
