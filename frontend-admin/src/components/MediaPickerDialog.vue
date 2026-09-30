<script setup lang="ts">
import axios from 'axios'
import { ArrowLeft, Check, Search, X } from 'lucide-vue-next'
import { computed, nextTick, ref, useId, watch } from 'vue'

import AppIcon from '@/components/AppIcon.vue'
import type { MediaFigureAttrs } from '@/components/editor/mediaFigure'
import ImageUploadForm from '@/components/ImageUploadForm.vue'
import PaginationControls from '@/components/PaginationControls.vue'
import { fetchMediaList, type MediaDetailsPayload, mediaPreviewUrl, uploadMedia } from '@/services/media'
import { uploadImageToPage } from '@/services/pageImages'
import type { Media } from '@/types/media'

/**
 * Pôr uma foto no texto: enviada do computador ali mesmo, ou escolhida entre as que já estão na
 * biblioteca (e então descrita para este texto). As duas abas existem para quem tem a foto no
 * computador não precisar sair da página que está editando — o caminho antigo era abrir a
 * biblioteca numa aba nova, enviar, voltar e buscar.
 *
 * Enviar começa selecionado: é o caso de quem chega aqui pela primeira vez com uma foto nova.
 * O envio vai para a biblioteca e a foto entra no texto com a descrição digitada no envio; a
 * página em si só muda no site quando alguém clica em Salvar.
 *
 * `purpose="cover"`: escolher a capa de uma página ("Imagens desta página"). Não tem o passo
 * de descrever, porque a capa usa o texto alternativo e a legenda da própria biblioteca (ADR
 * 0025). Clicar na imagem já é a escolha, e o diálogo emite `pick`; enviar do computador põe a
 * foto direto na capa (`uploaded`), pela mesma rota de "Imagens desta página".
 *
 * `<dialog>` nativo com `showModal()`: o navegador já prende o foco dentro, torna o resto da
 * página inerte, fecha no Esc e devolve o foco a quem abriu — nada disso reimplementado aqui.
 *
 * `editing` presente = a figura já está no texto e a pessoa só ajusta alternativo e legenda; o
 * passo da grade é pulado.
 *
 * A grade pede à API só o que pode ir para o site (`publishable`) — é conveniência: quem recusa
 * imagem impublicável no conteúdo é a API, ao salvar a página.
 */
const props = withDefaults(
  defineProps<{
    open: boolean
    editing: MediaFigureAttrs | null
    purpose?: 'text' | 'cover'
    /** Obrigatório com `purpose="cover"`: o envio vai para a capa desta página. */
    pageUuid?: string
    /** Foto solta no corpo do texto: o diálogo abre no envio, já com ela escolhida. */
    initialFile?: File | null
  }>(),
  { purpose: 'text', pageUuid: undefined, initialFile: null },
)

const emit = defineEmits<{
  close: []
  confirm: [attrs: MediaFigureAttrs]
  pick: [media: Media]
  /** Só na capa: a foto foi enviada e já está na capa. */
  uploaded: [media: Media]
}>()

// Um prefixo por instância: a tela de edição de página tem dois seletores (o do editor e o da
// capa), e `id` repetido faz o `<label for>` de um apontar para o campo do outro.
const uid = useId()
const dialog = ref<HTMLDialogElement | null>(null)
const altInput = ref<HTMLInputElement | null>(null)

const step = ref<'choose' | 'describe'>('choose')
const source = ref<'upload' | 'library'>('upload')
// Muda a cada abertura: o formulário de envio renasce vazio, sem a foto da vez anterior.
const uploadKey = ref(0)
const libraryLoaded = ref(false)
const items = ref<Media[]>([])
const search = ref('')
const currentPage = ref(1)
const lastPage = ref(1)
const isLoading = ref(false)
const errorMessage = ref<string | null>(null)

const chosen = ref<{ uuid: string; preview: string } | null>(null)
const alt = ref('')
const caption = ref('')

