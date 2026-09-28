/**
 * Os cinco tipos de formulário recebido, no formato que o filtro da tela de Auditoria precisa:
 * `value` é o valor de App\Enums\FormSubmissionType no backend, `label` é o texto do <option>.
 *
 * Duplicado aqui e no enum PHP pelo mesmo motivo de STATUS_OPTIONS (ver src/types/forms.ts): é a
 * lista de um <select>, que precisa existir antes de qualquer resposta da API. Todo rótulo que
 * acompanha um registro continua vindo da API (`form_type_label`).
 */
import type { AuditFilterOption } from '@/types/audit'

export const FORM_TYPE_OPTIONS: AuditFilterOption[] = [
  { value: 'program_application', label: 'Aviso de interesse no contraturno' },
  { value: 'pickup_request', label: 'Agendamento de coleta' },
  { value: 'volunteer_application', label: 'Candidatura de voluntariado' },
  { value: 'partnership_inquiry', label: 'Proposta de parceria' },
  { value: 'contact_message', label: 'Mensagem de contato' },
]
