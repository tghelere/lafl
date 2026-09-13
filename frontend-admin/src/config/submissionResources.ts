/**
 * Um registro por entidade de formulário recebido (ver docs/dominio.md) — o que muda entre as
 * seis telas de listagem/detalhe é só isto: quais colunas mostrar e com qual rótulo. A tela
 * em si (SubmissionListView/SubmissionDetailView) é uma só, genérica, para não repetir a
 * mesma estrutura de tabela e grade de detalhe seis vezes.
 *
 * `key` corresponde ao campo devolvido pelos Resources administrativos do backend (ver
 * App\Http\Resources\*ListResource / *Resource) — os `_label` são o texto em português já
 * pronto, os campos sem `_label` são exibidos como vêm (data, número, texto curto).
 */
export type FieldDef = {
  key: string
  label: string
}

export type SubmissionResourceConfig = {
  slug: string
  title: string
  /** Colunas da tabela de listagem, na ordem em que aparecem. */
  listColumns: FieldDef[]
  /** Campos da grade de detalhe, na ordem em que aparecem — sempre sem máscara. */
  detailFields: FieldDef[]
}

export const SUBMISSION_RESOURCES: Record<string, SubmissionResourceConfig> = {
  'enrollment-interests': {
    slug: 'enrollment-interests',
    title: 'Interesses de matrícula',
    listColumns: [
      { key: 'guardian_name', label: 'Responsável' },
      { key: 'phone', label: 'Telefone' },
      { key: 'email', label: 'E-mail' },
      { key: 'child_age_range_label', label: 'Faixa etária' },
      { key: 'desired_period_label', label: 'Período' },
    ],
    detailFields: [
      { key: 'guardian_name', label: 'Responsável' },
      { key: 'phone', label: 'Telefone' },
      { key: 'email', label: 'E-mail' },
      { key: 'child_age_range_label', label: 'Faixa etária da criança' },
      { key: 'desired_period_label', label: 'Período pretendido' },
      { key: 'message', label: 'Mensagem' },
    ],
  },
  'program-applications': {
    slug: 'program-applications',
    title: 'Avisos de interesse no contraturno',
    listColumns: [
      { key: 'guardian_name', label: 'Responsável' },
      { key: 'phone', label: 'Telefone' },
    ],
    detailFields: [
      { key: 'guardian_name', label: 'Responsável' },
      { key: 'phone', label: 'Telefone' },
    ],
  },
  'pickup-requests': {
    slug: 'pickup-requests',
    title: 'Pedidos de coleta',
    listColumns: [
      { key: 'donor_name', label: 'Doador' },
      { key: 'phone', label: 'Telefone' },
      { key: 'items_description', label: 'Itens' },
      { key: 'availability_window', label: 'Disponibilidade' },
      { key: 'scheduled_for', label: 'Agendado para' },
    ],
    detailFields: [
      { key: 'donor_name', label: 'Doador' },
      { key: 'phone', label: 'Telefone' },
      // Endereço só aparece aqui, nunca na listagem (ver App\Http\Resources\
      // PickupRequestListResource no backend) — é o dado mais sensível desta fase.
      { key: 'address', label: 'Endereço para a coleta' },
      { key: 'items_description', label: 'Itens a doar' },
      { key: 'availability_window', label: 'Disponibilidade' },
      { key: 'scheduled_for', label: 'Agendado para' },
    ],
  },
  'volunteer-applications': {
    slug: 'volunteer-applications',
    title: 'Voluntários',
    listColumns: [
      { key: 'name', label: 'Nome' },
      { key: 'phone', label: 'Telefone' },
      { key: 'email', label: 'E-mail' },
      { key: 'availability', label: 'Disponibilidade' },
      { key: 'interest_area', label: 'Área de interesse' },
    ],
    detailFields: [
      { key: 'name', label: 'Nome' },
      { key: 'phone', label: 'Telefone' },
      { key: 'email', label: 'E-mail' },
      { key: 'availability', label: 'Disponibilidade' },
      { key: 'interest_area', label: 'Área de interesse' },
      { key: 'message', label: 'Mensagem' },
    ],
  },
  'partnership-inquiries': {
    slug: 'partnership-inquiries',
    title: 'Propostas de apoio',
    listColumns: [
      { key: 'company_name', label: 'Empresa' },
      { key: 'tax_id', label: 'CNPJ' },
      { key: 'contact_name', label: 'Contato' },
      { key: 'phone', label: 'Telefone' },
      { key: 'support_type_label', label: 'Tipo de apoio' },
    ],
    detailFields: [
      { key: 'company_name', label: 'Empresa' },
      { key: 'tax_id', label: 'CNPJ' },
      { key: 'contact_name', label: 'Contato' },
      { key: 'phone', label: 'Telefone' },
      { key: 'email', label: 'E-mail' },
      { key: 'support_type_label', label: 'Tipo de apoio' },
      { key: 'message', label: 'Mensagem' },
    ],
  },
  'contact-messages': {
    slug: 'contact-messages',
    title: 'Mensagens de contato',
    listColumns: [
      { key: 'name', label: 'Nome' },
      { key: 'email', label: 'E-mail' },
      { key: 'subject', label: 'Assunto' },
    ],
    detailFields: [
      { key: 'name', label: 'Nome' },
      { key: 'email', label: 'E-mail' },
      { key: 'subject', label: 'Assunto' },
      { key: 'message', label: 'Mensagem' },
    ],
  },
}
