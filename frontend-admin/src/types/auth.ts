export interface User {
  id: string
  name: string
  email: string
  roles: string[]
  two_factor_enabled: boolean
  /**
   * viewAny por recurso administrativo (ver App\Http\Resources\UserResource::accessMap no
   * backend) — chave é o mesmo slug kebab-case usado nas rotas (ex.: "transparency-documents",
   * "pickup-requests"). Única fonte usada pelo painel para decidir o que mostrar no menu e
   * nas telas — nunca papel fixo (ver src/components/AppSidebar.vue).
   */
  access: Record<string, boolean>
}
