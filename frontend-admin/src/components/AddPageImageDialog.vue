<script setup lang="ts">
import { X } from 'lucide-vue-next'
import { ref, useId, watch } from 'vue'

import AppIcon from '@/components/AppIcon.vue'
import ImageUploadForm from '@/components/ImageUploadForm.vue'
import type { MediaDetailsPayload } from '@/services/media'
import { uploadImageToPage } from '@/services/pageImages'
import type { Media } from '@/types/media'

/**
 * "Adicionar foto" de "Imagens desta página": enviar do computador para a galeria ou para a
 * capa, num diálogo aberto pelo botão do topo da seção. Antes, era um formulário longo
 * empilhado no fim da tela, depois de todas as listas — quem chegava para adicionar uma foto
 * precisava rolar a página inteira para achar onde.
 *
 * Mesmo `<dialog>` nativo do seletor do editor (MediaPickerDialog.vue), pelos mesmos motivos:
 * foco preso dentro, resto da página inerte, Esc fecha e o foco volta a quem abriu.
 */
const props = defineProps<{
  open: boolean
  pageUuid: string
}>()

const emit = defineEmits<{
  close: []
  uploaded: [media: Media, target: 'gallery' | 'cover']
}>()

const uid = useId()
const dialog = ref<HTMLDialogElement | null>(null)
const target = ref<'gallery' | 'cover'>('gallery')
// Muda a cada abertura: o formulário renasce vazio, sem a foto da vez anterior.
const formKey = ref(0)

watch(
  () => props.open,
  (open) => {
    if (!open) {
      dialog.value?.close()

      return
    }

    target.value = 'gallery'
    formKey.value++
    dialog.value?.showModal()
  },
)

function upload(file: File, payload: MediaDetailsPayload): Promise<Media> {
  return uploadImageToPage(props.pageUuid, file, payload, target.value)
}

function handleUploaded(media: Media): void {
  emit('uploaded', media, target.value)
  dialog.value?.close()
}

/** Ver MediaPickerDialog.vue: uma foto solta fora da área não pode abrir no lugar do painel. */
function ignoreStrayFileDrop(event: DragEvent): void {
  if (event.dataTransfer?.types.includes('Files')) {
    event.preventDefault()
  }
}
</script>

<template>
  <dialog
    ref="dialog"
    class="media-picker"
    :aria-labelledby="`${uid}-title`"
    @close="emit('close')"
    @dragover="ignoreStrayFileDrop"
    @drop="ignoreStrayFileDrop"
  >
    <header class="media-picker__header">
      <h2
        :id="`${uid}-title`"
        class="media-picker__title"
      >
        Adicionar foto a esta página
      </h2>
      <button
        type="button"
        class="btn btn--secondary media-picker__close"
        aria-label="Fechar"
        @click="dialog?.close()"
      >
        <AppIcon :icon="X" />
      </button>
    </header>

    <p class="field__hint">
      Para pôr uma foto no meio do texto, use o botão “Imagem” do editor, ou arraste a foto para
      o ponto do texto onde ela deve ficar.
    </p>

    <ImageUploadForm
      :key="formKey"
      :upload="upload"
      :submit-label="target === 'cover' ? 'Enviar para a capa' : 'Enviar para a galeria'"
      @uploaded="handleUploaded"
    >
      <fieldset class="field media-declaration">
        <legend class="field__legend">
          Para onde vai
        </legend>
        <label class="media-declaration__option">
          <input
            v-model="target"
            type="radio"
            :name="`${uid}-target`"
            value="gallery"
          >
          Fim da galeria
        </label>
        <label class="media-declaration__option">
          <input
            v-model="target"
            type="radio"
            :name="`${uid}-target`"
            value="cover"
          >
          Capa, no lugar da atual
        </label>
      </fieldset>
    </ImageUploadForm>
  </dialog>
</template>
