import {
  Ban,
  CircleCheck,
  CircleDashed,
  CircleMinus,
  ClipboardList,
  Clock,
  FileText,
  Globe,
  Handshake,
  HeartHandshake,
  LayoutDashboard,
  Mail,
  PencilLine,
  ScrollText,
  ShieldCheck,
  Truck,
  Users,
} from 'lucide-vue-next'
import type { Component } from 'vue'

/**
 * Ícone de cada recurso e de cada estado. Um mapa só, importado por quem precisa, porque as
 * MESMAS coisas aparecem em lugares diferentes: o recurso "Mensagens de contato" tem ícone na
 * navegação lateral e no card da tela Início, e os dois têm de ser o mesmo desenho — senão a
 * pessoa aprende dois símbolos para uma coisa.
 *
 * Só os ícones usados são importados, um a um: o pacote do lucide tem mais de mil, e um
 * `import * as icons` levaria todos para dentro do bundle.
 */
export const RESOURCE_ICONS: Record<string, Component> = {
  'program-applications': ClipboardList,
  'partnership-inquiries': Handshake,
  'volunteer-applications': HeartHandshake,
  'contact-messages': Mail,
  'pickup-requests': Truck,
  pages: FileText,
  'transparency-documents': ScrollText,
  users: Users,
  'audit-logs': ShieldCheck,
}

/** Ícone da tela Início, que não é um recurso do mapa de acesso. */
export const DASHBOARD_ICON: Component = LayoutDashboard

/**
 * Ícone de cada valor de selo (ver src/components/StatusBadge.vue). São três famílias no mesmo
 * mapa porque o componente de selo é um só: status de atendimento de formulário
 * (new/in_progress/done/discarded), leitura (unread), e publicação/ativação
 * (published/draft/active/inactive).
 */
export const STATUS_ICONS: Record<string, Component> = {
  new: CircleDashed,
  in_progress: Clock,
  done: CircleCheck,
  discarded: Ban,
  unread: Mail,
  published: Globe,
  draft: PencilLine,
  active: CircleCheck,
  inactive: CircleMinus,
}
