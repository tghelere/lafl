<script setup lang="ts">
import axios from 'axios'
import { Funnel, Plus, X } from 'lucide-vue-next'
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import AppIcon from '@/components/AppIcon.vue'
import AppLayout from '@/components/AppLayout.vue'
import EmptyState from '@/components/EmptyState.vue'
import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PageHeader from '@/components/PageHeader.vue'
import PaginationControls from '@/components/PaginationControls.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import { fetchRoles } from '@/services/roles'
import { fetchUserList } from '@/services/users'
import type { RoleOption, UserAccount } from '@/types/users'

const route = useRoute()
const router = useRouter()

const users = ref<UserAccount[]>([])
const currentPage = ref(1)
const lastPage = ref(1)
const isLoading = ref(true)
const errorMessage = ref<string | null>(null)

const searchFilter = ref('')
const statusFilter = ref('')

const roles = ref<RoleOption[]>([])
const roleLabels = computed<Record<string, string>>(() =>
  Object.fromEntries(roles.value.map((role) => [role.value, role.label])),
)

function roleNames(userRoles: string[]): string {
  return userRoles.map((role) => roleLabels.value[role] ?? role).join(', ')
}

async function loadRoles(): Promise<void> {
  try {
    roles.value = await fetchRoles()
  } catch {
    // A lista continua útil mesmo sem os nomes legíveis dos papéis — roleNames() cai para o
    // valor cru (ex.: "financeiro") em vez de quebrar a tela inteira por causa disto.
  }
}

async function load(): Promise<void> {
  isLoading.value = true
  errorMessage.value = null

  try {
    const response = await fetchUserList({
      search: searchFilter.value || undefined,
      status: (statusFilter.value || undefined) as 'active' | 'inactive' | undefined,
      page: Number(route.query.page ?? 1),
    })
    users.value = response.data
    currentPage.value = response.meta.current_page
    lastPage.value = response.meta.last_page
  } catch (error) {
    errorMessage.value =
      axios.isAxiosError(error) && error.response?.status === 403
        ? 'Você não tem permissão para ver os usuários.'
        : 'Não foi possível carregar os usuários. Tente novamente.'
  } finally {
    isLoading.value = false
  }
}

const emptyMessage = computed(() =>
  searchFilter.value || statusFilter.value ? 'Nenhum usuário encontrado para esse filtro.' : 'Nenhum usuário cadastrado.',
)

function applyFilters(): void {
  void router.push({
    query: {
      ...(searchFilter.value ? { search: searchFilter.value } : {}),
      ...(statusFilter.value ? { status: statusFilter.value } : {}),
    },
  })
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
    statusFilter.value = String(route.query.status ?? '')
    void load()
  },
  { immediate: true },
)

void loadRoles()
</script>

<template>
  <AppLayout resource="users">
    <PageHeader title="Usuários">
      <template #actions>
        <RouterLink
          :to="{ name: 'users.create' }"
          class="btn btn--primary"
        >
          <AppIcon :icon="Plus" />
          Novo usuário
        </RouterLink>
      </template>
    </PageHeader>

    <form
      class="filter-bar"
      @submit.prevent="applyFilters"
    >
      <div class="filter-bar__field">
        <label for="filter-search">Nome ou e-mail</label>
        <input
          id="filter-search"
          v-model="searchFilter"
          type="text"
          placeholder="Buscar"
        >
      </div>

      <div class="filter-bar__field">
        <label for="filter-status">Situação</label>
        <select
          id="filter-status"
          v-model="statusFilter"
        >
          <option value="">
            Todos
          </option>
          <option value="active">
            Ativos
          </option>
          <option value="inactive">
            Inativos
          </option>
        </select>
      </div>

      <button
        type="submit"
        class="btn btn--primary"
      >
        <AppIcon :icon="Funnel" />
        Filtrar
      </button>
      <button
        type="button"
        class="btn btn--secondary"
        @click="clearFilters"
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
      v-else-if="users.length === 0"
      :message="emptyMessage"
    />
    <template v-else>
      <div class="table-wrapper">
        <table class="table">
          <thead>
            <tr>
              <th>Nome</th>
              <th>E-mail</th>
              <th>Papéis</th>
              <th>Situação</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="user in users"
              :key="user.id"
              class="table__row--clickable"
            >
              <td data-label="Nome">
                <RouterLink
                  :to="{ name: 'users.edit', params: { uuid: user.id } }"
                  class="table__row-link"
                >
                  {{ user.name }}
                </RouterLink>
              </td>
              <td data-label="E-mail">
                {{ user.email }}
              </td>
              <td data-label="Papéis">
                {{ roleNames(user.roles) }}
              </td>
              <td data-label="Situação">
                <StatusBadge
                  :status="user.active ? 'active' : 'inactive'"
                  :label="user.active ? 'Ativo' : 'Inativo'"
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
