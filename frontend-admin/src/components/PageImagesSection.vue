<script setup lang="ts">
import axios from 'axios'
import { ImagePlus, Images } from 'lucide-vue-next'
import { reactive, ref, watch } from 'vue'

import AppIcon from '@/components/AppIcon.vue'
import MediaDeclarationField from '@/components/MediaDeclarationField.vue'
import MediaPickerDialog from '@/components/MediaPickerDialog.vue'
import NoticeBanner from '@/components/NoticeBanner.vue'
import PageImageItem from '@/components/PageImageItem.vue'
import { fetchPageImages, setPageCover, uploadImageToPage } from '@/services/pageImages'
import type { Media, PageImages } from '@/types/media'

/**
 * "Imagens desta página": a porta da página para a mesma biblioteca de /admin/imagens (ver
 * docs/decisoes/0025-imagens-da-pagina.md). Lista só o que ESTA página usa — capa, galeria e
 * as imagens do texto salvo — e deixa enviar, substituir e corrigir o texto no mesmo lugar.
 *
 * Fica fora do formulário da página, com salvamento próprio: cada ação aqui já está no ar
 * quando termina, e não depende do "Salvar" do texto.
 */
const props = defineProps<{
  pageUuid: string
  /** Muda quando a página é salva: as imagens do texto são as do conteúdo SALVO. */
  refreshKey: string | null
}>()

const MAX_FILE_BYTES = 10240 * 1024
const ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp']

const images = ref<PageImages | null>(null)
const isLoading = ref(true)
const loadErrorMessage = ref<string | null>(null)
const notice = ref<string | null>(null)

const file = ref<File | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)
const alt = ref('')
const caption = ref('')
const depictsAssistedMinor = ref<boolean | null>(null)
const target = ref<'gallery' | 'cover'>('gallery')
const isUploading = ref(false)
const uploadErrorMessage = ref<string | null>(null)
const fieldErrors = reactive<Record<string, string[]>>({})

async function load(): Promise<void> {
  loadErrorMessage.value = null

  try {
    images.value = await fetchPageImages(props.pageUuid)
  } catch (error) {
    loadErrorMessage.value =
      axios.isAxiosError(error) && error.response?.status === 403
        ? 'Você não tem permissão para mexer nas imagens desta página.'
        : 'Não foi possível carregar as imagens desta página. Tente novamente.'
  } finally {
    isLoading.value = false
  }
}

watch(() => [props.pageUuid, props.refreshKey], () => void load(), { immediate: true })

function onChanged(message: string): void {
  notice.value = message
  void load()
}

const pickerOpen = ref(false)
const coverErrorMessage = ref<string | null>(null)

async function chooseCover(media: Media): Promise<void> {
  coverErrorMessage.value = null
  notice.value = null

  try {
    await setPageCover(props.pageUuid, media.id)
    onChanged('Capa trocada. O site já mostra a nova.')
  } catch (error) {
    const body = axios.isAxiosError(error) ? (error.response?.data as { errors?: Record<string, string[]> }) : null
    coverErrorMessage.value =
      body?.errors?.media?.[0] ??
      (axios.isAxiosError(error) && error.response?.status === 403
        ? 'Você não tem permissão para trocar a capa desta página.'
        : 'Não foi possível trocar a capa. Tente novamente.')
  }
}

function clearUploadErrors(): void {
  uploadErrorMessage.value = null
  Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key])
}

function handleFileChange(event: Event): void {
  const input = event.target as HTMLInputElement
  const selected = input.files?.[0] ?? null
  delete fieldErrors.file

  if (selected && (!ACCEPTED_TYPES.includes(selected.type) || selected.size > MAX_FILE_BYTES)) {
    fieldErrors.file = [
      selected.size > MAX_FILE_BYTES ? 'A imagem não pode passar de 10 MB.' : 'A imagem precisa ser JPEG, PNG ou WebP.',
    ]
    input.value = ''
    file.value = null

    return
  }

  file.value = selected
}

async function upload(): Promise<void> {
  isUploading.value = true
  notice.value = null
  clearUploadErrors()

  try {
    await uploadImageToPage(
      props.pageUuid,
      file.value,
      {
        alt: alt.value,
        caption: caption.value,
        depicts_assisted_minor: depictsAssistedMinor.value,
      },
      target.value,
    )
    const sentTo = target.value

    file.value = null
    alt.value = ''
    caption.value = ''
    depictsAssistedMinor.value = null
    target.value = 'gallery'

    if (fileInput.value) {
      fileInput.value.value = ''
    }

    onChanged(
      sentTo === 'cover'
        ? 'Imagem enviada e posta na capa. Ela já aparece no site.'
        : 'Imagem enviada e posta no fim da galeria. Ela já aparece no site.',
    )
  } catch (error) {
    if (axios.isAxiosError(error) && error.response?.status === 422) {
      const body = error.response.data as { errors?: Record<string, string[]> }
      Object.assign(fieldErrors, body.errors ?? {})
      uploadErrorMessage.value = 'Corrija os campos indicados.'
    } else {
      uploadErrorMessage.value =
        axios.isAxiosError(error) && error.response?.status === 403
          ? 'Você não tem permissão para enviar imagens.'
          : 'Não foi possível enviar. Tente novamente.'
    }
  } finally {
    isUploading.value = false
  }
}
</script>

