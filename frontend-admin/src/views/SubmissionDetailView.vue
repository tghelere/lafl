<script setup lang="ts">
import { Mail } from 'lucide-vue-next'
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'

import AppBreadcrumb from '@/components/AppBreadcrumb.vue'
import AppIcon from '@/components/AppIcon.vue'
import AppLayout from '@/components/AppLayout.vue'
import ErrorState from '@/components/ErrorState.vue'
import InternalNoteForm from '@/components/InternalNoteForm.vue'
import LoadingState from '@/components/LoadingState.vue'
import NoticeBanner from '@/components/NoticeBanner.vue'
import PageHeader from '@/components/PageHeader.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import { SUBMISSION_RESOURCES } from '@/config/submissionResources'
import { fetchAuditLog } from '@/services/auditLogs'
import { fetchSubmissionDetail, markSubmissionUnread, updateSubmissionStatus } from '@/services/submissions'
import { useAuthStore } from '@/stores/auth'
import { useUnreadCountsStore } from '@/stores/unreadCounts'
import type { AuditEntry } from '@/types/audit'
import type { BreadcrumbItem } from '@/types/breadcrumb'
import type { SubmissionDetail } from '@/types/submission'

const route = useRoute()
const unreadCounts = useUnreadCountsStore()
const authStore = useAuthStore()

/**
 * O histórico de acessos deste registro é da auditoria, e auditoria é de super_admin. A checagem é
 * pelo mapa `access` calculado por Policy no backend (ver
 * App\Http\Resources\UserResource::accessMap), nunca por `roles.includes('super_admin')` aqui —
 * e quem barra de verdade continua sendo a Policy: a chamada de um papel sem acesso volta 403.
 */
const canSeeAccessHistory = computed(() => authStore.user?.access['audit-logs'] === true)

const resourceSlug = computed(() => String(route.params.resource))
const uuid = computed(() => String(route.params.uuid))
const config = computed(() => SUBMISSION_RESOURCES[resourceSlug.value])

/**
 * "Voluntários / Detalhe" — dois degraus, e nunca o nome de quem enviou o formulário. O nome
 * já está na tela, sob a mesma Policy, mas a trilha é copiada e colada em conversa e relato de
 * erro com muito mais frequência que o corpo da tela; na dúvida sobre dado pessoal, a opção
 * mais restritiva (ver CLAUDE.md, "Como trabalhar neste projeto").
 */
const breadcrumb = computed<BreadcrumbItem[]>(() => [
  {
    label: config.value?.title ?? '',
    to: { name: 'submissions.index', params: { resource: resourceSlug.value } },
  },
  { label: 'Detalhe' },
])

const submission = ref<SubmissionDetail | null>(null)
const isLoading = ref(false)
const errorMessage = ref<string | null>(null)
const isSaving = ref(false)
const saveConfirmedAt = ref<number | null>(null)
const isMarkingUnread = ref(false)

const accessHistory = ref<AuditEntry[] | null>(null)
const accessHistoryError = ref<string | null>(null)

/**
 * Carregado só quando a seção é aberta, não junto do registro: é uma segunda chamada de rede que a
 * maioria das visitas ao detalhe não precisa.
 */
async function loadAccessHistory(): Promise<void> {
  if (accessHistory.value !== null) {
    return
  }

  accessHistoryError.value = null

  try {
    accessHistory.value = (await fetchAuditLog({ record: uuid.value, per_page: 50 })).data
  } catch {
    accessHistoryError.value = 'Não foi possível carregar o histórico de acessos.'
  }
}

async function load(): Promise<void> {
  if (!config.value) {
    return
  }

  isLoading.value = true
  errorMessage.value = null
  submission.value = null
  accessHistory.value = null
  accessHistoryError.value = null

  try {
    submission.value = await fetchSubmissionDetail(resourceSlug.value, uuid.value)

    // Abrir o detalhe marca como lido do lado da API (ver
    // docs/decisoes/0021-leitura-separada-do-status-de-atendimento.md) — os contadores do menu
    // e da tela Início têm de refletir isso sem esperar uma recarga da página.
    void unreadCounts.refresh()
  } catch {
    errorMessage.value = 'Não foi possível carregar o registro. Tente novamente.'
  } finally {
    isLoading.value = false
  }
}

async function handleMarkUnread(): Promise<void> {
  isMarkingUnread.value = true
  errorMessage.value = null

  try {
    submission.value = await markSubmissionUnread(resourceSlug.value, uuid.value)
    void unreadCounts.refresh()
  } catch {
    errorMessage.value = 'Não foi possível marcar como não lido. Tente novamente.'
  } finally {
    isMarkingUnread.value = false
  }
}

