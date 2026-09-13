<script setup lang="ts">
import { onMounted, ref } from 'vue'

import AppLayout from '@/components/AppLayout.vue'
import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import { SUBMISSION_RESOURCES } from '@/config/submissionResources'
import { fetchDashboardSummary } from '@/services/dashboard'
import { useAuthStore } from '@/stores/auth'
import type { DashboardEntry } from '@/types/submission'

const authStore = useAuthStore()

const entries = ref<DashboardEntry[]>([])
const isLoading = ref(true)
const errorMessage = ref<string | null>(null)

// Mapa reverso tipo -> slug de recurso (ver src/config/submissionResources.ts), só para
// montar o link "ver lista" de cada card — o backend devolve o tipo (ex.:
// "program_application"), a rota usa o slug em kebab-case (ex.: "program-applications").
const TYPE_TO_RESOURCE: Record<string, string> = {
  program_application: 'program-applications',
  pickup_request: 'pickup-requests',
  volunteer_application: 'volunteer-applications',
  partnership_inquiry: 'partnership-inquiries',
  contact_message: 'contact-messages',
}

function resourceTitle(type: string): string {
  const slug = TYPE_TO_RESOURCE[type]

  return slug ? SUBMISSION_RESOURCES[slug]?.title ?? '' : ''
}

onMounted(async () => {
  try {
    entries.value = await fetchDashboardSummary()
  } catch {
    errorMessage.value = 'Não foi possível carregar as pendências. Tente novamente.'
  } finally {
    isLoading.value = false
  }
})
</script>

<template>
  <AppLayout>
    <h1>Início</h1>
    <p v-if="authStore.user">
      Olá, {{ authStore.user.name }}.
    </p>

    <LoadingState v-if="isLoading" />
    <ErrorState
      v-else-if="errorMessage"
      :message="errorMessage"
    />

    <!-- comunicacao (e qualquer papel sem viewAny em nenhum dos cinco formulários) recebe uma
         lista vazia da API — nunca uma exceção especial no front, ver
         App\Actions\Dashboard\GetPendingFormSubmissionCounts no backend. -->
    <p v-else-if="entries.length === 0">
      Seu acesso neste painel é a conteúdo (páginas, notícias, mídia) — ainda não implementado
      nesta fatia. Você não vê formulários recebidos porque seu papel não tem acesso a esse
      tipo de dado (ver docs/estrutura-site.md §4.4).
    </p>

    <div
      v-else
      class="summary-grid"
    >
      <RouterLink
        v-for="entry in entries"
        :key="entry.type"
        :to="{ name: 'submissions.index', params: { resource: TYPE_TO_RESOURCE[entry.type] } }"
        class="card summary-card"
      >
        <span class="summary-card__value">{{ entry.pending }}</span>
        <span class="summary-card__label">{{ resourceTitle(entry.type) || entry.label }}</span>
      </RouterLink>
    </div>
  </AppLayout>
</template>
