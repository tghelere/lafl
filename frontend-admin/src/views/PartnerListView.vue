<script setup lang="ts">
import axios from 'axios'
import { Plus } from 'lucide-vue-next'
import { ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import AppIcon from '@/components/AppIcon.vue'
import AppLayout from '@/components/AppLayout.vue'
import EmptyState from '@/components/EmptyState.vue'
import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import NoticeBanner from '@/components/NoticeBanner.vue'
import PageHeader from '@/components/PageHeader.vue'
import PaginationControls from '@/components/PaginationControls.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import { fetchPartnerList } from '@/services/partners'
import type { Partner } from '@/types/partners'

const route = useRoute()
const router = useRouter()

const partners = ref<Partner[]>([])
const currentPage = ref(1)
const lastPage = ref(1)
const isLoading = ref(true)
const errorMessage = ref<string | null>(null)

async function load(): Promise<void> {
  isLoading.value = true
  errorMessage.value = null

  try {
    const response = await fetchPartnerList(Number(route.query.page ?? 1))
    partners.value = response.data
    currentPage.value = response.meta.current_page
    lastPage.value = response.meta.last_page
  } catch (error) {
    errorMessage.value =
      axios.isAxiosError(error) && error.response?.status === 403
        ? 'Você não tem permissão para ver os parceiros.'
        : 'Não foi possível carregar os parceiros. Tente novamente.'
  } finally {
    isLoading.value = false
  }
}

function goToPage(page: number): void {
  void router.push({ query: { page: String(page) } })
}

watch(() => route.fullPath, () => void load(), { immediate: true })
</script>

<template>
  <AppLayout resource="partners">
    <PageHeader title="Parceiros">
      <template #actions>
        <RouterLink
          :to="{ name: 'partners.create' }"
          class="btn btn--primary"
        >
          <AppIcon :icon="Plus" />
          Novo parceiro
        </RouterLink>
      </template>
    </PageHeader>

    <NoticeBanner
      v-if="route.query.created"
      variant="info"
    >
      Parceiro cadastrado.
    </NoticeBanner>
    <NoticeBanner
      v-if="route.query.deleted"
      variant="info"
    >
      Parceiro excluído.
    </NoticeBanner>

    <LoadingState v-if="isLoading" />
    <ErrorState
      v-else-if="errorMessage"
      :message="errorMessage"
    />
    <EmptyState
      v-else-if="partners.length === 0"
      message="Nenhum parceiro cadastrado."
    />
    <template v-else>
      <div class="table-wrapper">
        <table class="table">
          <thead>
            <tr>
              <th>Logo</th>
              <th>Nome</th>
              <th>Link</th>
              <th>Ordem</th>
              <th>Situação</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="partner in partners"
              :key="partner.uuid"
              class="table__row--clickable"
            >
              <td data-label="Logo">
                <img
                  class="partner-logo"
                  :src="partner.logo_url"
                  :alt="`Logo de ${partner.name}`"
                  loading="lazy"
                >
              </td>
              <td data-label="Nome">
                <RouterLink
                  :to="{ name: 'partners.edit', params: { uuid: partner.uuid } }"
                  class="table__row-link"
                >
                  {{ partner.name }}
                </RouterLink>
              </td>
              <td data-label="Link">
                {{ partner.url ?? '—' }}
              </td>
              <td data-label="Ordem">
                {{ partner.position }}
              </td>
              <td data-label="Situação">
                <StatusBadge
                  :status="partner.is_active ? 'active' : 'inactive'"
                  :label="partner.is_active ? 'Ativo' : 'Inativo'"
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
  </AppLayout>
</template>

<style scoped>
.partner-logo {
  width: 6rem;
  height: 3.5rem;
  object-fit: contain;
  background: var(--color-surface-muted, transparent);
  border-radius: var(--radius-sm, 4px);
}
</style>
