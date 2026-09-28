import type { RouteLocationRaw } from 'vue-router'

/**
 * Um degrau da trilha de navegação (ver src/components/AppBreadcrumb.vue).
 *
 * `loading` existe porque o degrau do meio quase sempre é o NOME DE UM REGISTRO, que só chega
 * depois da resposta da API. Sem ele a trilha nascia com um rótulo vazio entre duas barras —
 * era exatamente esse o "Páginas / / Editar página" que esta tarefa veio corrigir.
 */
export type BreadcrumbItem = {
  label: string
  /** Sem `to`, o degrau é texto — o último nunca é link. */
  to?: RouteLocationRaw
  /** Enquanto verdadeiro, o degrau aparece como esqueleto no lugar do rótulo. */
  loading?: boolean
}
