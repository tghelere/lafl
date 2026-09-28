/**
 * Uma linha da tela de Auditoria (ver App\Http\Resources\FormAuditEntryResource no backend).
 *
 * Não há identificador de linha, de propósito: a tela é só leitura e a chave de `activity_log` é
 * sequencial (ver CLAUDE.md, regra 4). O `:key` da listagem é composto no próprio componente.
 */
export type AuditEntry = {
  occurred_at: string | null
  /** Já em dd/mm/aaaa HH:mm no fuso institucional — o painel não formata data. */
  occurred_at_label: string | null
  /** `null` quando o acontecimento não teve autor autenticado (o formulário veio do site). */
  user: string | null
  event: string | null
  action_label: string
  form_type: string | null
  form_type_label: string | null
  /** `null` quando o registro já foi expurgado por retenção — a entrada do log permanece. */
  record_uuid: string | null
  record_resource: string | null
  ip: string | null
}

export type AuditListResponse = {
  data: AuditEntry[]
  meta: {
    current_page: number
    last_page: number
    total: number
  }
}

export type AuditFilterOption = { value: string; label: string }
