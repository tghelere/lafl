<script setup lang="ts">
import axios from 'axios'
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { onBeforeRouteLeave, useRoute } from 'vue-router'

import AppLayout from '@/components/AppLayout.vue'
import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import NoticeBanner from '@/components/NoticeBanner.vue'
import RichTextEditor from '@/components/RichTextEditor.vue'
import { siteUrl } from '@/config'
import { fetchContentMarkers } from '@/services/contentMarkers'
import { fetchContentPage, updateContentPage } from '@/services/pages'
import type { ContentMarker, ContentPage } from '@/types/pages'

/**
 * Comprimento em que o Google costuma truncar a meta description no resultado de busca. Não
 * é limite da API (o backend aceita até 500) — passar disso não impede salvar, só avisa que
 * o final provavelmente não vai aparecer na busca.
 */
const META_DESCRIPTION_ADVISORY_LIMIT = 160

const route = useRoute()

const uuid = computed(() => (typeof route.params.uuid === 'string' ? route.params.uuid : null))

const record = ref<ContentPage | null>(null)
const isLoading = ref(false)
const loadErrorMessage = ref<string | null>(null)
const markers = ref<ContentMarker[]>([])

const title = ref('')
const content = ref('')
const metaTitle = ref('')
const metaDescription = ref('')

const isSaving = ref(false)
const savedAt = ref<number | null>(null)
const submitErrorMessage = ref<string | null>(null)
const fieldErrors = reactive<Record<string, string[]>>({})

const isDraft = computed(() => record.value?.status === 'draft')

const metaDescriptionLength = computed(() => metaDescription.value.length)
const metaDescriptionOverAdvisory = computed(
  () => metaDescriptionLength.value > META_DESCRIPTION_ADVISORY_LIMIT,
)

const publicUrl = computed(() => {
  if (!record.value) {
    return null
  }

  const base = siteUrl.replace(/\/$/, '')

  return `${base}/${record.value.slug}`
})

/**
 * Comparação com o que veio do servidor, não um sinalizador ligado no primeiro `input` — o
 * editor normaliza o HTML ao carregar (o Tiptap reserializa o conteúdo), então um
 * sinalizador simples marcaria "alterado" sem ninguém ter digitado nada.
 */
const isDirty = computed(() => {
  if (!record.value) {
    return false
  }

  return (
    title.value !== record.value.title ||
    content.value !== record.value.content ||
    metaTitle.value !== (record.value.meta_title ?? '') ||
    metaDescription.value !== (record.value.meta_description ?? '')
  )
})

function clearFieldErrors(): void {
  Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key])
}

function applyRecord(page: ContentPage): void {
  record.value = page
  title.value = page.title
  content.value = page.content
  metaTitle.value = page.meta_title ?? ''
  metaDescription.value = page.meta_description ?? ''
}

async function load(): Promise<void> {
  if (!uuid.value) {
    return
  }

  isLoading.value = true
  loadErrorMessage.value = null

  try {
    applyRecord(await fetchContentPage(uuid.value))
  } catch (error) {
    if (axios.isAxiosError(error) && error.response?.status === 403) {
      loadErrorMessage.value = 'Você não tem permissão para editar esta página.'
    } else if (axios.isAxiosError(error) && error.response?.status === 404) {
      loadErrorMessage.value = 'Página não encontrada.'
    } else {
      loadErrorMessage.value = 'Não foi possível carregar a página. Tente novamente.'
    }
  } finally {
    isLoading.value = false
  }
}

/**
 * Os valores envelhecem (a contagem muda a cada documento publicado), então a lista é buscada
 * a cada abertura, não uma vez na vida do painel.
 *
 * Falhar aqui não pode atrapalhar quem veio escrever: a lista é um auxílio, e sem ela o
 * editor continua inteiro. Por isso o erro não vai para `loadErrorMessage`.
 */
async function loadMarkers(): Promise<void> {
  try {
    markers.value = await fetchContentMarkers()
  } catch {
    markers.value = []
  }
}

watch(
  () => route.fullPath,
  () => {
    submitErrorMessage.value = null
    savedAt.value = null
    clearFieldErrors()
    void load()
    void loadMarkers()
  },
  { immediate: true },
)

