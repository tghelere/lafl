<script setup lang="ts">
import axios from 'axios'
import { ExternalLink, Pencil, Replace, Save, X } from 'lucide-vue-next'
import { computed, reactive, ref } from 'vue'

import AppIcon from '@/components/AppIcon.vue'
import { replaceMediaFile, updateMediaDetails } from '@/services/media'
import { removeImageFromPage } from '@/services/pageImages'
import type { Media } from '@/types/media'

/**
 * Uma imagem em "Imagens desta página". Tudo o que ela faz passa pelas rotas da biblioteca
 * (substituir, texto alternativo e legenda) ou pela ligação com a página (tirar da capa ou da
 * galeria). Não existe cópia da imagem por página: corrigir o texto aqui corrige em todas as
 * páginas que a usam, e a tela diz isso quando é o caso.
 *
 * A imagem do meio do texto não tem "editar texto": o texto alternativo dela está gravado no
 * conteúdo da página e se edita no editor (docs/decisoes/0025-imagens-da-pagina.md, item 5).
 */
const props = defineProps<{
  media: Media
  role: 'cover' | 'gallery' | 'content'
  pageUuid: string
}>()

const emit = defineEmits<{
  /** Algo mudou; a seção recarrega a lista e mostra a mensagem. */
  changed: [message: string]
}>()

const MAX_FILE_BYTES = 10240 * 1024
const ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp']

const idBase = computed(() => `page-image-${props.role}-${props.media.id}`)
const isEditing = ref(false)
const alt = ref(props.media.alt)
const caption = ref(props.media.caption ?? '')
const replacement = ref<File | null>(null)
const isBusy = ref(false)
const errorMessage = ref<string | null>(null)
const fieldErrors = reactive<Record<string, string[]>>({})

const otherPages = computed(() => (props.media.usages ?? []).filter((usage) => usage.uuid !== props.pageUuid))

const removeLabel = computed(() => (props.role === 'cover' ? 'Tirar da capa' : 'Tirar da galeria'))

function clearErrors(): void {
  errorMessage.value = null
  Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key])
}

/** A mensagem é a da API sempre que ela manda uma (CLAUDE.md, regra 1). */
function applyError(error: unknown, fallback: string): void {
  if (axios.isAxiosError(error) && error.response?.status === 422) {
    const body = error.response.data as { errors?: Record<string, string[]> }
    Object.assign(fieldErrors, body.errors ?? {})
    errorMessage.value = body.errors?.media?.[0] ?? body.errors?.file?.[0] ?? 'Corrija os campos indicados.'

    return
  }

  errorMessage.value =
    axios.isAxiosError(error) && error.response?.status === 403 ? 'Você não tem permissão para fazer isso.' : fallback
}

function startEditing(): void {
  alt.value = props.media.alt
  caption.value = props.media.caption ?? ''
  clearErrors()
  isEditing.value = true
}

async function saveDetails(): Promise<void> {
  isBusy.value = true
  clearErrors()

  try {
    // A declaração vai como está: marcar a imagem como de assistido é feito na biblioteca, com
    // confirmação própria, e não por esta tela.
    await updateMediaDetails(props.media.id, {
      alt: alt.value,
      caption: caption.value,
      depicts_assisted_minor: props.media.depicts_assisted_minor,
    })
    isEditing.value = false
    emit('changed', 'Texto da imagem salvo.')
  } catch (error) {
    applyError(error, 'Não foi possível salvar. Tente novamente.')
  } finally {
    isBusy.value = false
  }
}

