<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import AppLayout from '@/components/AppLayout.vue'
import EmptyState from '@/components/EmptyState.vue'
import ErrorState from '@/components/ErrorState.vue'
import FilterBar from '@/components/FilterBar.vue'
import LoadingState from '@/components/LoadingState.vue'
import PaginationControls from '@/components/PaginationControls.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import { SUBMISSION_RESOURCES } from '@/config/submissionResources'
import { fetchSubmissionList } from '@/services/submissions'
import { STATUS_OPTIONS } from '@/types/forms'
import type { SubmissionListItem } from '@/types/submission'

/**
 * Uma tela só para as cinco listagens (Atendimento + Bazar, ver docs/estrutura-site.md §4.2) —
 * o que muda por recurso é só a config em src/config/submissionResources.ts. A autorização de
 * verdade é sempre da API: se o papel do usuário não tem acesso, a chamada abaixo devolve 403
 * e cai no estado de erro, mesmo que o link nunca apareça na navegação lateral.
 */
const route = useRoute()
const router = useRouter()

const resourceSlug = computed(() => String(route.params.resource))
const config = computed(() => SUBMISSION_RESOURCES[resourceSlug.value])

const items = ref<SubmissionListItem[]>([])
const currentPage = ref(1)
const lastPage = ref(1)
const isLoading = ref(false)
const errorMessage = ref<string | null>(null)

const statusFilter = ref('')
const fromFilter = ref('')
const toFilter = ref('')

async function load(): Promise<void> {
  if (!config.value) {
    return
  }

  isLoading.value = true
  errorMessage.value = null

  try {
    const response = await fetchSubmissionList(resourceSlug.value, {
      status: statusFilter.value || undefined,
      from: fromFilter.value || undefined,
      to: toFilter.value || undefined,
      page: Number(route.query.page ?? 1),
    })
    items.value = response.data
    currentPage.value = response.meta.current_page
    lastPage.value = response.meta.last_page
  } catch {
    errorMessage.value = 'Não foi possível carregar a listagem. Tente novamente.'
  } finally {
    isLoading.value = false
  }
}

function applyFilters(): void {
  void router.push({
    query: {
      ...(statusFilter.value ? { status: statusFilter.value } : {}),
      ...(fromFilter.value ? { from: fromFilter.value } : {}),
      ...(toFilter.value ? { to: toFilter.value } : {}),
    },
  })
}

function clearFilters(): void {
  void router.push({ query: {} })
}

function goToPage(page: number): void {
  void router.push({ query: { ...route.query, page: String(page) } })
}

function detailRoute(uuid: string): { name: string; params: Record<string, string> } {
  return { name: 'submissions.show', params: { resource: resourceSlug.value, uuid } }
}

// Dispara ao entrar na tela, trocar de recurso (outro item da navegação lateral) ou mudar a
// query string (filtro, página) — a query é a fonte de verdade do filtro atual, não um estado
// local solto, para o link ser compartilhável e o botão voltar do navegador funcionar.
watch(
  () => route.fullPath,
  () => {
    statusFilter.value = String(route.query.status ?? '')
    fromFilter.value = String(route.query.from ?? '')
    toFilter.value = String(route.query.to ?? '')
    void load()
  },
  { immediate: true },
)
</script>

<template>
  <AppLayout :resource="resourceSlug">
    <!-- Nunca renderiza: quando `config` é falso, `resourceSlug` não existe no mapa de acesso
         devolvido por /auth/user, então AppLayout já mostra a tela de não encontrada antes
         de chegar a desenhar este slot. O ramo só está aqui para o vue-tsc estreitar o tipo
         de `config` no ramo abaixo. -->
    <template v-if="!config" />

    <template v-else>
      <h1>{{ config.title }}</h1>

      <FilterBar
        v-model:status="statusFilter"
        v-model:from="fromFilter"
        v-model:to="toFilter"
        :status-options="STATUS_OPTIONS"
        @apply="applyFilters"
        @clear="clearFilters"
      />

      <LoadingState v-if="isLoading" />
      <ErrorState
        v-else-if="errorMessage"
        :message="errorMessage"
      />
      <EmptyState v-else-if="items.length === 0" />
      <template v-else>
        <div class="table-wrapper">
          <table class="table">
            <thead>
              <tr>
                <th
                  v-for="column in config.listColumns"
                  :key="column.key"
                >
                  {{ column.label }}
                </th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="item in items"
                :key="item.uuid"
                class="table__row--clickable"
              >
                <td
                  v-for="(column, index) in config.listColumns"
                  :key="column.key"
                >
                  <RouterLink
                    v-if="index === 0"
                    :to="detailRoute(item.uuid)"
                    class="table__row-link"
                  >
                    {{ item[column.key] ?? '—' }}
                  </RouterLink>
                  <template v-else>
                    {{ item[column.key] ?? '—' }}
                  </template>
                </td>
                <td>
                  <StatusBadge
                    :status="item.status"
                    :label="item.status_label"
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <PaginationControls
          :current-page="currentPage"
          :last-page="lastPage"
          @change="goToPage"
        />
      </template>
    </template>
  </AppLayout>
</template>
