<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'

import AppLayout from '@/components/AppLayout.vue'
import ErrorState from '@/components/ErrorState.vue'
import InternalNoteForm from '@/components/InternalNoteForm.vue'
import LoadingState from '@/components/LoadingState.vue'
import NoticeBanner from '@/components/NoticeBanner.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import { SUBMISSION_RESOURCES } from '@/config/submissionResources'
import { fetchSubmissionDetail, markSubmissionUnread, updateSubmissionStatus } from '@/services/submissions'
import { useUnreadCountsStore } from '@/stores/unreadCounts'
import type { SubmissionDetail } from '@/types/submission'

const route = useRoute()
const unreadCounts = useUnreadCountsStore()

const resourceSlug = computed(() => String(route.params.resource))
const uuid = computed(() => String(route.params.uuid))
const config = computed(() => SUBMISSION_RESOURCES[resourceSlug.value])

const submission = ref<SubmissionDetail | null>(null)
const isLoading = ref(false)
const errorMessage = ref<string | null>(null)
const isSaving = ref(false)
const saveConfirmedAt = ref<number | null>(null)
const isMarkingUnread = ref(false)

async function load(): Promise<void> {
  if (!config.value) {
    return
  }

  isLoading.value = true
  errorMessage.value = null
  submission.value = null

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
      <nav
        class="breadcrumb"
        aria-label="Trilha de navegação"
      >
        <ol>
          <li>
            <RouterLink :to="{ name: 'submissions.index', params: { resource: resourceSlug } }">
              {{ config.title }}
            </RouterLink>
          </li>
          <li><span aria-current="page">Detalhe</span></li>
        </ol>
      </nav>

      <LoadingState v-if="isLoading" />
      <ErrorState
        v-else-if="errorMessage && !submission"
        :message="errorMessage"
      />

      <template v-else-if="submission">
        <h1>{{ config.title }}</h1>

        <NoticeBanner variant="info">
          Este acesso foi registrado — quem visualizou este registro, quando e de qual IP fica
          na auditoria do sistema.
        </NoticeBanner>

        <div class="detail-header">
          <StatusBadge
            :status="submission.status"
            :label="submission.status_label"
          />
          <StatusBadge
            v-if="!submission.is_read"
            status="unread"
            label="Não lido"
          />

          <!-- Só o desmarcar tem botão: marcar como lido acontece ao abrir esta tela, então um
               botão "Marcar como lido" nunca teria o que fazer aqui. -->
          <button
            v-if="submission.is_read"
            type="button"
            class="btn btn--secondary"
            :disabled="isMarkingUnread"
            @click="handleMarkUnread"
          >
            Marcar como não lido
          </button>
        </div>

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
      </template>
    </template>
  </AppLayout>
</template>
