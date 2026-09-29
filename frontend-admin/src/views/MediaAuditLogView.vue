<script setup lang="ts">
import { Funnel, X } from 'lucide-vue-next'
import { onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import AppIcon from '@/components/AppIcon.vue'
import AppLayout from '@/components/AppLayout.vue'
import AuditTabs from '@/components/AuditTabs.vue'
import EmptyState from '@/components/EmptyState.vue'
import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PageHeader from '@/components/PageHeader.vue'
import PaginationControls from '@/components/PaginationControls.vue'
import { MEDIA_AUDIT_EVENT_OPTIONS } from '@/config/mediaAuditEvents'
import { fetchMediaAuditLog } from '@/services/auditLogs'
import { fetchUserList } from '@/services/users'
import type { MediaAuditEntry } from '@/types/audit'
import type { UserAccount } from '@/types/users'

/**
 * Auditoria da biblioteca de imagens — a segunda aba da tela de Auditoria (a primeira é a dos
 * formulários, AuditLogView.vue). Só leitura e só super_admin, pela mesma Policy
 * (App\Policies\ActivityPolicy). Envio, importação, troca de arquivo, texto, marcação,
 * exclusão e o que acontece com a imagem nas páginas.
 *
 * Tela própria, e não um filtro da de formulários: cada aba responde a uma pergunta, e as
 * colunas são outras (não há IP nem tipo de formulário aqui).
 */
const route = useRoute()
const router = useRouter()

const entries = ref<MediaAuditEntry[]>([])
const currentPage = ref(1)
const lastPage = ref(1)
const isLoading = ref(false)
const errorMessage = ref<string | null>(null)
const users = ref<UserAccount[]>([])

const eventFilter = ref('')
const userFilter = ref('')
const fromFilter = ref('')
const toFilter = ref('')

async function load(): Promise<void> {
  isLoading.value = true
  errorMessage.value = null

  try {
    const response = await fetchMediaAuditLog({
      event: eventFilter.value || undefined,
      user: userFilter.value || undefined,
      from: fromFilter.value || undefined,
      to: toFilter.value || undefined,
      page: Number(route.query.page ?? 1),
    })
    entries.value = response.data
    currentPage.value = response.meta.current_page
    lastPage.value = response.meta.last_page
  } catch {
    errorMessage.value = 'Não foi possível carregar a auditoria de imagens. Tente novamente.'
  } finally {
    isLoading.value = false
  }
}

function applyFilters(): void {
  void router.push({
    query: {
      ...(eventFilter.value ? { event: eventFilter.value } : {}),
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

function entryKey(entry: MediaAuditEntry, index: number): string {
  return `${entry.occurred_at ?? ''}-${entry.event ?? ''}-${entry.media_uuid ?? ''}-${index}`
}

onMounted(async () => {
  try {
    users.value = (await fetchUserList({ page: 1 })).data
  } catch {
    // Sem opções no filtro por usuário; a listagem continua utilizável.
  }
})

watch(
  () => route.fullPath,
  () => {
    eventFilter.value = String(route.query.event ?? '')
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
    <AuditTabs />

    <p class="page-intro">
      Envio, troca, texto, marcação e exclusão de imagens, e o que acontece com elas nas páginas.
      Somente leitura.
    </p>

    <form
      class="filter-bar"
      @submit.prevent="applyFilters"
    >
      <div class="filter-bar__field">
        <label for="media-audit-user">Usuário</label>
        <select
          id="media-audit-user"
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
        <label for="media-audit-event">Acontecimento</label>
        <select
          id="media-audit-event"
          v-model="eventFilter"
        >
          <option value="">
            Todos
          </option>
          <option
            v-for="option in MEDIA_AUDIT_EVENT_OPTIONS"
            :key="option.value"
            :value="option.value"
          >
            {{ option.label }}
          </option>
        </select>
      </div>

      <div class="filter-bar__field">
        <label for="media-audit-from">De</label>
        <input
          id="media-audit-from"
          v-model="fromFilter"
          type="date"
        >
      </div>

      <div class="filter-bar__field">
        <label for="media-audit-to">Até</label>
        <input
          id="media-audit-to"
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
      message="Nenhum registro de auditoria de imagens para este recorte."
    />
    <template v-else>
      <div class="table-wrapper">
        <table class="table">
          <thead>
            <tr>
              <th>Data e hora</th>
              <th>Usuário</th>
              <th>Ação</th>
              <th>Imagem</th>
              <th>Detalhe</th>
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
              <!-- Sem autor: a importação das fotos iniciais, feita por comando. -->
              <td data-label="Usuário">
                {{ entry.user ?? 'Sistema' }}
              </td>
              <td data-label="Ação">
                {{ entry.action_label }}
              </td>
              <td
                data-label="Imagem"
                class="table__cell--wrap"
              >
                <RouterLink
                  v-if="entry.media_uuid"
                  :to="{ name: 'media.edit', params: { uuid: entry.media_uuid } }"
                >
                  {{ entry.media_alt ?? 'Abrir imagem' }}
                </RouterLink>
                <!-- Excluída: a linha fica, sem link. -->
                <template v-else>
                  {{ entry.media_alt ? `${entry.media_alt} (excluída)` : '—' }}
                </template>
              </td>
              <td
                data-label="Detalhe"
                class="table__cell--wrap"
              >
                {{ entry.detail ?? '—' }}
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
