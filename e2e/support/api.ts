import { type APIRequestContext, type APIResponse, request } from '@playwright/test'

import { ADMIN_URL, API_URL } from './env'
import { type RoleKey, storageStatePath } from './users'

/**
 * Cliente da API administrativa autenticado pelo mesmo cookie de sessão que o painel usa —
 * é o storageState gravado no global setup, reaproveitado aqui.
 *
 * Serve para duas coisas, nunca para substituir o teste pela interface:
 * - montar o estado que um teste precisa (criar a página que ele vai editar), para que cada
 *   teste crie os próprios dados e não dependa da ordem dos outros;
 * - conferir do lado do servidor o que a tela diz ter feito (o `content` gravado depois de
 *   salvar, por exemplo), que é onde a asserção realmente vale.
 *
 * Reproduz à mão o que o axios do painel faz sozinho (ver frontend-admin/src/services/http.ts):
 * `withCredentials` (o storageState já traz o cookie), `withXSRFToken` (lê o cookie
 * XSRF-TOKEN e devolve em X-XSRF-TOKEN) e o cabeçalho `Origin` do painel — sem ele a
 * requisição não é considerada "do frontend" por Sanctum e a sessão nem chega a ser lida
 * (ver SANCTUM_STATEFUL_DOMAINS em backend/.env.e2e).
 */
export class AdminApi {
  private constructor(
    private readonly context: APIRequestContext,
    private readonly xsrfToken: string,
  ) {}

  static async as(role: RoleKey): Promise<AdminApi> {
    const context = await request.newContext({
      baseURL: API_URL,
      storageState: storageStatePath(role),
      extraHTTPHeaders: {
        Accept: 'application/json',
        Origin: ADMIN_URL,
        Referer: `${ADMIN_URL}/`,
      },
    })

    await context.get('/sanctum/csrf-cookie')

    const { cookies } = await context.storageState()
    const cookie = cookies.find((candidate) => candidate.name === 'XSRF-TOKEN')

    if (!cookie) {
      throw new Error('A API não devolveu o cookie XSRF-TOKEN — a pilha de e2e está no ar?')
    }

    // O valor do cookie vem percent-encoded; o cabeçalho precisa do token cru, igual ao que
    // o axios faz no painel.
    return new AdminApi(context, decodeURIComponent(cookie.value))
  }

  get(url: string, params?: Record<string, string | number>): Promise<APIResponse> {
    return this.context.get(url, { params })
  }

  post(url: string, data?: unknown): Promise<APIResponse> {
    return this.send('post', url, data)
  }

  put(url: string, data?: unknown): Promise<APIResponse> {
    return this.send('put', url, data)
  }

  patch(url: string, data?: unknown): Promise<APIResponse> {
    return this.send('patch', url, data)
  }

  delete(url: string): Promise<APIResponse> {
    return this.send('delete', url)
  }

  async dispose(): Promise<void> {
    await this.context.dispose()
  }

  private send(method: 'post' | 'put' | 'patch' | 'delete', url: string, data?: unknown): Promise<APIResponse> {
    return this.context[method](url, {
      headers: { 'X-XSRF-TOKEN': this.xsrfToken },
      ...(data === undefined ? {} : { data }),
    })
  }
}

/**
 * Lê uma página pelo endpoint administrativo. É a fonte de verdade das asserções sobre
 * `content` — o que o editor mostra na tela já passou pelo Tiptap, então comparar HTML pela
 * interface compararia a normalização do editor, não o que ficou gravado.
 */
export async function fetchPageContent(api: AdminApi, uuid: string): Promise<string> {
  const response = await api.get(`/api/v1/pages/${uuid}`)

  if (!response.ok()) {
    throw new Error(`GET /api/v1/pages/${uuid} respondeu ${response.status()}`)
  }

  const body = (await response.json()) as { data: { content: string } }

  return body.data.content
}

/**
 * O valor que um marcador de conteúdo tem AGORA, pela mesma fonte que o painel mostra a quem
 * escreve (ver App\Actions\Content\ListContentMarkers).
 *
 * É o que impede a asserção tautológica: o teste não recalcula a idade nem conta documento em
 * TypeScript — regra de negócio nenhuma vive aqui —, pergunta à API e depois cobra que o site
 * publique exatamente esse texto.
 */
export async function markerValue(api: AdminApi, name: string): Promise<string> {
  const response = await api.get('/api/v1/content-markers')

  if (!response.ok()) {
    throw new Error(`GET /api/v1/content-markers respondeu ${response.status()}`)
  }

  const body = (await response.json()) as { data: Array<{ name: string; value: string }> }
  const marker = body.data.find((candidate) => candidate.name === name)

  if (!marker) {
    throw new Error(`a API não conhece o marcador "${name}"`)
  }

  return marker.value
}
