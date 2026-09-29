<script setup lang="ts">
import axios from 'axios'
import { Upload } from 'lucide-vue-next'
import { onBeforeUnmount, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'

import AppBreadcrumb from '@/components/AppBreadcrumb.vue'
import AppIcon from '@/components/AppIcon.vue'
import AppLayout from '@/components/AppLayout.vue'
import MediaDeclarationField from '@/components/MediaDeclarationField.vue'
import NoticeBanner from '@/components/NoticeBanner.vue'
import PageHeader from '@/components/PageHeader.vue'
import { uploadMedia } from '@/services/media'
import type { BreadcrumbItem } from '@/types/breadcrumb'

// Mesmo teto de App\Http\Requests\Media\Concerns\ValidatesImageUpload (em KB lá). Só UX
// imediata: quem valida é a API, inclusive o tipo pelo conteúdo e o teto de megapixels.
const MAX_FILE_BYTES = 10240 * 1024
const ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp']

const router = useRouter()

const breadcrumb: BreadcrumbItem[] = [
  { label: 'Imagens', to: { name: 'media.index' } },
  { label: 'Enviar imagem' },
]

const file = ref<File | null>(null)
const localPreview = ref<string | null>(null)
const fileInputError = ref<string | null>(null)
const alt = ref('')
const caption = ref('')
const depictsAssistedMinor = ref<boolean | null>(null)

const isSaving = ref(false)
const submitErrorMessage = ref<string | null>(null)
const fieldErrors = reactive<Record<string, string[]>>({})

function setPreview(selected: File | null): void {
  if (localPreview.value) {
    URL.revokeObjectURL(localPreview.value)
  }

  localPreview.value = selected ? URL.createObjectURL(selected) : null
}

onBeforeUnmount(() => setPreview(null))

function handleFileChange(event: Event): void {
  const input = event.target as HTMLInputElement
  const selected = input.files?.[0] ?? null
  fileInputError.value = null

  if (selected && !ACCEPTED_TYPES.includes(selected.type)) {
    fileInputError.value = 'A imagem precisa ser JPEG, PNG ou WebP.'
  } else if (selected && selected.size > MAX_FILE_BYTES) {
    fileInputError.value = 'A imagem não pode passar de 10 MB.'
  }

  if (fileInputError.value) {
    input.value = ''
    file.value = null
    setPreview(null)

    return
  }

  file.value = selected
  setPreview(selected)
}

async function handleSubmit(): Promise<void> {
  isSaving.value = true
  submitErrorMessage.value = null
  Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key])

  try {
    const media = await uploadMedia(file.value, {
      alt: alt.value,
      caption: caption.value,
      depicts_assisted_minor: depictsAssistedMinor.value,
    })
    void router.push({ name: 'media.edit', params: { uuid: media.id }, query: { created: '1' } })
  } catch (error) {
    if (axios.isAxiosError(error) && error.response?.status === 422) {
      const body = error.response.data as { errors?: Record<string, string[]> }
      Object.assign(fieldErrors, body.errors ?? {})
      submitErrorMessage.value = 'Corrija os campos indicados antes de enviar.'
    } else if (axios.isAxiosError(error) && error.response?.status === 403) {
      submitErrorMessage.value = 'Você não tem permissão para enviar imagens.'
    } else if (axios.isAxiosError(error) && error.response?.status === 413) {
      submitErrorMessage.value = 'A imagem é grande demais para o servidor aceitar.'
    } else {
      submitErrorMessage.value = 'Não foi possível enviar. Tente novamente.'
    }
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <AppLayout resource="media">
    <AppBreadcrumb :items="breadcrumb" />

    <PageHeader title="Enviar imagem" />

    <NoticeBanner
      v-if="submitErrorMessage"
      variant="error"
    >
      {{ submitErrorMessage }}
    </NoticeBanner>

    <form
      class="card media-form"
      @submit.prevent="handleSubmit"
    >
      <div class="field">
        <label for="media-file">Arquivo (JPEG, PNG ou WebP, até 10 MB)</label>
        <input
          id="media-file"
          type="file"
          accept="image/jpeg,image/png,image/webp"
          required
          @change="handleFileChange"
        >
        <span class="field__hint">
          A localização e os demais dados da câmera são apagados no envio. O nome do arquivo não é
          guardado.
        </span>
        <span
          v-if="fileInputError"
          class="field__error"
        >{{ fileInputError }}</span>
        <span
          v-if="fieldErrors.file"
          class="field__error"
        >{{ fieldErrors.file[0] }}</span>
      </div>

      <img
        v-if="localPreview"
        :src="localPreview"
        alt="Pré-visualização da imagem escolhida"
        class="media-form__preview"
      >

      <div class="field">
        <label for="media-alt">Texto alternativo</label>
        <input
          id="media-alt"
          v-model="alt"
          type="text"
          maxlength="255"
          required
        >
        <span class="field__hint">
          O que a imagem mostra, para quem não pode vê-la. Ex.: "Fachada da sede com o letreiro da
          instituição sobre a entrada".
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
        />
        <span
          v-if="fieldErrors.caption"
          class="field__error"
        >{{ fieldErrors.caption[0] }}</span>
      </div>

      <MediaDeclarationField
        v-model="depictsAssistedMinor"
        :error="fieldErrors.depicts_assisted_minor?.[0]"
      />

      <button
        type="submit"
        class="btn btn--primary"
        :disabled="isSaving"
      >
        <AppIcon :icon="Upload" />
        {{ isSaving ? 'Enviando…' : 'Enviar' }}
      </button>
    </form>
  </AppLayout>
</template>
