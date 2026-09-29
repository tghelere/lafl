/**
 * Os acontecimentos da biblioteca de mídia, para o filtro da aba "Imagens" da Auditoria: `value`
 * é o de App\Enums\MediaAuditEvent no backend, `label` o texto do <option>.
 *
 * Duplicado aqui pelo mesmo motivo de FORM_TYPE_OPTIONS (config/formTypes.ts): é a lista de um
 * <select>, que precisa existir antes de qualquer resposta. O rótulo de cada linha vem da API.
 */
import type { AuditFilterOption } from '@/types/audit'

export const MEDIA_AUDIT_EVENT_OPTIONS: AuditFilterOption[] = [
  { value: 'uploaded', label: 'Imagem enviada' },
  { value: 'imported', label: 'Foto inicial importada' },
  { value: 'replaced', label: 'Arquivo substituído' },
  { value: 'updated', label: 'Texto alterado' },
  { value: 'marked', label: 'Marcada como foto de criança ou adolescente atendido' },
  { value: 'deleted', label: 'Imagem excluída' },
  { value: 'placed', label: 'Posta na página' },
  { value: 'removed_from_page', label: 'Tirada da página' },
  { value: 'moved', label: 'Movida na galeria' },
]
