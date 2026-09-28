<script setup lang="ts">
import { Funnel, X } from 'lucide-vue-next'
import { onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import AppIcon from '@/components/AppIcon.vue'
import AppLayout from '@/components/AppLayout.vue'
import EmptyState from '@/components/EmptyState.vue'
import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PageHeader from '@/components/PageHeader.vue'
import PaginationControls from '@/components/PaginationControls.vue'
import { FORM_TYPE_OPTIONS } from '@/config/formTypes'
import { fetchAuditLog } from '@/services/auditLogs'
import { fetchUserList } from '@/services/users'
import type { AuditEntry } from '@/types/audit'
import type { UserAccount } from '@/types/users'

/**
 * Auditoria dos formulários recebidos — só leitura, só super_admin (ver
 * App\Policies\ActivityPolicy; a tela é guardada por `resource="audit-logs"`, o mesmo mapa de
 * acesso calculado por Policy que o resto do painel usa).
 *
 * Nenhuma ação: não há botão nenhum além dos filtros e da paginação. Um log de auditoria que se
 * pode alterar pela tela não serve de log.
 */
const route = useRoute()
const router = useRouter()

const entries = ref<AuditEntry[]>([])
const currentPage = ref(1)
const lastPage = ref(1)
const isLoading = ref(false)
const errorMessage = ref<string | null>(null)

// Contas para o filtro por usuário. Vem de /api/v1/users, que é super_admin também — quem abre
// esta tela sempre pode listá-las.
const users = ref<UserAccount[]>([])

const typeFilter = ref('')
const userFilter = ref('')
const fromFilter = ref('')
const toFilter = ref('')

async function load(): Promise<void> {
  isLoading.value = true
  errorMessage.value = null

  try {
    const response = await fetchAuditLog({
      type: typeFilter.value || undefined,
      user: userFilter.value || undefined,
      from: fromFilter.value || undefined,
      to: toFilter.value || undefined,
      page: Number(route.query.page ?? 1),
    })
    entries.value = response.data
    currentPage.value = response.meta.current_page
    lastPage.value = response.meta.last_page
  } catch {
    errorMessage.value = 'Não foi possível carregar a auditoria. Tente novamente.'
  } finally {
    isLoading.value = false
  }
}

function applyFilters(): void {
  void router.push({
    query: {
      ...(typeFilter.value ? { type: typeFilter.value } : {}),
      ...(userFilter.value ? { user: userFilter.value } : {}),
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

/** Chave estável de linha sem id sequencial no payload: o instante mais o que aconteceu. */
function entryKey(entry: AuditEntry, index: number): string {
  return `${entry.occurred_at ?? ''}-${entry.event ?? ''}-${entry.record_uuid ?? ''}-${index}`
}

onMounted(async () => {
  try {
    users.value = (await fetchUserList({ page: 1 })).data
  } catch {
    // O filtro por usuário fica sem opções; a listagem continua utilizável. Não vale trocar a
    // tela inteira por um erro por causa de um <select>.
  }
})

// A query é a fonte de verdade do filtro atual, como nas outras listagens do painel — o link é
// compartilhável e o botão voltar do navegador funciona.
watch(
  () => route.fullPath,
  () => {
    typeFilter.value = String(route.query.type ?? '')
    userFilter.value = String(route.query.user ?? '')
    fromFilter.value = String(route.query.from ?? '')
    toFilter.value = String(route.query.to ?? '')
    void load()
  },
  { immediate: true },
)
</script>

<template>
  <AppLayout resource="audit-logs">
    <PageHeader title="Auditoria" />

    <p class="page-intro">
      Registro de acessos e alterações nos formulários recebidos. Somente leitura.
    </p>

    <form
      class="filter-bar"
      @submit.prevent="applyFilters"
    >
      <div class="filter-bar__field">
        <label for="filter-user">Usuário</label>
        <select
          id="filter-user"
          v-model="userFilter"
        >
          <option value="">
            Todos
          </option>
          <option
            v-for="user in users"
            :key="user.id"
            :value="user.id"
          >
            {{ user.name }}
          </option>
        </select>
      </div>

      <div class="filter-bar__field">
        <label for="filter-type">Tipo de formulário</label>
        <select
          id="filter-type"
          v-model="typeFilter"
        >
          <option value="">
            Todos
          </option>
          <option
            v-for="option in FORM_TYPE_OPTIONS"
            :key="option.value"
            :value="option.value"
          >
            {{ option.label }}
          </option>
        </select>
      </div>

      <div class="filter-bar__field">
        <label for="filter-from">De</label>
        <input
          id="filter-from"
          v-model="fromFilter"
          type="date"
        >
      </div>

      <div class="filter-bar__field">
        <label for="filter-to">Até</label>
        <input
          id="filter-to"
          v-model="toFilter"
          type="date"
        >
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
      v-else-if="entries.length === 0"
      message="Nenhum registro de auditoria para este recorte."
    />
    <template v-else>
      <div class="table-wrapper">
        <table class="table">
          <thead>
            <tr>
              <th>Data e hora</th>
              <th>Usuário</th>
              <th>Ação</th>
              <th>Tipo de formulário</th>
              <th>Registro</th>
              <th>IP</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(entry, index) in entries"
              :key="entryKey(entry, index)"
            >
              <td data-label="Data e hora">
                {{ entry.occurred_at_label ?? '—' }}
              </td>
              <!-- Sem autor: o formulário foi recebido pelo site, não por alguém do painel. -->
              <td data-label="Usuário">
                {{ entry.user ?? '—' }}
              </td>
              <td data-label="Ação">
                {{ entry.action_label }}
              </td>
              <td data-label="Tipo de formulário">
                {{ entry.form_type_label ?? '—' }}
              </td>
              <td data-label="Registro">
                <RouterLink
                  v-if="entry.record_uuid && entry.record_resource"
                  :to="{
                    name: 'submissions.show',
                    params: { resource: entry.record_resource, uuid: entry.record_uuid },
                  }"
                >
                  Abrir registro
                </RouterLink>
                <!-- Registro já expurgado por retenção: a entrada do log permanece, o
                     formulário não. -->
                <template v-else>
                  —
                </template>
              </td>
              <td data-label="IP">
                {{ entry.ip ?? '—' }}
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
