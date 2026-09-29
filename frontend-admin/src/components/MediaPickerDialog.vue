<script setup lang="ts">
import axios from 'axios'
import { ArrowLeft, Check, ExternalLink, Search, X } from 'lucide-vue-next'
import { computed, nextTick, ref, useId, watch } from 'vue'
import { useRouter } from 'vue-router'

import AppIcon from '@/components/AppIcon.vue'
import type { MediaFigureAttrs } from '@/components/editor/mediaFigure'
import PaginationControls from '@/components/PaginationControls.vue'
import { fetchMediaList, mediaPreviewUrl } from '@/services/media'
import type { Media } from '@/types/media'

/**
 * Escolher uma imagem da biblioteca para o texto, e dar a ela texto alternativo e legenda.
 *
 * `purpose="cover"`: escolher a capa de uma página ("Imagens desta página"). Não tem o passo
 * de descrever, porque a capa usa o texto alternativo e a legenda da própria biblioteca (ADR
 * 0025). Clicar na imagem já é a escolha, e o diálogo emite `pick`.
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
  }>(),
  { purpose: 'text' },
)

const emit = defineEmits<{
  close: []
  confirm: [attrs: MediaFigureAttrs]
  pick: [media: Media]
}>()

const router = useRouter()
// Um prefixo por instância: a tela de edição de página tem dois seletores (o do editor e o da
// capa), e `id` repetido faz o `<label for>` de um apontar para o campo do outro.
const uid = useId()
const dialog = ref<HTMLDialogElement | null>(null)
const altInput = ref<HTMLInputElement | null>(null)

const step = ref<'choose' | 'describe'>('choose')
const items = ref<Media[]>([])
const search = ref('')
const currentPage = ref(1)
const lastPage = ref(1)
const isLoading = ref(false)
const errorMessage = ref<string | null>(null)

const chosen = ref<{ uuid: string; preview: string } | null>(null)
const alt = ref('')
const caption = ref('')

const uploadHref = computed(() => router.resolve({ name: 'media.create' }).href)

async function load(page = 1): Promise<void> {
  isLoading.value = true
  errorMessage.value = null

  try {
    const response = await fetchMediaList({ search: search.value || undefined, page, per_page: 12, publishable: true })
    items.value = response.data
    currentPage.value = response.meta.current_page
    lastPage.value = response.meta.last_page
  } catch (error) {
    errorMessage.value =
      axios.isAxiosError(error) && error.response?.status === 403
        ? 'Você não tem permissão para ver a biblioteca de imagens.'
        : 'Não foi possível carregar as imagens. Tente novamente.'
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
      step.value = 'choose'
      void load()
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
  >
    <header class="media-picker__header">
      <h2
        :id="`${uid}-title`"
        class="media-picker__title"
      >
        {{ purpose === 'cover' ? 'Escolher a capa' : editing ? 'Editar imagem' : step === 'choose' ? 'Inserir imagem' : 'Descrever a imagem' }}
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
      <form
        class="filter-bar"
        role="search"
        @submit.prevent="load(1)"
      >
        <div class="filter-bar__field">
          <label :for="`${uid}-search`">Buscar na biblioteca</label>
          <input
            :id="`${uid}-search`"
            v-model="search"
            type="search"
            placeholder="Texto alternativo ou legenda"
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
        Só aparecem as imagens que podem ir para o site. Para uma foto nova, feche e use
        “Enviar imagem”, escolhendo a capa como destino.
      </p>
      <p
        v-else
        class="field__hint"
      >
        Não achou? <a
          :href="uploadHref"
          target="_blank"
          rel="noopener"
        >Envie a imagem numa aba nova <AppIcon :icon="ExternalLink" /></a> e busque de novo — o
        texto que você está editando fica aqui.
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
        Nenhuma imagem encontrada.
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
        <label :for="`${uid}-alt`">Texto alternativo</label>
        <input
          :id="`${uid}-alt`"
          ref="altInput"
          v-model="alt"
          type="text"
          maxlength="255"
          required
        >
        <span class="field__hint">O que a imagem mostra, neste texto, para quem não pode vê-la.</span>
      </div>

      <div class="field">
        <label :for="`${uid}-caption`">Legenda (opcional)</label>
        <input
          :id="`${uid}-caption`"
          v-model="caption"
          type="text"
          maxlength="500"
        >
      </div>

      <div class="media-picker__actions">
        <button
          v-if="!editing"
          type="button"
          class="btn btn--secondary"
          @click="step = 'choose'"
        >
          <AppIcon :icon="ArrowLeft" />
          Escolher outra
        </button>
        <button
          type="submit"
          class="btn btn--primary"
          :disabled="alt.trim() === ''"
        >
          <AppIcon :icon="Check" />
          {{ editing ? 'Aplicar' : 'Inserir no texto' }}
        </button>
      </div>
    </form>
  </dialog>
</template>