<template>
  <section
    class="card page-images"
    aria-labelledby="page-images-title"
  >
    <h2
      id="page-images-title"
      class="page-images__title"
    >
      Imagens desta página
    </h2>
    <p class="field__hint">
      Cada mudança aqui vai para o site na hora, sem precisar salvar a página. As imagens são as
      mesmas do acervo completo:
      <RouterLink :to="{ name: 'media.index' }">
        biblioteca de imagens
      </RouterLink>
    </p>

    <NoticeBanner
      v-if="notice"
      variant="info"
    >
      {{ notice }}
    </NoticeBanner>

    <p
      v-if="isLoading"
      class="field__hint"
    >
      Carregando imagens…
    </p>
    <p
      v-else-if="loadErrorMessage"
      class="field__error"
      role="alert"
    >
      {{ loadErrorMessage }}
    </p>

    <template v-else-if="images">
      <h3 class="page-images__group">
        Galeria
      </h3>
      <p class="field__hint">
        Aparece depois do texto, nesta ordem.
      </p>
      <ul
        v-if="images.gallery.length > 0"
        class="page-images__list"
        aria-label="Galeria"
      >
        <PageImageItem
          v-for="media in images.gallery"
          :key="`gallery-${media.id}`"
          :media="media"
          role="gallery"
          :page-uuid="pageUuid"
          @changed="onChanged"
        />
      </ul>
      <p
        v-else
        class="page-images__empty"
      >
        A galeria está vazia.
      </p>

      <h3 class="page-images__group">
        Capa
      </h3>
      <p class="field__hint">
        Não aparece nesta página: representa a página em outros lugares do site.
      </p>
      <p
        v-if="images.cover_shown_on.length > 0"
        class="page-images__shown-on"
      >
        A capa desta página aparece em {{ images.cover_shown_on.join(' e em ') }}. Trocar ou
        tirar a capa muda isso na hora.
      </p>
      <p
        v-else
        class="field__hint"
      >
        Hoje nenhum lugar do site mostra a capa desta página.
      </p>
      <p
        v-if="coverErrorMessage"
        class="field__error"
        role="alert"
      >
        {{ coverErrorMessage }}
      </p>
      <ul
        v-if="images.cover"
        class="page-images__list"
        aria-label="Capa"
      >
        <PageImageItem
          :media="images.cover"
          role="cover"
          :page-uuid="pageUuid"
          @changed="onChanged"
        />
      </ul>
      <p
        v-else
        class="page-images__empty"
      >
        Esta página não tem capa.
      </p>
      <button
        type="button"
        class="btn btn--secondary page-images__choose-cover"
        @click="pickerOpen = true"
      >
        <AppIcon :icon="Images" />
        {{ images.cover ? 'Trocar a capa por imagem da biblioteca' : 'Escolher a capa na biblioteca' }}
      </button>
      <MediaPickerDialog
        :open="pickerOpen"
        :editing="null"
        purpose="cover"
        @pick="chooseCover"
        @close="pickerOpen = false"
      />

      <template v-if="images.content.length > 0">
        <h3 class="page-images__group">
          No meio do texto
        </h3>
        <ul
          class="page-images__list"
          aria-label="No meio do texto"
        >
          <PageImageItem
            v-for="media in images.content"
            :key="`content-${media.id}`"
            :media="media"
            role="content"
            :page-uuid="pageUuid"
            @changed="onChanged"
          />
        </ul>
      </template>

      <form
        class="page-images__upload"
        @submit.prevent="upload"
      >
        <h3 class="page-images__group">
          Enviar imagem
        </h3>

        <p
          v-if="uploadErrorMessage"
          class="field__error"
          role="alert"
        >
          {{ uploadErrorMessage }}
        </p>

        <div class="field">
          <label for="page-image-upload-file">Arquivo (JPEG, PNG ou WebP, até 10 MB)</label>
          <input
            id="page-image-upload-file"
            ref="fileInput"
            type="file"
            accept="image/jpeg,image/png,image/webp"
            @change="handleFileChange"
          >
          <span
            v-if="fieldErrors.file"
            class="field__error"
          >{{ fieldErrors.file[0] }}</span>
        </div>

        <div class="field">
          <label for="page-image-upload-alt">Texto alternativo</label>
          <input
            id="page-image-upload-alt"
            v-model="alt"
            type="text"
            maxlength="255"
          >
          <span class="field__hint">Descreva a imagem para quem não pode vê-la.</span>
          <span
            v-if="fieldErrors.alt"
            class="field__error"
          >{{ fieldErrors.alt[0] }}</span>
        </div>

        <div class="field">
          <label for="page-image-upload-caption">Legenda (opcional)</label>
          <input
            id="page-image-upload-caption"
            v-model="caption"
            type="text"
            maxlength="500"
          >
          <span
            v-if="fieldErrors.caption"
            class="field__error"
          >{{ fieldErrors.caption[0] }}</span>
        </div>

        <MediaDeclarationField
          v-model="depictsAssistedMinor"
          :error="fieldErrors.depicts_assisted_minor?.[0]"
        />

        <fieldset class="field media-declaration">
          <legend class="field__legend">
            Para onde vai
          </legend>
          <label class="media-declaration__option">
            <input
              v-model="target"
              type="radio"
              name="page-image-target"
              value="gallery"
            >
            Fim da galeria
          </label>
          <label class="media-declaration__option">
            <input
              v-model="target"
              type="radio"
              name="page-image-target"
              value="cover"
            >
            Capa, no lugar da atual
          </label>
        </fieldset>

        <button
          type="submit"
          class="btn btn--primary"
          :disabled="isUploading"
        >
          <AppIcon :icon="ImagePlus" />
          {{ isUploading ? 'Enviando…' : target === 'cover' ? 'Enviar para a capa' : 'Enviar para a galeria' }}
        </button>
      </form>
    </template>
  </section>
</template>
