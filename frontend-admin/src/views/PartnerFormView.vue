<script setup lang="ts">
import axios from 'axios'
import { Save, Trash2 } from 'lucide-vue-next'
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
import { createPartner, deletePartner, fetchPartner, updatePartner } from '@/services/partners'
import type { BreadcrumbItem } from '@/types/breadcrumb'
import type { Partner } from '@/types/partners'

// Mesmo teto de ValidatesImageUpload::MAX_KILOBYTES no backend. Só conveniência de UX: quem
// valida de verdade é a API.
const MAX_LOGO_BYTES = 10240 * 1024

const route = useRoute()
const router = useRouter()

const uuid = computed(() => (typeof route.params.uuid === 'string' ? route.params.uuid : null))
const isEditing = computed(() => uuid.value !== null)

const record = ref<Partner | null>(null)
const isLoading = ref(false)
const loadErrorMessage = ref<string | null>(null)

const breadcrumb = computed<BreadcrumbItem[]>(() =>
  isEditing.value
    ? [
        { label: 'Parceiros', to: { name: 'partners.index' } },
        { label: record.value?.name ?? '', loading: isLoading.value },
        { label: 'Editar' },
      ]
    : [{ label: 'Parceiros', to: { name: 'partners.index' } }, { label: 'Novo parceiro' }],
)

const name = ref('')
const url = ref('')
const position = ref('')
const isActive = ref(true)
const logo = ref<File | null>(null)
const logoPreview = ref<string | null>(null)
const logoInputError = ref<string | null>(null)

const isSaving = ref(false)
const isDeleting = ref(false)
const saveConfirmedAt = ref<number | null>(null)
const submitErrorMessage = ref<string | null>(null)
const fieldErrors = reactive<Record<string, string[]>>({})

function fill(partner: Partner): void {
  record.value = partner
  name.value = partner.name
  url.value = partner.url ?? ''
  position.value = String(partner.position)
  isActive.value = partner.is_active
}

async function load(): Promise<void> {
  if (!uuid.value) {
    return
  }

  isLoading.value = true
  loadErrorMessage.value = null

  try {
    fill(await fetchPartner(uuid.value))
  } catch (error) {
    if (axios.isAxiosError(error) && error.response?.status === 403) {
      loadErrorMessage.value = 'Você não tem permissão para editar este parceiro.'
    } else if (axios.isAxiosError(error) && error.response?.status === 404) {
      loadErrorMessage.value = 'Parceiro não encontrado.'
    } else {
      loadErrorMessage.value = 'Não foi possível carregar o parceiro. Tente novamente.'
    }
  } finally {
    isLoading.value = false
  }
}

void load()

function handleLogoChange(event: Event): void {
  const input = event.target as HTMLInputElement
  const selected = input.files?.[0] ?? null
  logoInputError.value = null

  if (logoPreview.value) {
    URL.revokeObjectURL(logoPreview.value)
    logoPreview.value = null
  }

  if (!selected) {
    logo.value = null

    return
  }

  if (!['image/png', 'image/jpeg', 'image/webp'].includes(selected.type)) {
    logoInputError.value = 'A logo precisa ser PNG, JPG ou WebP.'
    logo.value = null
    input.value = ''

    return
  }

  if (selected.size > MAX_LOGO_BYTES) {
    logoInputError.value = 'A logo não pode passar de 10 MB.'
    logo.value = null
    input.value = ''

    return
  }

  logo.value = selected
  logoPreview.value = URL.createObjectURL(selected)
}

function clearFieldErrors(): void {
  Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key])
}

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
    name: name.value,
    url: url.value,
    position: position.value,
    isActive: isActive.value,
    logo: logo.value,
  }

  try {
    if (isEditing.value && uuid.value) {
      fill(await updatePartner(uuid.value, payload))
      logo.value = null
      logoPreview.value = null
      saveConfirmedAt.value = Date.now()
    } else {
      await createPartner(payload)
      void router.push({ name: 'partners.index', query: { created: '1' } })

      return
    }
  } catch (error) {
    if (!applyValidationErrors(error)) {
      submitErrorMessage.value =
        axios.isAxiosError(error) && error.response?.status === 403
          ? 'Você não tem permissão para salvar este parceiro.'
          : 'Não foi possível salvar. Tente novamente.'
    }
  } finally {
    isSaving.value = false
  }
}

