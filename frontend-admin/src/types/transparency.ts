/**
 * Rótulos espelham App\Enums\TransparencyDocumentType::label() no backend — mesma disciplina
 * já usada em STATUS_OPTIONS (types/forms.ts): reproduzir texto de rótulo para popular um
 * <select> é interface, não regra de negócio; quem valida o valor é sempre a API.
 */
export type TransparencyDocumentTypeValue =
  | 'balance'
  | 'bylaws'
  | 'minutes'
  | 'certificate'
  | 'agreement_accounting'
  | 'notice'
  | 'annual_report'

export type TransparencyDocumentTypeOption = { value: TransparencyDocumentTypeValue; label: string }

export const TRANSPARENCY_DOCUMENT_TYPE_OPTIONS: TransparencyDocumentTypeOption[] = [
  { value: 'balance', label: 'Balanço' },
  { value: 'bylaws', label: 'Estatuto' },
  { value: 'minutes', label: 'Ata' },
  { value: 'certificate', label: 'Certidão' },
  { value: 'agreement_accounting', label: 'Prestação de contas do convênio' },
  { value: 'notice', label: 'Edital' },
  { value: 'annual_report', label: 'Relatório anual' },
]

export type TransparencyDocument = {
  uuid: string
  title: string
  year: number
  type: TransparencyDocumentTypeValue
  type_label: string
  file_size: number
  download_count: number
  published_at: string | null
  created_at: string | null
  updated_at: string | null
}

export type TransparencyDocumentListResponse = {
  data: TransparencyDocument[]
  meta: {
    current_page: number
    last_page: number
    total: number
  }
}