async function handleSubmit(): Promise<void> {
  if (!uuid.value || !record.value) {
    return
  }

  isSaving.value = true
  submitErrorMessage.value = null
  savedAt.value = null
  clearFieldErrors()

  try {
    // slug e status são reenviados como vieram: esta tela não os edita (ver
    // src/services/pages.ts). O backend ignora os dois para quem não tem `direcao`.
    const saved = await updateContentPage(uuid.value, {
      slug: record.value.slug,
      title: title.value,
      content: content.value,
      meta_title: metaTitle.value || null,
      meta_description: metaDescription.value || null,
      status: record.value.status,
    })

    // Reaplica o que o servidor devolveu, não o que foi enviado: o conteúdo volta já
    // sanitizado, e é essa a versão que está publicada. Sem isso, um trecho removido pela
    // allowlist continuaria visível no editor até recarregar a tela.
    applyRecord(saved)
    savedAt.value = Date.now()
  } catch (error) {
    if (axios.isAxiosError(error) && error.response?.status === 422) {
      const body = error.response.data as { errors?: Record<string, string[]> }
      Object.assign(fieldErrors, body.errors ?? {})
      submitErrorMessage.value = 'Corrija os campos indicados antes de salvar.'
    } else if (axios.isAxiosError(error) && error.response?.status === 403) {
      submitErrorMessage.value = 'Você não tem permissão para editar esta página.'
    } else {
      submitErrorMessage.value = 'Não foi possível salvar. Tente novamente.'
    }
  } finally {
    isSaving.value = false
  }
}

onBeforeRouteLeave(() => {
  if (!isDirty.value) {
    return true
  }

  return window.confirm('Há alterações não salvas nesta página. Sair mesmo assim?')
})

// Cobre fechar a aba e recarregar, que não passam pelo roteador. O texto do aviso é o do
// navegador — desde 2019 nenhum deles deixa a página escolher a mensagem.
function warnOnUnload(event: BeforeUnloadEvent): void {
  if (isDirty.value) {
    event.preventDefault()
  }
}

onMounted(() => window.addEventListener('beforeunload', warnOnUnload))
onBeforeUnmount(() => window.removeEventListener('beforeunload', warnOnUnload))
</script>