async function handleStatusSubmit(payload: { status: string; internalNote: string | null }): Promise<void> {
  isSaving.value = true
  saveConfirmedAt.value = null

  try {
    submission.value = await updateSubmissionStatus(resourceSlug.value, uuid.value, {
      status: payload.status,
      internal_note: payload.internalNote,
    })
    saveConfirmedAt.value = Date.now()
  } catch {
    errorMessage.value = 'Não foi possível salvar. Tente novamente.'
  } finally {
    isSaving.value = false
  }
}

watch(() => route.fullPath, load, { immediate: true })
</script>

<template>
  <AppLayout :resource="resourceSlug">
    <!-- Nunca renderiza: quando `config` é falso, `resourceSlug` não existe no mapa de acesso
         devolvido por /auth/user, então AppLayout já mostra a tela de não encontrada antes
         de chegar a desenhar este slot. O ramo só está aqui para o vue-tsc estreitar o tipo
         de `config` no ramo abaixo. -->
    <template v-if="!config" />

    <template v-else>
      <AppBreadcrumb :items="breadcrumb" />

      <LoadingState v-if="isLoading" />
      <ErrorState
        v-else-if="errorMessage && !submission"
        :message="errorMessage"
      />

      <template v-else-if="submission">
        <PageHeader :title="config.title">
          <template #badge>
            <StatusBadge
              :status="submission.status"
              :label="submission.status_label"
            />
            <StatusBadge
              v-if="!submission.is_read"
              status="unread"
              label="Não lido"
            />
          </template>

          <!-- Só o desmarcar tem botão: marcar como lido acontece ao abrir esta tela, então um
               botão "Marcar como lido" nunca teria o que fazer aqui. -->
          <template
            v-if="submission.is_read"
            #actions
          >
            <button
              type="button"
              class="btn btn--secondary"
              :disabled="isMarkingUnread"
              @click="handleMarkUnread"
            >
              <AppIcon :icon="Mail" />
              Marcar como não lido
            </button>
          </template>
        </PageHeader>

        <dl class="detail-grid">
          <!-- Comum às cinco telas, por isso fora de config.detailFields. Primeiro campo da
               grade: numa caixa de entrada, "quando isto chegou" é o contexto de tudo o mais. -->
          <div>
            <dt>Recebido em</dt>
            <dd>{{ submission.created_at_label ?? '—' }}</dd>
          </div>
          <template
            v-for="field in config.detailFields"
            :key="field.key"
          >
            <div>
              <dt>{{ field.label }}</dt>
              <dd>{{ submission[field.key] || '—' }}</dd>
            </div>
          </template>
          <div v-if="submission.read_at_label">
            <dt>Lido por</dt>
            <dd>{{ submission.read_by ?? '—' }} · {{ submission.read_at_label }}</dd>
          </div>
          <div v-if="submission.handled_by">
            <dt>Atendido por</dt>
            <dd>{{ submission.handled_by }}</dd>
          </div>
        </dl>

        <NoticeBanner
          v-if="errorMessage"
          variant="error"
        >
          {{ errorMessage }}
        </NoticeBanner>
        <NoticeBanner
          v-if="saveConfirmedAt"
          variant="info"
        >
          Atendimento atualizado.
        </NoticeBanner>

        <InternalNoteForm
          :status="submission.status"
          :internal-note="submission.internal_note"
          :submitting="isSaving"
          @submit="handleStatusSubmit"
        />

        <!-- Recolhido por padrão, e só para quem pode ver auditoria. `<details>` nativo: o
             navegador já dá teclado, foco e anúncio de estado sem nenhum JavaScript. -->
        <details
          v-if="canSeeAccessHistory"
          class="access-history"
          @toggle="loadAccessHistory"
        >
          <summary>Histórico de acessos</summary>

          <ErrorState
            v-if="accessHistoryError"
            :message="accessHistoryError"
          />
          <p
            v-else-if="accessHistory === null"
            class="record-footnote"
          >
            Carregando…
          </p>
          <p
            v-else-if="accessHistory.length === 0"
            class="record-footnote"
          >
            Nenhum acesso registrado para este registro.
          </p>
          <ul
            v-else
            class="access-history__list"
          >
            <li
              v-for="(entry, index) in accessHistory"
              :key="`${entry.occurred_at ?? ''}-${entry.event ?? ''}-${index}`"
            >
              <span class="access-history__when">{{ entry.occurred_at_label ?? '—' }}</span>
              · {{ entry.action_label }}
              · {{ entry.user ?? 'site público' }}
              · {{ entry.ip ?? 'sem IP' }}
            </li>
          </ul>
        </details>

        <!-- Nota, não alerta: é verdade permanente sobre todo registro, e um banner destacado em
             toda tela deixa de ser lido depois da terceira vez. -->
        <p class="record-footnote">
          Os acessos a este registro ficam na auditoria do sistema.
        </p>
      </template>
    </template>
  </AppLayout>
</template>