async function handleDelete(): Promise<void> {
  if (!uuid.value) {
    return
  }

  const confirmed = window.confirm(
    'Excluir este parceiro? Ele deixa de aparecer no site. Se só quer tirá-lo do ar por um tempo, desmarque "Mostrar no site".',
  )

  if (!confirmed) {
    return
  }

  isDeleting.value = true
  submitErrorMessage.value = null

  try {
    await deletePartner(uuid.value)
    void router.push({ name: 'partners.index', query: { deleted: '1' } })
  } catch (error) {
    submitErrorMessage.value =
      axios.isAxiosError(error) && error.response?.status === 403
        ? 'Você não tem permissão para excluir este parceiro.'
        : 'Não foi possível excluir. Tente novamente.'
    isDeleting.value = false
  }
}
</script>

<template>
  <AppLayout resource="partners">
    <AppBreadcrumb :items="breadcrumb" />

    <LoadingState v-if="isLoading" />
    <ErrorState
      v-else-if="loadErrorMessage"
      :message="loadErrorMessage"
    />

    <template v-else>
      <PageHeader :title="isEditing ? 'Editar parceiro' : 'Novo parceiro'">
        <template
          v-if="isEditing"
          #badge
        >
          <StatusBadge
            :status="isActive ? 'active' : 'inactive'"
            :label="isActive ? 'Ativo' : 'Inativo'"
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
        Parceiro salvo.
      </NoticeBanner>

      <form
        class="card"
        @submit.prevent="handleSubmit"
      >
        <div class="field">
          <label for="partner-name">Nome</label>
          <input
            id="partner-name"
            v-model="name"
            type="text"
            maxlength="150"
            required
          >
          <span
            v-if="fieldErrors.name"
            class="field__error"
          >{{ fieldErrors.name[0] }}</span>
        </div>

        <div class="field">
          <label for="partner-logo">Logo (PNG, JPG ou WebP, até 10 MB)</label>
          <img
            v-if="logoPreview || record"
            class="partner-form__preview"
            :src="logoPreview ?? record?.logo_url"
            :alt="`Logo de ${name || 'parceiro'}`"
          >
          <input
            id="partner-logo"
            type="file"
            accept="image/png,image/jpeg,image/webp"
            :required="!isEditing"
            @change="handleLogoChange"
          >
          <span
            v-if="isEditing"
            class="field__hint"
          >Deixe em branco para manter a logo atual.</span>
          <span
            v-if="logoInputError"
            class="field__error"
          >{{ logoInputError }}</span>
          <span
            v-if="fieldErrors.logo"
            class="field__error"
          >{{ fieldErrors.logo[0] }}</span>
        </div>

        <div class="field">
          <label for="partner-url">Link (opcional)</label>
          <input
            id="partner-url"
            v-model="url"
            type="text"
            inputmode="url"
            maxlength="2048"
            placeholder="https://www.exemplo.com.br"
          >
          <span class="field__hint">Se informado, o cartão do parceiro leva a este endereço.</span>
          <span
            v-if="fieldErrors.url"
            class="field__error"
          >{{ fieldErrors.url[0] }}</span>
        </div>

        <div class="field">
          <label for="partner-position">Ordem</label>
          <input
            id="partner-position"
            v-model="position"
            type="number"
            min="0"
            max="65535"
          >
          <span class="field__hint">Menor aparece primeiro. Vazio, vai para o fim da lista.</span>
          <span
            v-if="fieldErrors.position"
            class="field__error"
          >{{ fieldErrors.position[0] }}</span>
        </div>

        <div class="field field--checkbox">
          <label for="partner-active">
            <input
              id="partner-active"
              v-model="isActive"
              type="checkbox"
            >
            Mostrar no site
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
        class="partner-form__actions"
      >
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
.partner-form__actions {
  display: flex;
  gap: var(--space-3);
  margin-top: var(--space-5);
}

.partner-form__preview {
  display: block;
  width: 12rem;
  height: 7rem;
  margin-bottom: var(--space-2);
  object-fit: contain;
  border: 1px solid var(--color-border, #ddd);
  border-radius: var(--radius-sm, 4px);
}
</style>