const title = computed(() => {
  if (props.purpose === 'cover') {
    return 'Foto que representa esta página'
  }

  if (props.editing) {
    return 'Descrição da foto no texto'
  }

  return step.value === 'choose' ? 'Pôr uma foto no texto' : 'Descrever a foto'
})

function uploadForPurpose(file: File, payload: MediaDetailsPayload): Promise<Media> {
  if (props.purpose === 'cover' && props.pageUuid) {
    return uploadImageToPage(props.pageUuid, file, payload, 'cover')
  }

  return uploadMedia(file, payload)
}

function handleUploaded(media: Media, details: { alt: string; caption: string }): void {
  if (props.purpose === 'cover') {
    emit('uploaded', media)
  } else {
    emit('confirm', { uuid: media.id, alt: details.alt, caption: details.caption })
  }

  dialog.value?.close()
}

/**
 * Uma foto solta fora da área de envio, mas dentro do diálogo, seria aberta pelo navegador no
 * lugar do painel — e o texto que a pessoa estava escrevendo iria junto. Só arquivos: arrastar
 * texto para um campo continua funcionando.
 */
function ignoreStrayFileDrop(event: DragEvent): void {
  if (event.dataTransfer?.types.includes('Files')) {
    event.preventDefault()
  }
}

const tabs = [
  { key: 'upload', label: 'Enviar do computador' },
  { key: 'library', label: 'Escolher entre as já enviadas' },
] as const

function selectSource(key: 'upload' | 'library'): void {
  source.value = key

  if (key === 'library' && !libraryLoaded.value) {
    void load()
  }
}

/** Setas trocam de aba, como em qualquer lista de abas (padrão `tablist` do ARIA). */
function onTabKeydown(event: KeyboardEvent): void {
  if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') {
    return
  }

  event.preventDefault()
  const next = source.value === 'upload' ? 'library' : 'upload'
  selectSource(next)
  void nextTick(() => document.getElementById(`${uid}-tab-${next}`)?.focus())
}

async function load(page = 1): Promise<void> {
  isLoading.value = true
  errorMessage.value = null

  try {
    const response = await fetchMediaList({ search: search.value || undefined, page, per_page: 12, publishable: true })
    items.value = response.data
    libraryLoaded.value = true
    currentPage.value = response.meta.current_page
    lastPage.value = response.meta.last_page
  } catch (error) {
    errorMessage.value =
      axios.isAxiosError(error) && error.response?.status === 403
        ? 'Você não tem permissão para ver as fotos já enviadas.'
        : 'Não foi possível carregar as fotos. Tente novamente.'
  } finally {
    isLoading.value = false
  }
}

function choose(item: Media): void {
  if (props.purpose === 'cover') {
    emit('pick', item)
    dialog.value?.close()

    return
  }

  chosen.value = { uuid: item.id, preview: item.preview_url }
  alt.value = item.alt
  caption.value = item.caption ?? ''
  step.value = 'describe'
  void nextTick(() => altInput.value?.focus())
}

function confirm(): void {
  if (!chosen.value || alt.value.trim() === '') {
    return
  }

  emit('confirm', { uuid: chosen.value.uuid, alt: alt.value.trim(), caption: caption.value.trim() })
  dialog.value?.close()
}

