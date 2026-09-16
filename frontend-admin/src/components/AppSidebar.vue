<script setup lang="ts">
import { computed } from 'vue'

import { useAuthStore } from '@/stores/auth'

/**
 * A navegação só mostra o que authStore.user.access permite — o mesmo mapa de viewAny por
 * recurso que a API calcula via Policy (ver App\Http\Resources\UserResource::accessMap no
 * backend), nunca papel fixo decidido aqui. Um usuário sem acesso a nenhum recurso de uma
 * seção não vê o bloco aqui, mas quem realmente barra é sempre a Policy na API — chamar a
 * rota diretamente sem acesso devolve 403 de qualquer forma.
 */
const authStore = useAuthStore()

const access = computed(() => authStore.user?.access ?? {})

function hasAccess(...resources: string[]): boolean {
  return resources.some((resource) => access.value[resource] === true)
}

// Cada bloco aparece se o usuário tem acesso a pelo menos um dos recursos que ele lista —
// cobre sozinho tanto um papel só quanto a soma de dois papéis, sem checar papel nenhum
// diretamente.
const showContraturno = computed(() => hasAccess('program-applications', 'partnership-inquiries'))
const showAtendimento = computed(() => hasAccess('volunteer-applications', 'contact-messages'))
const showBazar = computed(() => hasAccess('pickup-requests'))
const showTransparencia = computed(() => hasAccess('transparency-documents'))
const showConteudo = computed(() => hasAccess('pages'))
const showConfiguracoes = computed(() => hasAccess('users'))

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

      <template v-if="showContraturno">
        <p class="app-sidebar__section-label">
          Contraturno
        </p>
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
      </template>

      <template v-if="showAtendimento">
        <p class="app-sidebar__section-label">
          Atendimento
        </p>
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

      <template v-if="showConteudo">
        <p class="app-sidebar__section-label">
          Conteúdo
        </p>
        <RouterLink
          :to="{ name: 'pages.index' }"
          class="app-sidebar__link"
        >
          Páginas
        </RouterLink>
      </template>

      <template v-if="showTransparencia">
        <p class="app-sidebar__section-label">
          Transparência
        </p>
        <RouterLink
          :to="{ name: 'transparency.index' }"
          class="app-sidebar__link"
        >
          Documentos
        </RouterLink>
      </template>

      <template v-if="showConfiguracoes">
        <p class="app-sidebar__section-label">
          Configurações
        </p>
        <RouterLink
          :to="{ name: 'users.index' }"
          class="app-sidebar__link"
        >
          Usuários
        </RouterLink>
      </template>
    </nav>

    <p class="app-sidebar__note">
      Todo acesso ao detalhe de um formulário é registrado.
    </p>
  </aside>
</template>
