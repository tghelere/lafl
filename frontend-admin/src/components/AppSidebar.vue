<script setup lang="ts">
import { computed, onMounted } from 'vue'
import type { Component } from 'vue'
import { useRoute } from 'vue-router'
import type { RouteLocationRaw } from 'vue-router'

import AppIcon from '@/components/AppIcon.vue'
import { DASHBOARD_ICON, RESOURCE_ICONS } from '@/config/icons'
import { SECTION, resolveRouteMetaText } from '@/router/meta'
import { useAuthStore } from '@/stores/auth'
import { useUnreadCountsStore } from '@/stores/unreadCounts'

// Fonte única em shared/brand/ — ver LEIA-ME.md de cada pasta. Import direto (não cópia),
// habilitado por vite.config.ts (server.fs.allow).
import logoHorizontal from '../../../shared/brand/lar-analia-franco/lar-analia-franco-horizontal.svg'
import softhingLogo from '../../../shared/brand/softhing/softhing-fundo-escuro.svg'

/**
 * A navegação só mostra o que authStore.user.access permite — o mesmo mapa de viewAny por
 * recurso que a API calcula via Policy (ver App\Http\Resources\UserResource::accessMap no
 * backend), nunca papel fixo decidido aqui. Um usuário sem acesso a nenhum recurso de uma
 * seção não vê o bloco aqui, mas quem realmente barra é sempre a Policy na API — chamar a
 * rota diretamente sem acesso devolve 403 de qualquer forma.
 */
const authStore = useAuthStore()

/**
 * Contadores de não lidos ao lado de cada seção de formulário (ver
 * docs/decisoes/0021-leitura-separada-do-status-de-atendimento.md). A store é a mesma da tela
 * Início — dois números para a mesma coisa na mesma tela seria pior que nenhum.
 *
 * `ensureLoaded`, e não `refresh`: a navegação lateral remonta a cada troca de tela, e uma
 * chamada por navegação não traria informação nova nenhuma. Quem atualiza o número é quem muda
 * a leitura (ver SubmissionDetailView.vue).
 */
const unreadCounts = useUnreadCountsStore()

onMounted(() => {
  void unreadCounts.ensureLoaded()
})

const route = useRoute()

/**
 * A seção da tela atual vem de `meta.section` da rota (ver src/router/meta.ts), nunca de
 * comparar a URL: `/admin/paginas/{uuid}` não é `/admin/paginas`, e era por isso que o item
 * do menu apagava ao abrir o detalhe de um registro ou a edição de uma página.
 */
const currentSection = computed(() => resolveRouteMetaText(route.meta.section, route))

const access = computed(() => authStore.user?.access ?? {})

type NavItem = {
  label: string
  to: RouteLocationRaw
  section: string
  icon: Component
  /** Recurso cujo contador de não lidos aparece ao lado do rótulo. */
  unread?: string
  /** Quando presente, o item só aparece para quem tem acesso a este recurso. */
  requires?: string
}

type NavGroup = {
  label: string
  /**
   * O bloco aparece se o usuário tem acesso a pelo menos um destes recursos — cobre sozinho
   * tanto um papel só quanto a soma de dois papéis, sem checar papel nenhum diretamente.
   * Vazio quer dizer "todo usuário autenticado vê".
   */
  requires: string[]
  items: NavItem[]
}

function submissionsRoute(resource: string): RouteLocationRaw {
  return { name: 'submissions.index', params: { resource } }
}

/**
 * A seção de um item de formulário é o próprio slug do recurso — o mesmo valor que
 * `meta.section` das rotas /admin/:resource devolve.
 */
function submissionItem(resource: string, label: string): NavItem {
  return {
    label,
    to: submissionsRoute(resource),
    section: resource,
    unread: resource,
    icon: RESOURCE_ICONS[resource]!,
  }
}