<template>
  <AppLayout resource="pages">
    <nav
      class="breadcrumb"
      aria-label="Trilha de navegação"
    >
      <ol>
        <li>
          <RouterLink :to="{ name: 'pages.index' }">
            Páginas
          </RouterLink>
          /
        </li>
        <li><span aria-current="page">Editar página</span></li>
      </ol>
    </nav>

    <LoadingState v-if="isLoading" />
    <ErrorState
      v-else-if="loadErrorMessage"
      :message="loadErrorMessage"
    />

    <template v-else-if="record">
      <div class="page-form__header">
        <h1>{{ record.title }}</h1>
        <span
          class="badge"
          :class="isDraft ? 'badge--draft' : 'badge--published'"
        >
          {{ record.status_label }}
        </span>
      </div>

      <p class="page-form__address">
        Endereço público: <code>/{{ record.slug }}</code>
        <a
          v-if="publicUrl && !isDraft"
          :href="publicUrl"
          target="_blank"
          rel="noopener noreferrer"
          class="page-form__open"
        >
          Abrir no site
        </a>
      </p>

      <NoticeBanner v-if="isDraft">
        Esta página é um rascunho e ainda não está no ar. O texto pode ser editado e salvo
        normalmente; publicar não é feito por esta tela.
      </NoticeBanner>
      <NoticeBanner
        v-if="submitErrorMessage"
        variant="error"
      >
        {{ submitErrorMessage }}
      </NoticeBanner>
      <NoticeBanner
        v-if="savedAt"
        variant="info"
      >
        Página salva. A alteração já está no ar.
      </NoticeBanner>

      <form
        class="card"
        @submit.prevent="handleSubmit"
      >
        <div class="field">
          <label for="page-title">Título</label>
          <input
            id="page-title"
            v-model="title"
            type="text"
            maxlength="255"
            required
          >
          <span
            v-if="fieldErrors.title"
            class="field__error"
          >{{ fieldErrors.title[0] }}</span>
        </div>

        <div class="field">
          <!-- Não é <label for>: a área editável do Tiptap é um contenteditable, não um
               campo de formulário — o nome acessível vai nela por aria-label. -->
          <span class="field__legend">Conteúdo</span>
          <RichTextEditor
            v-model="content"
            aria-label="Conteúdo da página"
          />
          <span
            v-if="fieldErrors.content"
            class="field__error"
          >{{ fieldErrors.content[0] }}</span>

          <!-- Os valores vêm prontos da API (App\Actions\Content\ListContentMarkers) — o
               painel não calcula idade nem conta documento. -->
          <section
            v-if="markers.length"
            class="markers"
            aria-labelledby="page-markers-title"
          >
            <h2
              id="page-markers-title"
              class="markers__title"
            >
              Números que se calculam sozinhos
            </h2>
            <p class="markers__hint">
              Escreva o marcador no meio do texto e o site publica o número do dia, com o
              plural certo. Não digite o número: escrito à mão, ele envelhece sem ninguém
              perceber.
            </p>
            <ul class="markers__list">
              <li
                v-for="marker in markers"
                :key="marker.name"
                class="markers__item"
              >
                <code class="markers__code">{{ marker.marker }}</code>
                <span class="markers__label">{{ marker.label }}</span>
                <span class="markers__value">hoje: {{ marker.value }}</span>
              </li>
            </ul>
          </section>
        </div>

        <h2 class="page-form__section">
          Busca no Google
        </h2>

        <div class="field">
          <label for="page-meta-title">Título para busca</label>
          <input
            id="page-meta-title"
            v-model="metaTitle"
            type="text"
            maxlength="255"
          >
          <span class="field__hint">
            Em branco, a busca usa o título da página.
          </span>
          <span
            v-if="fieldErrors.meta_title"
            class="field__error"
          >{{ fieldErrors.meta_title[0] }}</span>
        </div>

        <div class="field">
          <label for="page-meta-description">Descrição para busca</label>
          <textarea
            id="page-meta-description"
            v-model="metaDescription"
            rows="3"
            maxlength="500"
          />
          <span
            class="char-counter"
            :class="{ 'char-counter--over': metaDescriptionOverAdvisory }"
          >
            {{ metaDescriptionLength }} de {{ META_DESCRIPTION_ADVISORY_LIMIT }} caracteres
            <template v-if="metaDescriptionOverAdvisory">
              — o Google provavelmente vai cortar o final.
            </template>
          </span>
          <span
            v-if="fieldErrors.meta_description"
            class="field__error"
          >{{ fieldErrors.meta_description[0] }}</span>
        </div>

        <button
          type="submit"
          class="btn btn--primary"
          :disabled="isSaving"
        >
          {{ isSaving ? 'Salvando…' : 'Salvar' }}
        </button>
      </form>
    </template>
  </AppLayout>
</template>

<style scoped>
.page-form__header {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  flex-wrap: wrap;
}

.page-form__address {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
  margin-bottom: var(--space-5);
}

.page-form__open {
  margin-left: var(--space-3);
}

.markers {
  margin-top: var(--space-4);
  padding: var(--space-4);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  /* Um degrau abaixo do branco do .card em volta, para o bloco se ler como nota de apoio. */
  background: var(--color-surface);
}

.markers__title {
  font-size: var(--text-sm);
  margin: 0 0 var(--space-2);
}

.markers__hint {
  font-size: var(--text-sm);
  color: var(--color-text-muted);
  margin: 0 0 var(--space-3);
}

.markers__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: var(--space-2);
}

.markers__item {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: var(--space-2) var(--space-3);
  font-size: var(--text-sm);
}

.markers__code {
  font-weight: 600;
}

.markers__label {
  color: var(--color-text-muted);
}

.markers__value {
  margin-left: auto;
  color: var(--color-text-muted);
  font-variant-numeric: tabular-nums;
}

.page-form__section {
  font-size: var(--text-lg);
  margin: var(--space-6) 0 var(--space-4);
  padding-top: var(--space-5);
  border-top: 1px solid var(--color-border);
}
</style>
