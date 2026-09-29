<script setup lang="ts">
import axios from 'axios'
import { ImagePlus, Search, X } from 'lucide-vue-next'
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import AppIcon from '@/components/AppIcon.vue'
import AppLayout from '@/components/AppLayout.vue'
import EmptyState from '@/components/EmptyState.vue'
import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import NoticeBanner from '@/components/NoticeBanner.vue'
import PageHeader from '@/components/PageHeader.vue'
import PaginationControls from '@/components/PaginationControls.vue'
import { fetchMediaList } from '@/services/media'
import type { Media } from '@/types/media'

const route = useRoute()
const router = useRouter()

const items = ref<Media[]>([])
const currentPage = ref(1)
const lastPage = ref(1)
const isLoading = ref(true)
const errorMessage = ref<string | null>(null)
const search = ref('')

// Busca e página na query string, como nas outras listagens (TransparencyListView.vue): link
// compartilhável e recarga preservando o estado. A busca em si é da API.
async function load(): Promise<void> {
  isLoading.value = true
  errorMessage.value = null

  try {
    const response = await fetchMediaList({
      search: search.value || undefined,
      page: Number(route.query.page ?? 1),
    })
    items.value = response.data
    currentPage.value = response.meta.current_page
    lastPage.value = response.meta.last_page
  } catch (error) {
    errorMessage.value =
      axios.isAxiosError(error) && error.response?.status === 403
        ? 'Você não tem permissão para ver a biblioteca de imagens.'
        : 'Não foi possível carregar as imagens. Tente novamente.'
  } finally {
    isLoading.value = false
  }
}

const emptyMessage = computed(() =>
  search.value ? 'Nenhuma imagem encontrada para essa busca.' : 'Nenhuma imagem na biblioteca ainda.',
)

function applySearch(): void {
  void router.push({ query: search.value ? { search: search.value } : {} })
}

function clearSearch(): void {
  void router.push({ query: {} })
}

function goToPage(page: number): void {
  void router.push({ query: { ...route.query, page: String(page) } })
}

watch(
  () => route.fullPath,
  () => {
    search.value = String(route.query.search ?? '')
    void load()
  },
  { immediate: true },
)
</script>

<template>
  <AppLayout resource="media">
    <PageHeader title="Imagens">
      <template #actions>
        <RouterLink
          :to="{ name: 'media.create' }"
          class="btn btn--primary"
        >
          <AppIcon :icon="ImagePlus" />
          Enviar imagem
        </RouterLink>
      </template>
    </PageHeader>

    <NoticeBanner
      v-if="route.query.deleted"
      variant="info"
    >
      Imagem excluída.
    </NoticeBanner>

    <form
      class="filter-bar"
      role="search"
      @submit.prevent="applySearch"
    >
      <div class="filter-bar__field">
        <label for="media-search">Buscar</label>
        <input
          id="media-search"
          v-model="search"
          type="search"
          placeholder="Texto alternativo ou legenda"
        >
      </div>

      <button
        type="submit"
        class="btn btn--primary"
      >
        <AppIcon :icon="Search" />
        Buscar
      </button>
      <button
        type="button"
        class="btn btn--secondary"
        @click="clearSearch"
      >
        <AppIcon :icon="X" />
        Limpar
      </button>
    </form>

    <LoadingState v-if="isLoading" />
    <ErrorState
      v-else-if="errorMessage"
      :message="errorMessage"
    />
    <EmptyState
      v-else-if="items.length === 0"
      :message="emptyMessage"
    />
    <template v-else>
      <ul
        class="media-grid"
        aria-label="Imagens da biblioteca"
      >
        <li
          v-for="item in items"
          :key="item.id"
          class="media-tile"
        >
          <RouterLink
            :to="{ name: 'media.edit', params: { uuid: item.id } }"
            class="media-tile__link"
          >
            <span class="media-tile__frame">
              <img
                :src="item.preview_url"
                :alt="item.alt"
                :width="item.width"
                :height="item.height"
                loading="lazy"
                class="media-tile__image"
              >
            </span>
            <span class="media-tile__alt">{{ item.alt }}</span>
            <span class="media-tile__meta">{{ item.width }} × {{ item.height }}</span>
          </RouterLink>
          <span
            v-if="!item.publishable"
            class="badge badge--discarded media-tile__badge"
          >Fora do site</span>
        </li>
      </ul>

      <PaginationControls
        :current-page="currentPage"
        :last-page="lastPage"
        @change="goToPage"
      />
    </template>
  </AppLayout>
</template>
