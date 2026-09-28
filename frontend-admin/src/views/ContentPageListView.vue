<script setup lang="ts">
import axios from 'axios'
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import AppLayout from '@/components/AppLayout.vue'
import EmptyState from '@/components/EmptyState.vue'
import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PageHeader from '@/components/PageHeader.vue'
import PaginationControls from '@/components/PaginationControls.vue'
import { fetchContentPageList } from '@/services/pages'
import type { ContentPage } from '@/types/pages'

const route = useRoute()
const router = useRouter()

const pages = ref<ContentPage[]>([])
const currentPage = ref(1)
const lastPage = ref(1)
const isLoading = ref(true)
const errorMessage = ref<string | null>(null)

const searchFilter = ref('')

const emptyMessage = computed(() =>
  searchFilter.value ? 'Nenhuma página encontrada para essa busca.' : 'Nenhuma página cadastrada.',
)

async function load(): Promise<void> {
  isLoading.value = true
  errorMessage.value = null

  try {
    const response = await fetchContentPageList({
      search: searchFilter.value || undefined,
      page: Number(route.query.page ?? 1),
    })
    pages.value = response.data
    currentPage.value = response.meta.current_page
    lastPage.value = response.meta.last_page
  } catch (error) {
    errorMessage.value =
      axios.isAxiosError(error) && error.response?.status === 403
        ? 'Você não tem permissão para ver as páginas do site.'
        : 'Não foi possível carregar as páginas. Tente novamente.'
  } finally {
    isLoading.value = false
  }
}

function applyFilters(): void {
  void router.push({ query: searchFilter.value ? { search: searchFilter.value } : {} })
}

function clearFilters(): void {
  void router.push({ query: {} })
}

function goToPage(page: number): void {
  void router.push({ query: { ...route.query, page: String(page) } })
}

watch(
  () => route.fullPath,
  () => {
    searchFilter.value = String(route.query.search ?? '')
    void load()
  },
  { immediate: true },
)
</script>

<template>
  <AppLayout resource="pages">
    <PageHeader title="Páginas" />

    <p class="page-list__intro">
      O endereço público e a situação de cada página são definidos fora desta tela — aqui é
      onde o texto é editado.
    </p>

    <form
      class="filter-bar"
      @submit.prevent="applyFilters"
    >
      <div class="filter-bar__field">
        <label for="filter-search">Título</label>
        <input
          id="filter-search"
          v-model="searchFilter"
          type="text"
          placeholder="Buscar"
        >
      </div>

      <button
        type="submit"
        class="btn btn--primary"
      >
        Filtrar
      </button>
      <button
        type="button"
        class="btn btn--secondary"
        @click="clearFilters"
      >
        Limpar
      </button>
    </form>

    <LoadingState v-if="isLoading" />
    <ErrorState
      v-else-if="errorMessage"
      :message="errorMessage"
    />
    <EmptyState
      v-else-if="pages.length === 0"
      :message="emptyMessage"
    />
    <template v-else>
      <div class="table-wrapper">
        <table class="table">
          <thead>
            <tr>
              <th>Título</th>
              <th>Endereço público</th>
              <th>Situação</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="page in pages"
              :key="page.id"
              class="table__row--clickable"
            >
              <td>
                <RouterLink
                  :to="{ name: 'pages.edit', params: { uuid: page.id } }"
                  class="table__row-link"
                >
                  {{ page.title }}
                </RouterLink>
              </td>
              <td><code>/{{ page.slug }}</code></td>
              <td>
                <span
                  class="badge"
                  :class="page.status === 'published' ? 'badge--published' : 'badge--draft'"
                >
                  {{ page.status_label }}
                </span>
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
  </AppLayout>
</template>

<style scoped>
.page-list__intro {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
  margin-bottom: var(--space-5);
}
</style>
