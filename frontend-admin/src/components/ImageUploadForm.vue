<script setup lang="ts">
import axios from 'axios'
import { ImageUp, RefreshCw, Upload } from 'lucide-vue-next'
import { onBeforeUnmount, reactive, ref, useId, watch } from 'vue'

import AppIcon from '@/components/AppIcon.vue'
import MediaDeclarationField from '@/components/MediaDeclarationField.vue'
import type { MediaDetailsPayload } from '@/services/media'
import type { Media } from '@/types/media'

/**
 * Enviar uma foto do computador sem sair da tela: área de arrastar e soltar (ou clicar para
 * escolher), pré-visualização, descrição e a declaração obrigatória. Usado dentro dos diálogos
 * do editor de página — o seletor do texto e o da capa (MediaPickerDialog.vue) e o "Adicionar
 * foto" de "Imagens desta página" (AddPageImageDialog.vue).
 *
 * Quem envia é o `upload` recebido: a biblioteca (foto para o texto) ou a página (galeria,
 * capa). O formulário é o mesmo porque a API pede o mesmo nos dois casos — arquivo, texto
 * alternativo e a declaração, num pedido só (StoreMediaRequest, StorePageImageRequest). Por
 * isso "enviar" e "descrever" são um passo só: a descrição vai junto do arquivo.
 *
 * Tipo e tamanho são conferidos aqui só para a resposta ser imediata. Quem decide é a API,
 * inclusive o tipo pelo conteúdo, o teto de megapixels e a declaração — e as mensagens de erro
 * são as dela.
 */
const props = defineProps<{
  upload: (file: File, payload: MediaDetailsPayload) => Promise<Media>
  submitLabel: string
  /** Arquivo que já chegou solto (no corpo do texto): o formulário começa com ele escolhido. */
  initialFile?: File | null
}>()

const emit = defineEmits<{
  uploaded: [media: Media, details: { alt: string; caption: string }]
}>()

// Mesmo teto de App\Http\Requests\Media\Concerns\ValidatesImageUpload (em KB lá).
const MAX_FILE_BYTES = 10240 * 1024
const ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp']

const uid = useId()
const fileInput = ref<HTMLInputElement | null>(null)
const altInput = ref<HTMLInputElement | null>(null)

const file = ref<File | null>(null)
const localPreview = ref<string | null>(null)
const isDragging = ref(false)
const alt = ref('')
const caption = ref('')
const credit = ref('')
const depictsAssistedMinor = ref<boolean | null>(null)

const isUploading = ref(false)
const errorMessage = ref<string | null>(null)
const fieldErrors = reactive<Record<string, string[]>>({})

function setPreview(selected: File | null): void {
  if (localPreview.value) {
    URL.revokeObjectURL(localPreview.value)
  }

  localPreview.value = selected ? URL.createObjectURL(selected) : null
}

onBeforeUnmount(() => setPreview(null))

function accept(selected: File | null): void {
  delete fieldErrors.file
  errorMessage.value = null

  if (selected && !ACCEPTED_TYPES.includes(selected.type)) {
    fieldErrors.file = ['A imagem precisa ser JPEG, PNG ou WebP.']
  } else if (selected && selected.size > MAX_FILE_BYTES) {
    fieldErrors.file = ['A imagem não pode passar de 10 MB.']
  }

  if (fieldErrors.file) {
    if (fileInput.value) {
      fileInput.value.value = ''
    }

    return
  }

  file.value = selected
  setPreview(selected)

  if (selected) {
    // Escolhida a foto, o próximo passo é descrevê-la: o foco vai direto ao campo.
    setTimeout(() => altInput.value?.focus())
  }
}

watch(
  () => props.initialFile,
  (initial) => {
    if (initial) {
      accept(initial)
    }
  },
  { immediate: true },
)

function handleFileChange(event: Event): void {
  accept((event.target as HTMLInputElement).files?.[0] ?? null)
}

function handleDrop(event: DragEvent): void {
  isDragging.value = false
  const dropped = event.dataTransfer?.files?.[0] ?? null

  if (dropped) {
    accept(dropped)
  }
}

function chooseAnother(): void {
  fileInput.value?.click()
}

