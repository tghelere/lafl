<script setup lang="ts">
import axios from 'axios'
import { Replace, Save, Trash2 } from 'lucide-vue-next'
import { computed, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import AppBreadcrumb from '@/components/AppBreadcrumb.vue'
import AppIcon from '@/components/AppIcon.vue'
import AppLayout from '@/components/AppLayout.vue'
import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import MediaDeclarationField from '@/components/MediaDeclarationField.vue'
import NoticeBanner from '@/components/NoticeBanner.vue'
import PageHeader from '@/components/PageHeader.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import { deleteMedia, fetchMedia, replaceMediaFile, updateMediaDetails } from '@/services/media'
import type { BreadcrumbItem } from '@/types/breadcrumb'
import type { Media } from '@/types/media'

const MAX_FILE_BYTES = 10240 * 1024
const ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp']

const route = useRoute()
const router = useRouter()

const uuid = computed(() => String(route.params.uuid))

const record = ref<Media | null>(null)
const isLoading = ref(true)
const loadErrorMessage = ref<string | null>(null)

const alt = ref('')
const caption = ref('')
const depictsAssistedMinor = ref<boolean | null>(null)

const replacement = ref<File | null>(null)
const replaceInput = ref<HTMLInputElement | null>(null)

const isSaving = ref(false)
const isReplacing = ref(false)
const isDeleting = ref(false)
const notice = ref<string | null>(null)
const submitErrorMessage = ref<string | null>(null)
const fieldErrors = reactive<Record<string, string[]>>({})

const breadcrumb = computed<BreadcrumbItem[]>(() => [
  { label: 'Imagens', to: { name: 'media.index' } },
  { label: record.value?.alt ?? '', loading: isLoading.value },
  { label: 'Editar' },
])

function fill(media: Media): void {
  record.value = media
  alt.value = media.alt
  caption.value = media.caption ?? ''
  depictsAssistedMinor.value = media.depicts_assisted_minor
}

function clearMessages(): void {
  notice.value = null
  submitErrorMessage.value = null
  Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key])
}

/**
 * Mensagem de erro vem da API sempre que ela manda uma (CLAUDE.md, regra 1) — inclusive a
 * recusa de exclusão, que diz em quais páginas a imagem está.
 */
function applyError(error: unknown, fallback: string): void {
  if (axios.isAxiosError(error) && error.response?.status === 422) {
    const body = error.response.data as { message?: string; errors?: Record<string, string[]> }
    Object.assign(fieldErrors, body.errors ?? {})
    submitErrorMessage.value = body.errors?.media?.[0] ?? 'Corrija os campos indicados.'

    return
  }

  submitErrorMessage.value =
    axios.isAxiosError(error) && error.response?.status === 403
      ? 'Você não tem permissão para fazer isso com esta imagem.'
      : fallback
}

async function load(): Promise<void> {
  isLoading.value = true
  loadErrorMessage.value = null
  clearMessages()

  try {
    fill(await fetchMedia(uuid.value))

    if (route.query.created) {
      notice.value = 'Imagem enviada.'
    }
  } catch (error) {
    loadErrorMessage.value =
      axios.isAxiosError(error) && error.response?.status === 404
        ? 'Imagem não encontrada.'
        : axios.isAxiosError(error) && error.response?.status === 403
          ? 'Você não tem permissão para ver esta imagem.'
          : 'Não foi possível carregar a imagem. Tente novamente.'
  } finally {
    isLoading.value = false
  }
}

// O vue-router reaproveita a instância ao ir de uma imagem para outra — carregar pelo
// parâmetro, e não só na montagem (armadilha registrada no CLAUDE.md).
watch(uuid, () => void load(), { immediate: true })

async function handleSave(): Promise<void> {
  if (depictsAssistedMinor.value === true && record.value?.depicts_assisted_minor === false) {
    const confirmed = window.confirm(
      'Marcar esta imagem como foto de criança ou adolescente atendido a tira do site imediatamente, em todas as páginas. A marcação não pode ser desfeita. Continuar?',
    )

    if (!confirmed) {
      return
    }
  }

  isSaving.value = true
  clearMessages()

  try {
    fill(
      await updateMediaDetails(uuid.value, {
        alt: alt.value,
        caption: caption.value,
        depicts_assisted_minor: depictsAssistedMinor.value,
      }),
    )
    notice.value = 'Dados da imagem salvos.'
  } catch (error) {
    applyError(error, 'Não foi possível salvar. Tente novamente.')
  } finally {
    isSaving.value = false
  }
}

function handleReplacementChange(event: Event): void {
  const input = event.target as HTMLInputElement
  const selected = input.files?.[0] ?? null
  delete fieldErrors.file

  if (selected && (!ACCEPTED_TYPES.includes(selected.type) || selected.size > MAX_FILE_BYTES)) {
    fieldErrors.file = [
      selected.size > MAX_FILE_BYTES ? 'A imagem não pode passar de 10 MB.' : 'A imagem precisa ser JPEG, PNG ou WebP.',
    ]
    input.value = ''
    replacement.value = null

    return
  }

  replacement.value = selected
}

async function handleReplace(): Promise<void> {
  if (!replacement.value) {
    return
  }

  isReplacing.value = true
  clearMessages()

  try {
    fill(await replaceMediaFile(uuid.value, replacement.value))
    replacement.value = null

    if (replaceInput.value) {
      replaceInput.value.value = ''
    }

    notice.value = 'Arquivo substituído. O endereço da imagem continua o mesmo, e as páginas que a usam já mostram o novo.'
  } catch (error) {
    applyError(error, 'Não foi possível substituir o arquivo. Tente novamente.')
  } finally {
    isReplacing.value = false
  }
}

