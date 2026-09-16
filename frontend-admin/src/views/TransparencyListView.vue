<script setup lang="ts">
import axios from 'axios'
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import AppLayout from '@/components/AppLayout.vue'
import EmptyState from '@/components/EmptyState.vue'
import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import NoticeBanner from '@/components/NoticeBanner.vue'
import PaginationControls from '@/components/PaginationControls.vue'
import { fetchTransparencyDocumentList } from '@/services/transparencyDocuments'
import { TRANSPARENCY_DOCUMENT_TYPE_OPTIONS } from '@/types/transparency'
import type { TransparencyDocument } from '@/types/transparency'

const route = useRoute()
const router = useRouter()

const documents = ref<TransparencyDocument[]>([])
const currentPage = ref(1)
const lastPage = ref(1)
const isLoading = ref(true)
const errorMessage = ref<string | null>(null)

const yearFilter = ref('')
const typeFilter = ref('')

// Filtro por ano/tipo e paginação são aplicados no servidor (App\Http\Controllers\Api\V1\
// TransparencyDocumentController::index) — a query string é a fonte de verdade do filtro e
// da página atual, mesmo padrão de SubmissionListView.vue, para o link ser compartilhável e
// recarregar a página preservar o estado.
async function load(): Promise<void> {
  isLoading.value = true
  errorMessage.value = null

  try {
    const response = await fetchTransparencyDocumentList({
      year: yearFilter.value ? Number(yearFilter.value) : undefined,
      type: typeFilter.value || undefined,
      page: Number(route.query.page ?? 1),
    })
    documents.value = response.data
    currentPage.value = response.meta.current_page
    lastPage.value = response.meta.last_page
  } catch (error) {
    errorMessage.value =
      axios.isAxiosError(error) && error.response?.status === 403
        ? 'Você não tem permissão para ver os documentos de transparência.'
        : 'Não foi possível carregar os documentos. Tente novamente.'
  } finally {
    isLoading.value = false
  }
}

const emptyMessage = computed(() =>
  yearFilter.value || typeFilter.value ? 'Nenhum documento encontrado para esse filtro.' : 'Nenhum documento cadastrado.',
)

function applyFilters(): void {
  // Sem `page` de propósito — trocar o filtro sempre volta para a primeira página.
  void router.push({
    query: {
      ...(yearFilter.value ? { year: yearFilter.value } : {}),
      ...(typeFilter.value ? { type: typeFilter.value } : {}),
    },
  })
}

function clearFilters(): void {
  void router.push({ query: {} })
}

function goToPage(page: number): void {
  void router.push({ query: { ...route.query, page: String(page) } })
}

function formatFileSize(bytes: number): string {
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

watch(
  () => route.fullPath,
  () => {
    yearFilter.value = String(route.query.year ?? '')
    typeFilter.value = String(route.query.type ?? '')
    void load()
  },
  { immediate: true },
)
</script>

<template>
  <AppLayout>
    <h1>Transparência</h1>

    <NoticeBanner
      v-if="route.query.created"
      variant="info"
    >
      Documento cadastrado.
    </NoticeBanner>
    <NoticeBanner
      v-if="route.query.deleted"
      variant="info"
    >
      Documento excluído.
    </NoticeBanner>

    <form
      class="filter-bar"
      @submit.prevent="applyFilters"
    >
      <div class="filter-bar__field">
        <label for="filter-year">Ano</label>
        <input
          id="filter-year"
          v-model="yearFilter"
          type="number"
          placeholder="Todos"
        >
      </div>

      <div class="filter-bar__field">
        <label for="filter-type">Tipo</label>
        <select
          id="filter-type"
          v-model="typeFilter"
        >
          <option value="">
            Todos
          </option>
          <option
            v-for="option in TRANSPARENCY_DOCUMENT_TYPE_OPTIONS"
            :key="option.value"
            :value="option.value"
          >
            {{ option.label }}
          </option>
        </select>
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

      <RouterLink
        :to="{ name: 'transparency.create' }"
        class="btn btn--primary transparency-list__new"
      >
        + Novo documento
      </RouterLink>
    </form>

    <LoadingState v-if="isLoading" />
    <ErrorState
      v-else-if="errorMessage"
      :message="errorMessage"
    />
    <EmptyState
      v-else-if="documents.length === 0"
      :message="emptyMessage"
    />
    <template v-else>
      <div class="table-wrapper">
        <table class="table">
          <thead>
            <tr>
              <th>Título</th>
              <th>Ano</th>
              <th>Tipo</th>
              <th>Tamanho</th>
              <th>Downloads</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="document in documents"
              :key="document.uuid"
              class="table__row--clickable"
            >
              <td>
                <RouterLink
                  :to="{ name: 'transparency.edit', params: { uuid: document.uuid } }"
                  class="table__row-link"
                >
                  {{ document.title }}
                </RouterLink>
              </td>
              <td>{{ document.year }}</td>
              <td>{{ document.type_label }}</td>
              <td>{{ formatFileSize(document.file_size) }}</td>
              <td>{{ document.download_count }}</td>
              <td>
                <span
                  class="badge"
                  :class="document.published_at ? 'badge--published' : 'badge--draft'"
                >
                  {{ document.published_at ? 'Publicado' : 'Rascunho' }}
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
.transparency-list__new {
  margin-left: auto;
}
</style>