async function submit(): Promise<void> {
  if (!file.value) {
    fieldErrors.file = ['Escolha a foto antes de enviar.']

    return
  }

  isUploading.value = true
  errorMessage.value = null
  Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key])

  try {
    const media = await props.upload(file.value, {
      alt: alt.value,
      caption: caption.value,
      credit: credit.value,
      depicts_assisted_minor: depictsAssistedMinor.value,
    })
    emit('uploaded', media, { alt: alt.value.trim(), caption: caption.value.trim() })
  } catch (error) {
    if (axios.isAxiosError(error) && error.response?.status === 422) {
      const body = error.response.data as { errors?: Record<string, string[]> }
      Object.assign(fieldErrors, body.errors ?? {})
      errorMessage.value = body.errors?.media?.[0] ?? 'Corrija os campos indicados.'
    } else if (axios.isAxiosError(error) && error.response?.status === 403) {
      errorMessage.value = 'Você não tem permissão para enviar fotos.'
    } else if (axios.isAxiosError(error) && error.response?.status === 413) {
      errorMessage.value = 'A foto é grande demais para o servidor aceitar.'
    } else {
      errorMessage.value = 'Não foi possível enviar. Tente novamente.'
    }
  } finally {
    isUploading.value = false
  }
}
</script>

<template>
  <form
    class="image-upload"
    novalidate
    @submit.prevent="submit"
  >
    <p
      v-if="errorMessage"
      class="field__error"
      role="alert"
    >
      {{ errorMessage }}
    </p>

    <!-- A área inteira é o <label> do campo de arquivo: clicar nela abre o seletor do
         sistema, e o teclado chega pelo próprio campo (visualmente escondido, não removido). -->
    <label
      v-show="!file"
      class="image-upload__drop"
      :class="{ 'image-upload__drop--active': isDragging }"
      :for="`${uid}-file`"
      @dragenter.prevent="isDragging = true"
      @dragover.prevent="isDragging = true"
      @dragleave="isDragging = false"
      @drop.prevent="handleDrop"
    >
      <AppIcon
        :icon="ImageUp"
        class="image-upload__drop-icon"
      />
      <span class="image-upload__drop-title">Escolher foto do computador</span>
      <span class="image-upload__drop-text">ou arraste a foto e solte aqui</span>
      <span class="field__hint">JPEG, PNG ou WebP, até 10 MB.</span>
    </label>
    <input
      :id="`${uid}-file`"
      ref="fileInput"
      type="file"
      accept="image/jpeg,image/png,image/webp"
      class="visually-hidden"
      @change="handleFileChange"
    >
    <span
      v-if="fieldErrors.file"
      class="field__error"
    >{{ fieldErrors.file[0] }}</span>

    <template v-if="file">
      <div class="image-upload__chosen">
        <img
          v-if="localPreview"
          :src="localPreview"
          alt="A foto escolhida"
          class="image-upload__preview"
        >
        <button
          type="button"
          class="btn btn--secondary"
          @click="chooseAnother"
        >
          <AppIcon :icon="RefreshCw" />
          Escolher outra foto
        </button>
      </div>
      <p class="field__hint">
        A localização e os dados da câmera são apagados no envio. O nome do arquivo não é
        guardado.
      </p>

      <div class="field">
        <label :for="`${uid}-alt`">Descrição da foto</label>
        <input
          :id="`${uid}-alt`"
          ref="altInput"
          v-model="alt"
          type="text"
          maxlength="255"
        >
        <span class="field__hint">
          Diga o que aparece na foto, como se contasse a alguém que não pode vê-la. Ela é lida em
          voz alta para quem não enxerga a tela. Ex.: “Crianças plantando mudas na horta”.
        </span>
        <span
          v-if="fieldErrors.alt"
          class="field__error"
        >{{ fieldErrors.alt[0] }}</span>
      </div>

      <div class="field">
        <label :for="`${uid}-caption`">Legenda (opcional)</label>
        <input
          :id="`${uid}-caption`"
          v-model="caption"
          type="text"
          maxlength="500"
        >
        <span class="field__hint">Aparece escrita embaixo da foto, no site.</span>
        <span
          v-if="fieldErrors.caption"
          class="field__error"
        >{{ fieldErrors.caption[0] }}</span>
      </div>

      <div class="field">
        <label :for="`${uid}-credit`">Crédito (opcional)</label>
        <input
          :id="`${uid}-credit`"
          v-model="credit"
          type="text"
          maxlength="255"
        >
        <span class="field__hint">Quem tirou a foto ou de onde ela veio. Aparece quando alguém amplia a foto no site.</span>
        <span
          v-if="fieldErrors.credit"
          class="field__error"
        >{{ fieldErrors.credit[0] }}</span>
      </div>

      <MediaDeclarationField
        v-model="depictsAssistedMinor"
        :error="fieldErrors.depicts_assisted_minor?.[0]"
      />

      <slot />

      <div class="media-picker__actions">
        <button
          type="submit"
          class="btn btn--primary"
          :disabled="isUploading"
        >
          <AppIcon :icon="Upload" />
          {{ isUploading ? 'Enviando…' : submitLabel }}
        </button>
      </div>
    </template>
  </form>
</template>
