<script setup lang="ts">
import { computed } from 'vue'

import { useAuthStore } from '@/stores/auth'

/**
 * A navegação só mostra o que o papel acessa — conveniência de interface, nunca a fonte da
 * verdade da autorização (ver CLAUDE.md e docs/estrutura-site.md §4.4). Um usuário sem papel
 * para uma seção não vê o link aqui, mas quem realmente barra é sempre a Policy na API —
 * chamar a rota diretamente sem o papel devolve 403 de qualquer forma.
 */
const authStore = useAuthStore()

const roles = computed(() => authStore.user?.roles ?? [])

function hasAnyRole(...names: string[]): boolean {
  return roles.value.some((role) => names.includes(role))
}

const showAtendimento = computed(() => hasAnyRole('atendimento', 'direcao', 'super_admin'))
const showBazar = computed(() => hasAnyRole('bazar', 'direcao', 'super_admin'))

function resourceRoute(resource: string): { name: string; params: Record<string, string> } {
  return { name: 'submissions.index', params: { resource } }
}
</script>

<template>
  <aside class="app-sidebar">
    <RouterLink
      to="/admin"
      class="app-sidebar__brand"
    >
      Lar Anália Franco
    </RouterLink>

    <nav
      class="app-sidebar__nav"
      aria-label="Navegação principal"
    >
      <p class="app-sidebar__section-label">
        Início
      </p>
      <RouterLink
        to="/admin"
        class="app-sidebar__link"
      >
        Pendências
      </RouterLink>

      <template v-if="showAtendimento">
        <p class="app-sidebar__section-label">
          Atendimento
        </p>
        <RouterLink
          :to="resourceRoute('enrollment-interests')"
          class="app-sidebar__link"
        >
          Interesses de matrícula
        </RouterLink>
        <RouterLink
          :to="resourceRoute('program-applications')"
          class="app-sidebar__link"
        >
          Avisos do contraturno
        </RouterLink>
        <RouterLink
          :to="resourceRoute('partnership-inquiries')"
          class="app-sidebar__link"
        >
          Propostas de apoio
        </RouterLink>
        <RouterLink
          :to="resourceRoute('volunteer-applications')"
          class="app-sidebar__link"
        >
          Voluntários
        </RouterLink>
        <RouterLink
          :to="resourceRoute('contact-messages')"
          class="app-sidebar__link"
        >
          Mensagens de contato
        </RouterLink>
      </template>

      <template v-if="showBazar">
        <p class="app-sidebar__section-label">
          Bazar
        </p>
        <RouterLink
          :to="resourceRoute('pickup-requests')"
          class="app-sidebar__link"
        >
          Pedidos de coleta
        </RouterLink>
      </template>
    </nav>

    <p class="app-sidebar__note">
      Todo acesso ao detalhe de um formulário é registrado.
    </p>
  </aside>
</template>
