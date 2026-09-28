<script setup lang="ts">
import { computed, onMounted } from 'vue'

import AppIcon from '@/components/AppIcon.vue'
import AppLayout from '@/components/AppLayout.vue'
import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PageHeader from '@/components/PageHeader.vue'
import { RESOURCE_ICONS } from '@/config/icons'
import { SUBMISSION_RESOURCES } from '@/config/submissionResources'
import { useAuthStore } from '@/stores/auth'
import { useUnreadCountsStore } from '@/stores/unreadCounts'

const authStore = useAuthStore()

/**
 * Mesma store da navegação lateral (ver src/stores/unreadCounts.ts): o card e o contador do menu
 * mostram o mesmo número porque leem a mesma coisa, não porque duas chamadas coincidem.
 *
 * `refresh`, e não `ensureLoaded`: entrar no Início é pedir o retrato de agora.
 */
const unreadCounts = useUnreadCountsStore()

onMounted(() => {
  void unreadCounts.refresh()
})

const entries = computed(() => unreadCounts.entries)

/**
 * O título do card vem da config do painel quando existe (é o nome que a pessoa vê no menu e na
 * listagem), com o rótulo da API como reserva. O slug do recurso vem da API — não há mais mapa
 * reverso de tipo para recurso mantido à mão aqui.
 */
function resourceTitle(resource: string, fallback: string): string {
  return SUBMISSION_RESOURCES[resource]?.title ?? fallback
}

/** Mesmo ícone que o recurso tem na navegação lateral — ver src/config/icons.ts. */
function resourceIcon(resource: string) {
  return RESOURCE_ICONS[resource]
}
</script>

<template>
  <AppLayout>
    <PageHeader title="Início" />
    <p v-if="authStore.user">
      Olá, {{ authStore.user.name }}.
    </p>

    <LoadingState v-if="unreadCounts.isLoading && !unreadCounts.hasLoaded" />
    <ErrorState
      v-else-if="unreadCounts.errorMessage"
      :message="unreadCounts.errorMessage"
    />

    <!-- comunicacao (e qualquer papel sem viewAny em nenhum dos cinco formulários) recebe uma
         lista vazia da API — nunca uma exceção especial no front, ver
         App\Actions\Dashboard\GetUnreadFormSubmissionCounts no backend. -->
    <p v-else-if="entries.length === 0">
      Não há formulários para o seu perfil no momento.
    </p>

    <div
      v-else
      class="summary-grid"
    >
      <RouterLink
        v-for="entry in entries"
        :key="entry.type"
        :to="{ name: 'submissions.index', params: { resource: entry.resource }, query: { read: 'unread' } }"
        class="card summary-card"
      >
        <span class="summary-card__value">{{ entry.unread }}</span>
        <span class="summary-card__label">
          <AppIcon
            v-if="resourceIcon(entry.resource)"
            :icon="resourceIcon(entry.resource)!"
          />
          {{ resourceTitle(entry.resource, entry.label) }}
        </span>
        <span class="summary-card__hint">não lidos</span>
      </RouterLink>
    </div>
  </AppLayout>
</template>