const GROUPS: NavGroup[] = [
  {
    label: 'Início',
    requires: [],
    items: [
      { label: 'Pendências', to: { name: 'dashboard' }, section: SECTION.dashboard, icon: DASHBOARD_ICON },
    ],
  },
  {
    label: 'Contraturno',
    requires: ['program-applications', 'partnership-inquiries'],
    items: [
      submissionItem('program-applications', 'Avisos do contraturno'),
      submissionItem('partnership-inquiries', 'Propostas de apoio'),
    ],
  },
  {
    label: 'Atendimento',
    requires: ['volunteer-applications', 'contact-messages'],
    items: [
      submissionItem('volunteer-applications', 'Voluntários'),
      submissionItem('contact-messages', 'Mensagens de contato'),
    ],
  },
  {
    label: 'Bazar',
    requires: ['pickup-requests'],
    items: [submissionItem('pickup-requests', 'Pedidos de coleta')],
  },
  {
    label: 'Conteúdo',
    requires: ['pages'],
    items: [
      {
        label: 'Páginas',
        to: { name: 'pages.index' },
        section: SECTION.pages,
        icon: RESOURCE_ICONS.pages!,
      },
    ],
  },
  {
    label: 'Transparência',
    requires: ['transparency-documents'],
    items: [
      {
        label: 'Documentos',
        to: { name: 'transparency.index' },
        section: SECTION.transparency,
        icon: RESOURCE_ICONS['transparency-documents']!,
      },
    ],
  },
  {
    label: 'Configurações',
    requires: ['users', 'audit-logs'],
    items: [
      {
        label: 'Usuários',
        to: { name: 'users.index' },
        section: SECTION.users,
        requires: 'users',
        icon: RESOURCE_ICONS.users!,
      },
      {
        label: 'Auditoria',
        to: { name: 'audit.index' },
        section: SECTION.audit,
        requires: 'audit-logs',
        icon: RESOURCE_ICONS['audit-logs']!,
      },
    ],
  },
]

function hasAccess(...resources: string[]): boolean {
  return resources.some((resource) => access.value[resource] === true)
}

const groups = computed(() =>
  GROUPS.filter((group) => group.requires.length === 0 || hasAccess(...group.requires)).map(
    (group) => ({
      ...group,
      items: group.items.filter((item) => !item.requires || hasAccess(item.requires)),
    }),
  ),
)

/**
 * Zero não vira badge: um "0" ao lado de cada item é ruído, e a ausência do número já diz que
 * não há nada esperando. `null` é "ainda não carregou".
 */
function unreadOf(resource: string | undefined): number | null {
  if (!resource) {
    return null
  }

  const count = unreadCounts.byResource[resource]

  return count !== undefined && count > 0 ? count : null
}
</script>

<template>
  <aside class="app-sidebar">
    <RouterLink
      :to="{ name: 'dashboard' }"
      class="app-sidebar__brand"
      aria-label="Lar Anália Franco — página inicial do painel"
    >
      <img
        :src="logoHorizontal"
        width="65"
        height="30"
        alt=""
        class="app-sidebar__logo"
      >
    </RouterLink>

    <nav
      class="app-sidebar__nav"
      aria-label="Navegação principal"
    >
      <template
        v-for="group in groups"
        :key="group.label"
      >
        <p class="app-sidebar__section-label">
          {{ group.label }}
        </p>
        <RouterLink
          v-for="item in group.items"
          :key="item.label"
          :to="item.to"
          class="app-sidebar__link"
          :class="{ 'app-sidebar__link--active': currentSection === item.section }"
          :aria-current="currentSection === item.section ? 'page' : undefined"
        >
          <AppIcon :icon="item.icon" />
          <span class="app-sidebar__label">{{ item.label }}</span>
          <span
            v-if="unreadOf(item.unread)"
            class="app-sidebar__badge"
          >{{ unreadOf(item.unread) }} <span class="visually-hidden">não lidos</span></span>
        </RouterLink>
      </template>
    </nav>

    <p class="app-sidebar__credit">
      Desenvolvido por
      <a
        href="https://softhing.com.br/?utm_source=lar-analia-franco&utm_medium=referral&utm_campaign=credito-painel"
        target="_blank"
        rel="noopener"
        aria-label="Softhing — abre o site da desenvolvedora em nova aba"
      >
        <img
          :src="softhingLogo"
          width="73"
          height="20"
          alt=""
          class="app-sidebar__credit-logo"
        >
      </a>
    </p>
  </aside>
</template>
