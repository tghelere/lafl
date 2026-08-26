/**
 * Compartilhado pelas telas de Atendimento e Bazar (ver docs/estrutura-site.md §4.2) — status
 * é o mesmo enum nas seis entidades de formulário recebido (ver App\Enums\FormSubmissionStatus
 * no backend).
 */
export type StatusOption = { value: string; label: string }

export const STATUS_OPTIONS: StatusOption[] = [
  { value: 'new', label: 'Novo' },
  { value: 'in_progress', label: 'Em andamento' },
  { value: 'done', label: 'Concluído' },
  { value: 'discarded', label: 'Descartado' },
]

export type PaginationMeta = {
  current_page: number
  last_page: number
  total: number
}
