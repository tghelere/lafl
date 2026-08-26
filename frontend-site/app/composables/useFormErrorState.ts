/**
 * Lê o estado de erro que server/api/forms/[tipo].post.ts devolve via redirect 303 depois de
 * uma validação falhar — nunca o valor digitado, só quais campos falharam (nome de campo não
 * é dado pessoal; o valor em si nunca entra na URL, ver o proxy). Funciona sem JavaScript
 * porque é lido a partir da query string já presente na navegação, renderizado no servidor.
 */
export function useFormErrorState() {
  const route = useRoute()

  const hasError = computed(() => route.query.erro === '1')

  const failedFields = computed<string[]>(() => {
    const raw = route.query.campos
    if (typeof raw !== 'string' || raw === '') return []
    return raw.split(',')
  })

  function fieldFailed(field: string): boolean {
    return failedFields.value.includes(field)
  }

  return { hasError, failedFields, fieldFailed }
}
