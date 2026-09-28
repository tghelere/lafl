<script setup lang="ts">
import axios from 'axios'
import { EyeOff, Globe, Save, Trash2 } from 'lucide-vue-next'
import { computed, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import AppBreadcrumb from '@/components/AppBreadcrumb.vue'
import AppIcon from '@/components/AppIcon.vue'
import AppLayout from '@/components/AppLayout.vue'
import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import NoticeBanner from '@/components/NoticeBanner.vue'
import PageHeader from '@/components/PageHeader.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import {
  createTransparencyDocument,
  deleteTransparencyDocument,
  fetchTransparencyDocument,
  updateTransparencyDocument,
} from '@/services/transparencyDocuments'
import { TRANSPARENCY_DOCUMENT_TYPE_OPTIONS } from '@/types/transparency'
import type { BreadcrumbItem } from '@/types/breadcrumb'
import type { TransparencyDocument } from '@/types/transparency'

// Mesmo teto de StoreTransparencyDocumentRequest::rules() / UpdateTransparencyDocumentRequest::
// rules() — 'max:20480' é em KB. Só conveniência de UX: quem valida de verdade é a API.
const MAX_FILE_BYTES = 20480 * 1024

const route = useRoute()
const router = useRouter()

const uuid = computed(() => (typeof route.params.uuid === 'string' ? route.params.uuid : null))
const isEditing = computed(() => uuid.value !== null)

const record = ref<TransparencyDocument | null>(null)
const isLoading = ref(false)
const loadErrorMessage = ref<string | null>(null)

/**
 * "Transparência / Balanço 2025 / Editar" na edição; "Transparência / Novo documento" na
 * criação, onde não há registro nenhum para nomear o degrau do meio.
 */
const breadcrumb = computed<BreadcrumbItem[]>(() =>
  isEditing.value
    ? [
        { label: 'Transparência', to: { name: 'transparency.index' } },
        { label: record.value?.title ?? '', loading: isLoading.value },
        { label: 'Editar' },
      ]
    : [{ label: 'Transparência', to: { name: 'transparency.index' } }, { label: 'Novo documento' }],
)

const title = ref('')
const year = ref(new Date().getFullYear())
const type = ref('balance')
const published = ref(false)
const file = ref<File | null>(null)
const fileInputError = ref<string | null>(null)

const isSaving = ref(false)
const isTogglingPublish = ref(false)
const isDeleting = ref(false)
const saveConfirmedAt = ref<number | null>(null)
const submitErrorMessage = ref<string | null>(null)
const fieldErrors = reactive<Record<string, string[]>>({})

async function load(): Promise<void> {
  if (!uuid.value) {
    return
  }

  isLoading.value = true
  loadErrorMessage.value = null

  try {
    record.value = await fetchTransparencyDocument(uuid.value)
    title.value = record.value.title
    year.value = record.value.year
    type.value = record.value.type
    published.value = record.value.published_at !== null
  } catch (error) {
    if (axios.isAxiosError(error) && error.response?.status === 403) {
      loadErrorMessage.value = 'Você não tem permissão para editar este documento.'
    } else if (axios.isAxiosError(error) && error.response?.status === 404) {
      loadErrorMessage.value = 'Documento não encontrado.'
    } else {
      loadErrorMessage.value = 'Não foi possível carregar o documento. Tente novamente.'
    }
  } finally {
    isLoading.value = false
  }
}

void load()

function handleFileChange(event: Event): void {
  const input = event.target as HTMLInputElement
  const selected = input.files?.[0] ?? null
  fileInputError.value = null

  if (!selected) {
    file.value = null

    return
  }

  const looksLikePdf = selected.type === 'application/pdf' || selected.name.toLowerCase().endsWith('.pdf')

  if (!looksLikePdf) {
    fileInputError.value = 'O documento precisa ser um PDF.'
    file.value = null
    input.value = ''

    return
  }

  if (selected.size > MAX_FILE_BYTES) {
    fileInputError.value = 'O documento não pode passar de 20 MB.'
    file.value = null
    input.value = ''

    return
  }

  file.value = selected
}

function clearFieldErrors(): void {
  Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key])
}

/**
 * @returns true se o erro era 422 e já foi tratado (campo a campo) — quem chamou não precisa
 * mostrar mensagem genérica.
 */
function applyValidationErrors(error: unknown): boolean {
  if (axios.isAxiosError(error) && error.response?.status === 422) {
    const body = error.response.data as { errors?: Record<string, string[]> }
    clearFieldErrors()
    Object.assign(fieldErrors, body.errors ?? {})
    submitErrorMessage.value = 'Corrija os campos indicados antes de salvar.'

    return true
  }

  return false
}

async function handleSubmit(): Promise<void> {
  isSaving.value = true
  submitErrorMessage.value = null
  saveConfirmedAt.value = null
  clearFieldErrors()

  const payload = {
    title: title.value,
    year: year.value,
    type: type.value,
    published: published.value,
    file: file.value,
  }

  try {
    if (isEditing.value && uuid.value) {
      record.value = await updateTransparencyDocument(uuid.value, payload)
      file.value = null
      saveConfirmedAt.value = Date.now()
    } else {
      await createTransparencyDocument(payload)
      void router.push({ name: 'transparency.index', query: { created: '1' } })

      return
    }
  } catch (error) {
    if (!applyValidationErrors(error)) {
      submitErrorMessage.value =
        axios.isAxiosError(error) && error.response?.status === 403
          ? 'Você não tem permissão para salvar este documento.'
          : 'Não foi possível salvar. Tente novamente.'
    }
  } finally {
    isSaving.value = false
  }
}

/**
 * Ação principal para tirar um documento do ar é despublicar, não excluir (ver o relatório
 * desta sessão) — reenvia os metadados atuais com `published` invertido, sem exigir novo
 * arquivo.
 */
