/**
 * Compartilhado pelas telas de Atendimento e Bazar (ver docs/estrutura-site.md §4.2) — status
 * é o mesmo enum nas cinco entidades de formulário recebido (ver App\Enums\FormSubmissionStatus
 * no backend).
 *
 * Os rótulos estão duplicados aqui e no enum PHP por um motivo só: são as opções de um <select>,
 * que precisa existir antes de qualquer resposta da API chegar. Todo rótulo que acompanha um
 * registro vem da API (`status_label`), nunca daqui.
 */
export type StatusOption = { value: string; label: string }

export const STATUS_OPTIONS: StatusOption[] = [
  { value: 'in_progress', label: 'Em atendimento' },
  { value: 'done', label: 'Concluído' },
  { value: 'archived', label: 'Arquivado' },
]

/**
 * Leitura, separada do status de atendimento — ver
 * docs/decisoes/0021-leitura-separada-do-status-de-atendimento.md. Os valores são os que a API
 * aceita em `?read=` (ver App\Http\Requests\Forms\IndexFormSubmissionsRequest).
 */
export const READ_OPTIONS: StatusOption[] = [
  { value: 'unread', label: 'Não lidos' },
  { value: 'read', label: 'Lidos' },
]

export type PaginationMeta = {
  current_page: number
  last_page: number
  total: number
}