async function handleDelete(): Promise<void> {
  if (!window.confirm('Excluir esta imagem? Os arquivos são apagados em definitivo.')) {
    return
  }

  isDeleting.value = true
  clearMessages()

  try {
    await deleteMedia(uuid.value)
    void router.push({ name: 'media.index', query: { deleted: '1' } })
  } catch (error) {
    applyError(error, 'Não foi possível excluir. Tente novamente.')
    isDeleting.value = false
  }
}

function formatSize(bytes: number): string {
  return bytes >= 1024 * 1024 ? `${(bytes / (1024 * 1024)).toFixed(1)} MB` : `${Math.round(bytes / 1024)} KB`
}
</script>

<template>
  <AppLayout resource="media">
    <AppBreadcrumb :items="breadcrumb" />

    <LoadingState v-if="isLoading" />
    <ErrorState
      v-else-if="loadErrorMessage"
      :message="loadErrorMessage"
    />

    <template v-else-if="record">
      <PageHeader title="Editar imagem">
        <template #badge>
          <StatusBadge
            :status="record.publishable ? 'published' : 'discarded'"
            :label="record.publishable ? 'Pode ir para o site' : 'Fora do site'"
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
        v-if="notice"
        variant="info"
      >
        {{ notice }}
      </NoticeBanner>
      <NoticeBanner
        v-if="!record.publishable"
        variant="error"
      >
        Esta imagem foi marcada como foto de criança ou adolescente atendido e não aparece no site.
        Remova-a das páginas em “Onde é usada” e exclua-a da biblioteca.
      </NoticeBanner>

      <div class="media-detail">
        <figure class="media-detail__figure">
          <img
            :key="record.version"
            :src="record.preview_url"
            :alt="record.alt"
            :width="record.width"
            :height="record.height"
            class="media-detail__image"
          >
          <figcaption class="media-detail__facts">
            {{ record.width }} × {{ record.height }} px · {{ formatSize(record.size) }} · larguras
            geradas: {{ record.widths.join(', ') }}
          </figcaption>
        </figure>

        <section
          class="card media-detail__usages"
          aria-labelledby="media-usages-title"
        >
          <h2
            id="media-usages-title"
            class="media-detail__section-title"
          >
            Onde é usada
          </h2>
          <ul
            v-if="record.usages && record.usages.length > 0"
            class="media-detail__usage-list"
          >
            <li
              v-for="usage in record.usages"
              :key="usage.uuid"
            >
              <RouterLink :to="{ name: 'pages.edit', params: { uuid: usage.uuid } }">
                {{ usage.title }}
              </RouterLink>
              <span class="field__hint">/{{ usage.slug }}</span>
            </li>
          </ul>
          <p
            v-else
            class="field__hint"
          >
            Nenhuma página usa esta imagem.
          </p>
        </section>
      </div>

      <form
        class="card media-form"
        aria-label="Dados da imagem"
        @submit.prevent="handleSave"
      >
        <div class="field">
          <label for="media-alt">Texto alternativo</label>
          <input
            id="media-alt"
            v-model="alt"
            type="text"
            maxlength="255"
            required
            :disabled="!record.can.update"
          >
          <span class="field__hint">
            É o texto oferecido ao inserir a imagem numa página. O que já foi inserido não muda
            sozinho.
          </span>
          <span
            v-if="fieldErrors.alt"
            class="field__error"
          >{{ fieldErrors.alt[0] }}</span>
        </div>

        <div class="field">
          <label for="media-caption">Legenda (opcional)</label>
          <textarea
            id="media-caption"
            v-model="caption"
            maxlength="500"
            rows="2"
            :disabled="!record.can.update"
          />
          <span
            v-if="fieldErrors.caption"
            class="field__error"
          >{{ fieldErrors.caption[0] }}</span>
        </div>

        <MediaDeclarationField
          v-model="depictsAssistedMinor"
          :locked="record.depicts_assisted_minor"
          :error="fieldErrors.depicts_assisted_minor?.[0]"
        />

        <button
          v-if="record.can.update"
          type="submit"
          class="btn btn--primary"
          :disabled="isSaving"
        >
          <AppIcon :icon="Save" />
          {{ isSaving ? 'Salvando…' : 'Salvar' }}
        </button>
      </form>

      <section
        v-if="record.can.update"
        class="card media-form"
        aria-labelledby="media-replace-title"
      >
        <h2
          id="media-replace-title"
          class="media-detail__section-title"
        >
          Substituir arquivo
        </h2>
        <div class="field">
          <label for="media-replace-file">Novo arquivo (JPEG, PNG ou WebP, até 10 MB)</label>
          <input
            id="media-replace-file"
            ref="replaceInput"
            type="file"
            accept="image/jpeg,image/png,image/webp"
            @change="handleReplacementChange"
          >
          <span class="field__hint">
            O endereço da imagem continua o mesmo: as páginas que a usam passam a mostrar o arquivo
            novo sem precisar de edição. O arquivo anterior é apagado.
          </span>
          <span
            v-if="fieldErrors.file"
            class="field__error"
          >{{ fieldErrors.file[0] }}</span>
        </div>
        <button
          type="button"
          class="btn btn--secondary"
          :disabled="!replacement || isReplacing"
          @click="handleReplace"
        >
          <AppIcon :icon="Replace" />
          {{ isReplacing ? 'Substituindo…' : 'Substituir arquivo' }}
        </button>
      </section>

      <div
        v-if="record.can.delete"
        class="media-detail__danger"
      >
        <button
          type="button"
          class="btn btn--danger"
          :disabled="isDeleting"
          @click="handleDelete"
        >
          <AppIcon :icon="Trash2" />
          {{ isDeleting ? 'Excluindo…' : 'Excluir imagem' }}
        </button>
      </div>
    </template>
  </AppLayout>
</template>