function handleFileChange(event: Event): void {
  const input = event.target as HTMLInputElement
  const selected = input.files?.[0] ?? null
  clearErrors()

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

async function replace(): Promise<void> {
  if (!replacement.value) {
    return
  }

  isBusy.value = true
  clearErrors()

  try {
    await replaceMediaFile(props.media.id, replacement.value)
    replacement.value = null
    emit('changed', 'Arquivo substituído. O site já mostra o novo em todo lugar onde a imagem aparece.')
  } catch (error) {
    applyError(error, 'Não foi possível substituir o arquivo. Tente novamente.')
  } finally {
    isBusy.value = false
  }
}

async function remove(): Promise<void> {
  if (props.role === 'content') {
    return
  }

  isBusy.value = true
  clearErrors()

  try {
    await removeImageFromPage(props.pageUuid, props.media.id, props.role)
    emit('changed', props.role === 'cover' ? 'Imagem tirada da capa.' : 'Imagem tirada da galeria. Ela continua na biblioteca.')
  } catch (error) {
    applyError(error, 'Não foi possível tirar a imagem. Tente novamente.')
  } finally {
    isBusy.value = false
  }
}
</script>

<template>
  <li class="page-image">
    <img
      :key="media.version"
      :src="media.preview_url"
      :alt="media.alt"
      :width="media.width"
      :height="media.height"
      loading="lazy"
      class="page-image__thumb"
    >

    <div class="page-image__body">
      <p class="page-image__alt">
        {{ media.alt }}
      </p>
      <p
        v-if="media.caption"
        class="page-image__caption"
      >
        Legenda: {{ media.caption }}
      </p>
      <p
        v-if="!media.publishable"
        class="page-image__warning"
      >
        Marcada como foto de criança ou adolescente atendido: não aparece no site.
      </p>
      <p
        v-if="otherPages.length > 0"
        class="field__hint"
      >
        Também usada em: {{ otherPages.map((usage) => usage.title).join(', ') }}. O que mudar aqui
        vale lá também.
      </p>
      <p
        v-if="role === 'content'"
        class="field__hint"
      >
        O texto alternativo e a legenda desta imagem ficam no texto da página: selecione-a no
        editor acima e use o botão “Imagem”.
      </p>

      <p
        v-if="errorMessage"
        class="field__error"
        role="alert"
      >
        {{ errorMessage }}
      </p>

      <form
        v-if="isEditing"
        class="page-image__form"
        @submit.prevent="saveDetails"
      >
        <div class="field">
          <label :for="`${idBase}-alt`">Texto alternativo</label>
          <input
            :id="`${idBase}-alt`"
            v-model="alt"
            type="text"
            maxlength="255"
            required
          >
          <span
            v-if="fieldErrors.alt"
            class="field__error"
          >{{ fieldErrors.alt[0] }}</span>
        </div>
        <div class="field">
          <label :for="`${idBase}-caption`">Legenda (opcional)</label>
          <input
            :id="`${idBase}-caption`"
            v-model="caption"
            type="text"
            maxlength="500"
          >
          <span
            v-if="fieldErrors.caption"
            class="field__error"
          >{{ fieldErrors.caption[0] }}</span>
        </div>
        <div class="page-image__actions">
          <button
            type="submit"
            class="btn btn--primary"
            :disabled="isBusy"
          >
            <AppIcon :icon="Save" />
            Salvar texto
          </button>
          <button
            type="button"
            class="btn btn--secondary"
            @click="isEditing = false"
          >
            <AppIcon :icon="X" />
            Cancelar
          </button>
        </div>
      </form>

      <div
        v-else
        class="page-image__actions"
      >
        <button
          v-if="role !== 'content'"
          type="button"
          class="btn btn--secondary"
          @click="startEditing"
        >
          <AppIcon :icon="Pencil" />
          Editar texto
        </button>
        <button
          v-if="role !== 'content'"
          type="button"
          class="btn btn--secondary"
          :disabled="isBusy"
          @click="remove"
        >
          <AppIcon :icon="X" />
          {{ removeLabel }}
        </button>
        <RouterLink
          :to="{ name: 'media.edit', params: { uuid: media.id } }"
          class="btn btn--secondary"
        >
          <AppIcon :icon="ExternalLink" />
          Abrir na biblioteca
        </RouterLink>
      </div>

      <div class="page-image__replace">
        <label :for="`${idBase}-file`">Substituir arquivo (JPEG, PNG ou WebP, até 10 MB)</label>
        <div class="page-image__replace-row">
          <input
            :id="`${idBase}-file`"
            type="file"
            accept="image/jpeg,image/png,image/webp"
            @change="handleFileChange"
          >
          <button
            type="button"
            class="btn btn--secondary"
            :disabled="!replacement || isBusy"
            @click="replace"
          >
            <AppIcon :icon="Replace" />
            Substituir
          </button>
        </div>
        <span
          v-if="fieldErrors.file"
          class="field__error"
        >{{ fieldErrors.file[0] }}</span>
      </div>
    </div>
  </li>
</template>
