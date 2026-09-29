import type { RouteParams } from 'vue-router'

/**
 * Metadados de rota que o painel lê fora do roteador — o nome da tela (título da aba) e a
 * seção da navegação lateral.
 *
 * Os dois podem ser texto fixo ou uma função da rota, porque algumas telas são uma só
 * servindo vários recursos: as cinco listagens de formulário compartilham
 * `/admin/:resource`, e o nome da tela e a seção acesa no menu mudam com o parâmetro.
 *
 * O tipo do argumento é o mínimo que essas funções usam (`params`) de propósito: assim o
 * mesmo resolvedor serve tanto ao `RouteLocationNormalized` que os guardas recebem quanto ao
 * `RouteLocationNormalizedLoaded` que `useRoute()` devolve num componente, sem conversão de
 * tipo nem duas assinaturas.
 */
export type RouteMetaText = string | ((route: { params: RouteParams }) => string)

declare module 'vue-router' {
  interface RouteMeta {
    public?: boolean
    /** Nome da tela para o título da aba, no formato "<Tela> · Painel LAF". */
    title?: RouteMetaText
    /**
     * Seção da navegação lateral que esta rota pertence. É o que mantém o item do menu aceso
     * nas rotas filhas (detalhe de registro, edição de página) — comparar a URL não serviria:
     * `/admin/paginas/{uuid}` não é `/admin/paginas`, e um "começa com" acenderia
     * `/admin/transparencia` em `/admin/transparencia-qualquer-coisa`.
     */
    section?: RouteMetaText
  }
}

/**
 * Nomes de seção da navegação lateral que não vêm de um slug de recurso. Ficam aqui, e não
 * soltos como literal em cada lado, porque a tabela de rotas e a navegação lateral precisam
 * usar exatamente o mesmo valor: duas literais iguais em arquivos diferentes é o par que se
 * desencontra na primeira renomeação, e o sintoma seria só um item de menu que não acende.
 *
 * As cinco listagens de formulário não entram aqui: a seção delas é o próprio slug do
 * recurso, que já vem de src/config/submissionResources.ts.
 */
export const SECTION = {
  dashboard: 'dashboard',
  pages: 'pages',
  media: 'media',
  transparency: 'transparency',
  users: 'users',
  audit: 'audit',
} as const

export function resolveRouteMetaText(
  value: RouteMetaText | undefined,
  route: { params: RouteParams },
): string {
  return (typeof value === 'function' ? value(route) : value) ?? ''
}