watch(
  () => props.open,
  (open) => {
    if (!open) {
      dialog.value?.close()

      return
    }

    if (props.editing) {
      chosen.value = { uuid: props.editing.uuid, preview: mediaPreviewUrl(props.editing.uuid) }
      alt.value = props.editing.alt
      caption.value = props.editing.caption
      step.value = 'describe'
    } else {
      chosen.value = null
      search.value = ''
      items.value = []
      libraryLoaded.value = false
      step.value = 'choose'
      source.value = 'upload'
      uploadKey.value++
    }

    dialog.value?.showModal()

    if (props.editing) {
      void nextTick(() => altInput.value?.focus())
    }
  },
)
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
        {{ title }}
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

    <template v-if="step === 'choose'">
      <div
        class="media-picker__tabs"
        role="tablist"
        aria-label="De onde vem a foto"
      >
        <button
          v-for="tab in tabs"
          :id="`${uid}-tab-${tab.key}`"
          :key="tab.key"
          type="button"
          role="tab"
          class="media-picker__tab"
          :class="{ 'media-picker__tab--active': source === tab.key }"
          :aria-selected="source === tab.key"
          :aria-controls="`${uid}-panel-${tab.key}`"
          :tabindex="source === tab.key ? 0 : -1"
          @click="selectSource(tab.key)"
          @keydown="onTabKeydown"
        >
          {{ tab.label }}
        </button>
      </div>

      <div
        v-if="source === 'upload'"
        :id="`${uid}-panel-upload`"
        role="tabpanel"
        :aria-labelledby="`${uid}-tab-upload`"
      >
        <ImageUploadForm
          :key="uploadKey"
          :upload="uploadForPurpose"
          :initial-file="initialFile"
          :submit-label="purpose === 'cover' ? 'Enviar e usar nesta página' : 'Enviar e pôr no texto'"
          @uploaded="handleUploaded"
        />
      </div>

      <div
        v-else
        :id="`${uid}-panel-library`"
        role="tabpanel"
        :aria-labelledby="`${uid}-tab-library`"
      >
        <form
          class="filter-bar"
          role="search"
          @submit.prevent="load(1)"
        >
          <div class="filter-bar__field">
            <label :for="`${uid}-search`">Buscar pela descrição ou legenda</label>
            <input
              :id="`${uid}-search`"
              v-model="search"
              type="search"
            >
          </div>
          <button
            type="submit"
            class="btn btn--primary"
          >
            <AppIcon :icon="Search" />
            Buscar
          </button>
        </form>

        <p
          v-if="purpose === 'cover'"
          class="field__hint"
        >
          Só aparecem as fotos que podem ir para o site. Clique na foto para usá-la.
        </p>

        <p
          v-if="isLoading"
          class="state-message"
        >
          Carregando…
        </p>
        <p
          v-else-if="errorMessage"
          class="state-message state-message--error"
        >
          {{ errorMessage }}
        </p>
        <p
          v-else-if="items.length === 0"
          class="state-message"
        >
          Nenhuma foto encontrada.
        </p>
        <template v-else>
          <ul class="media-grid media-picker__grid">
            <li
              v-for="item in items"
              :key="item.id"
              class="media-tile"
            >
              <button
                type="button"
                class="media-tile__link media-picker__choice"
                @click="choose(item)"
              >
                <span class="media-tile__frame">
                  <img
                    :src="item.preview_url"
                    alt=""
                    loading="lazy"
                    class="media-tile__image"
                  >
                </span>
                <span class="media-tile__alt">{{ item.alt }}</span>
              </button>
            </li>
          </ul>
          <PaginationControls
            :current-page="currentPage"
            :last-page="lastPage"
            @change="load"
          />
        </template>
      </div>
    </template>

    <form
      v-else
      class="media-picker__describe"
      @submit.prevent="confirm"
    >
      <img
        v-if="chosen"
        :src="chosen.preview"
        :alt="alt"
        class="media-form__preview"
      >

      <div class="field">
        <label :for="`${uid}-alt`">Descrição da foto</label>
        <input
          :id="`${uid}-alt`"
          ref="altInput"
          v-model="alt"
          type="text"
          maxlength="255"
          required
        >
        <span class="field__hint">
          O que a foto mostra, para quem não pode vê-la. Vem preenchida com a descrição guardada em
          Imagens; mudar aqui vale só para este texto.
        </span>
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
      </div>

      <div class="media-picker__actions">
        <button
          v-if="!editing"
          type="button"
          class="btn btn--secondary"
          @click="step = 'choose'"
        >
          <AppIcon :icon="ArrowLeft" />
          Escolher outra foto
        </button>
        <button
          type="submit"
          class="btn btn--primary"
          :disabled="alt.trim() === ''"
        >
          <AppIcon :icon="Check" />
          {{ editing ? 'Aplicar' : 'Pôr no texto' }}
        </button>
      </div>
    </form>
  </dialog>
</template>
