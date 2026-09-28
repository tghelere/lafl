<script setup lang="ts">
import { computed, onMounted } from 'vue'

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

const access = computed(() => authStore.user?.access ?? {})

/**
 * Zero não vira badge: um "0" ao lado de cada item é ruído, e a ausência do número já diz que
 * não há nada esperando. `null` é "ainda não carregou".
 */
function unreadOf(resource: string): number | null {
  const count = unreadCounts.byResource[resource]

  return count !== undefined && count > 0 ? count : null
}

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
          <span
            v-if="unreadOf('program-applications')"
            class="app-sidebar__badge"
          >{{ unreadOf('program-applications') }} <span class="visually-hidden">não lidos</span></span>
        </RouterLink>
        <RouterLink
          :to="resourceRoute('partnership-inquiries')"
          class="app-sidebar__link"
        >
          Propostas de apoio
          <span
            v-if="unreadOf('partnership-inquiries')"
            class="app-sidebar__badge"
          >{{ unreadOf('partnership-inquiries') }} <span class="visually-hidden">não lidos</span></span>
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
          <span
            v-if="unreadOf('volunteer-applications')"
            class="app-sidebar__badge"
          >{{ unreadOf('volunteer-applications') }} <span class="visually-hidden">não lidos</span></span>
        </RouterLink>
        <RouterLink
          :to="resourceRoute('contact-messages')"
          class="app-sidebar__link"
        >
          Mensagens de contato
          <span
            v-if="unreadOf('contact-messages')"
            class="app-sidebar__badge"
          >{{ unreadOf('contact-messages') }} <span class="visually-hidden">não lidos</span></span>
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
          <span
            v-if="unreadOf('pickup-requests')"
            class="app-sidebar__badge"
          >{{ unreadOf('pickup-requests') }} <span class="visually-hidden">não lidos</span></span>
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
