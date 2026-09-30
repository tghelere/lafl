<script setup lang="ts">
import axios from 'axios'
import { ImagePlus, Images } from 'lucide-vue-next'
import { nextTick, ref, watch } from 'vue'

import AddPageImageDialog from '@/components/AddPageImageDialog.vue'
import AppIcon from '@/components/AppIcon.vue'
import MediaPickerDialog from '@/components/MediaPickerDialog.vue'
import NoticeBanner from '@/components/NoticeBanner.vue'
import PageImageItem from '@/components/PageImageItem.vue'
import { fetchPageImages, setPageCover } from '@/services/pageImages'
import type { Media, PageImages } from '@/types/media'

/**
 * "Imagens desta página": a porta da página para a mesma biblioteca de /admin/imagens (ver
 * docs/decisoes/0025-imagens-da-pagina.md). Lista só o que ESTA página usa — capa, galeria e
 * as imagens do texto salvo — e deixa enviar, substituir e corrigir o texto no mesmo lugar.
 *
 * Começa pelas ações (adicionar foto, escolher a capa), não pelas listas: quem abre a seção
 * quase sempre veio fazer uma dessas duas coisas. O envio abre em diálogo
 * (AddPageImageDialog.vue).
 *
 * Fica fora do formulário da página, com salvamento próprio: cada ação aqui já está no ar
 * quando termina, e não depende do "Salvar" do texto.
 */
const props = defineProps<{
  pageUuid: string
  /** Muda quando a página é salva: as imagens do texto são as do conteúdo SALVO. */
  refreshKey: string | null
}>()

const images = ref<PageImages | null>(null)
const isLoading = ref(true)
const loadErrorMessage = ref<string | null>(null)
const notice = ref<string | null>(null)

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

// Anúncio para leitor de tela: o NoticeBanner é `role="note"`, que não é lido quando muda.
const announcement = ref('')

/**
 * Depois de mover, o foco volta ao mesmo botão da mesma foto, agora no lugar novo, para quem
 * usa o teclado continuar apertando. Se a foto chegou à ponta e o botão ficou desligado, o foco
 * vai para o outro sentido.
 */
async function onMoved(media: Media, message: string, direction: 'up' | 'down'): Promise<void> {
  notice.value = null
  announcement.value = message
  await load()
  await nextTick()

  const base = `page-image-gallery-${media.id}`
  const same = document.getElementById(`${base}-${direction}`) as HTMLButtonElement | null
  const other = document.getElementById(`${base}-${direction === 'up' ? 'down' : 'up'}`) as HTMLButtonElement | null
  ;(same && !same.disabled ? same : other)?.focus()
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

const addOpen = ref(false)

function onUploaded(_media: Media, target: 'gallery' | 'cover'): void {
  onChanged(
    target === 'cover'
      ? 'Imagem enviada e posta na capa. Ela já aparece no site.'
      : 'Imagem enviada e posta no fim da galeria. Ela já aparece no site.',
  )
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

    <div
      v-if="images"
      class="page-images__actions"
    >
      <button
        type="button"
        class="btn btn--primary"
        @click="addOpen = true"
      >
        <AppIcon :icon="ImagePlus" />
        Adicionar foto
      </button>
      <button
        type="button"
        class="btn btn--secondary"
        @click="pickerOpen = true"
      >
        <AppIcon :icon="Images" />
        {{ images.cover ? 'Trocar a capa por imagem da biblioteca' : 'Escolher a capa na biblioteca' }}
      </button>
    </div>
    <AddPageImageDialog
      :open="addOpen"
      :page-uuid="pageUuid"
      @uploaded="onUploaded"
      @close="addOpen = false"
    />
    <MediaPickerDialog
      :open="pickerOpen"
      :editing="null"
      purpose="cover"
      :page-uuid="pageUuid"
      @pick="chooseCover"
      @uploaded="onChanged('Foto enviada e posta no lugar da anterior. O site já mostra a nova.')"
      @close="pickerOpen = false"
    />

    <NoticeBanner
      v-if="notice"
      variant="info"
    >
      {{ notice }}
    </NoticeBanner>
    <p
      class="visually-hidden"
      aria-live="polite"
    >
      {{ announcement }}
    </p>

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
          v-for="(media, index) in images.gallery"
          :key="`gallery-${media.id}`"
          :media="media"
          role="gallery"
          :page-uuid="pageUuid"
          :position="index + 1"
          :count="images.gallery.length"
          @changed="onChanged"
          @moved="(message, direction) => onMoved(media, message, direction)"
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
        A capa desta página aparece {{ images.cover_shown_on.join(' e ') }}. Trocar ou tirar a
        capa muda isso na hora.
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
    </template>
  </section>
</template>