async function togglePublish(): Promise<void> {
  if (!uuid.value) {
    return
  }

  isTogglingPublish.value = true
  submitErrorMessage.value = null
  saveConfirmedAt.value = null
  clearFieldErrors()

  try {
    record.value = await updateTransparencyDocument(uuid.value, {
      title: title.value,
      year: year.value,
      type: type.value,
      published: !published.value,
      file: null,
    })
    published.value = record.value.published_at !== null
    saveConfirmedAt.value = Date.now()
  } catch (error) {
    if (!applyValidationErrors(error)) {
      submitErrorMessage.value =
        axios.isAxiosError(error) && error.response?.status === 403
          ? 'Você não tem permissão para alterar este documento.'
          : 'Não foi possível salvar. Tente novamente.'
    }
  } finally {
    isTogglingPublish.value = false
  }
}

async function handleDelete(): Promise<void> {
  if (!uuid.value) {
    return
  }

  const confirmed = window.confirm(
    'Excluir este documento permanentemente? Prefira despublicar se só quer tirá-lo do ar — a exclusão não pode ser desfeita pelo painel.',
  )

  if (!confirmed) {
    return
  }

  isDeleting.value = true
  submitErrorMessage.value = null

  try {
    await deleteTransparencyDocument(uuid.value)
    void router.push({ name: 'transparency.index', query: { deleted: '1' } })
  } catch (error) {
    submitErrorMessage.value =
      axios.isAxiosError(error) && error.response?.status === 403
        ? 'Você não tem permissão para excluir este documento.'
        : 'Não foi possível excluir. Tente novamente.'
    isDeleting.value = false
  }
}
</script>

<template>
  <AppLayout resource="transparency-documents">
    <AppBreadcrumb :items="breadcrumb" />

    <LoadingState v-if="isLoading" />
    <ErrorState
      v-else-if="loadErrorMessage"
      :message="loadErrorMessage"
    />

    <template v-else>
      <PageHeader :title="isEditing ? 'Editar documento' : 'Novo documento'">
        <!-- Documento que ainda não existe não tem estado de publicação para mostrar. -->
        <template
          v-if="isEditing"
          #badge
        >
          <StatusBadge
            :status="published ? 'published' : 'draft'"
            :label="published ? 'Publicado' : 'Rascunho'"
          />
        </template>
      </PageHeader>

      <NoticeBanner
        v-if="submitErrorMessage"
        variant="error"
      >
        {{ submitErrorMessage }}
      </NoticeBanner>
      <NoticeBanner
        v-if="saveConfirmedAt"
        variant="info"
      >
        Documento salvo.
      </NoticeBanner>

      <form
        class="card"
        @submit.prevent="handleSubmit"
      >
        <div class="field">
          <label for="doc-title">Título</label>
          <input
            id="doc-title"
            v-model="title"
            type="text"
            maxlength="255"
            required
          >
          <span
            v-if="fieldErrors.title"
            class="field__error"
          >{{ fieldErrors.title[0] }}</span>
        </div>

        <div class="field">
          <label for="doc-year">Ano</label>
          <input
            id="doc-year"
            v-model.number="year"
            type="number"
            min="1900"
            required
          >
          <span
            v-if="fieldErrors.year"
            class="field__error"
          >{{ fieldErrors.year[0] }}</span>
        </div>

        <div class="field">
          <label for="doc-type">Tipo</label>
          <select
            id="doc-type"
            v-model="type"
            required
          >
            <option
              v-for="option in TRANSPARENCY_DOCUMENT_TYPE_OPTIONS"
              :key="option.value"
              :value="option.value"
            >
              {{ option.label }}
            </option>
          </select>
          <span
            v-if="fieldErrors.type"
            class="field__error"
          >{{ fieldErrors.type[0] }}</span>
        </div>

        <div class="field">
          <label for="doc-file">Arquivo (PDF, até 20 MB)</label>
          <input
            id="doc-file"
            type="file"
            accept="application/pdf"
            :required="!isEditing"
            @change="handleFileChange"
          >
          <span
            v-if="isEditing"
            class="field__hint"
          >Deixe em branco para manter o arquivo atual.</span>
          <span
            v-if="fileInputError"
            class="field__error"
          >{{ fileInputError }}</span>
          <span
            v-if="fieldErrors.file"
            class="field__error"
          >{{ fieldErrors.file[0] }}</span>
        </div>

        <div class="field field--checkbox">
          <label for="doc-published">
            <input
              id="doc-published"
              v-model="published"
              type="checkbox"
            >
            Publicar imediatamente
          </label>
        </div>

        <button
          type="submit"
          class="btn btn--primary"
          :disabled="isSaving"
        >
          <AppIcon :icon="Save" />
          {{ isSaving ? 'Salvando…' : 'Salvar' }}
        </button>
      </form>

      <div
        v-if="isEditing"
        class="transparency-form__actions"
      >
        <button
          type="button"
          class="btn btn--primary"
          :disabled="isTogglingPublish"
          @click="togglePublish"
        >
          <AppIcon :icon="published ? EyeOff : Globe" />
          {{ isTogglingPublish ? 'Aguarde…' : published ? 'Despublicar' : 'Publicar' }}
        </button>
        <button
          type="button"
          class="btn btn--danger"
          :disabled="isDeleting"
          @click="handleDelete"
        >
          <AppIcon :icon="Trash2" />
          {{ isDeleting ? 'Excluindo…' : 'Excluir' }}
        </button>
      </div>
    </template>
  </AppLayout>
</template>

<style scoped>
.transparency-form__actions {
  display: flex;
  gap: var(--space-3);
  margin-top: var(--space-5);
}
</style>
