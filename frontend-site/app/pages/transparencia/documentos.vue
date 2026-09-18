<script setup lang="ts">
// Rota com página própria — tem prioridade sobre o catch-all [...slug].vue, por precedência
// padrão do roteador do Nuxt. Renderizada no servidor a cada request (não entra em
// nitro.prerender.routes): o filtro por ano e tipo é combinatório demais para pré-gerar, e o
// acervo cresce com o tempo. Funciona sem JavaScript — o filtro é um <form method="get">
// comum, que recarrega a página com a URL atualizada; os links de paginação e download também
// são âncoras normais.
import { Download } from '@lucide/vue'

type TransparencyDocumentType =
  | 'balance'
  | 'bylaws'
  | 'minutes'
  | 'certificate'
  | 'agreement_accounting'
  | 'notice'
  | 'annual_report'

type TransparencyDocument = {
  uuid: string
  title: string
  /** Caminho canônico do PDF no site, montado pela API — nunca remontado aqui. */
  path: string
  year: number
  type: TransparencyDocumentType
  type_label: string
  file_size: number
  download_count: number
  published_at: string | null
}

type PaginatedTransparencyDocuments = {
  data: TransparencyDocument[]
  meta: { current_page: number; last_page: number; total: number }
}

const route = useRoute()
const config = useRuntimeConfig()

function queryParam(name: string): string | undefined {
  const value = route.query[name]
  return Array.isArray(value) ? (value[0] ?? undefined) : (value ?? undefined)
}

const yearQuery = queryParam('year')
const typeQuery = queryParam('type')
const pageQuery = queryParam('page')

// Espelho de App\Enums\TransparencyDocumentType (o `label` da API vem em `type_label`; estes
// existem para montar o <select> antes de qualquer documento chegar). O plural é só daqui: é
// o que permite o <title> dizer "Balanços de 2024" em vez de repetir o rótulo do singular.
const typeOptions: Array<{ value: TransparencyDocumentType; label: string; plural: string }> = [
  { value: 'balance', label: 'Balanço', plural: 'Balanços' },
  { value: 'bylaws', label: 'Estatuto', plural: 'Estatutos' },
  { value: 'minutes', label: 'Ata', plural: 'Atas' },
  { value: 'certificate', label: 'Certidão', plural: 'Certidões' },
  { value: 'agreement_accounting', label: 'Prestação de contas do convênio', plural: 'Prestações de contas do convênio' },
  { value: 'notice', label: 'Edital', plural: 'Editais' },
  { value: 'annual_report', label: 'Relatório anual', plural: 'Relatórios anuais' },
]

// Só filtro que a API de fato aplica entra no título e no canônico. `?type=xyz` é ignorado
// pela API (ver o controller público), então a página responde o acervo inteiro — anunciar
// esse endereço como canônico faria o buscador indexar duas URLs com o mesmo conteúdo.
const tipoValido = computed(() => typeOptions.find((option) => option.value === typeQuery) ?? null)
const anoValido = computed(() => (yearQuery && /^\d{4}$/.test(yearQuery) ? yearQuery : null))
const paginaAtual = computed(() => {
  const numero = Number(pageQuery)
  return Number.isInteger(numero) && numero > 1 ? numero : 1
})

const { data } = await useAsyncData<PaginatedTransparencyDocuments>(
  `transparency-documents:${yearQuery ?? ''}:${typeQuery ?? ''}:${pageQuery ?? ''}`,
  () =>
    $fetch(`${config.public.apiUrl}/api/v1/public/transparency-documents`, {
      query: {
        year: yearQuery,
        type: typeQuery,
        page: pageQuery,
      },
    }),
)

const documents = computed(() => data.value?.data ?? [])
const meta = computed(() => data.value?.meta)

function formatSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  const kb = bytes / 1024
  if (kb < 1024) return `${kb.toFixed(0)} KB`
  return `${(kb / 1024).toFixed(1)} MB`
}

// Reaproveita o filtro atual nos links de paginação — cada link já chega com a URL completa,
// sem depender de JavaScript para montar a próxima página.
function pageHref(page: number): string {
  const params = new URLSearchParams()
  if (yearQuery) params.set('year', yearQuery)
  if (typeQuery) params.set('type', typeQuery)
  params.set('page', String(page))
  return `/transparencia/documentos?${params.toString()}`
}

// O que a pessoa vê na aba e no resultado de busca precisa dizer QUAL recorte ela está
// olhando: "Balanços de 2024" e o acervo inteiro são páginas diferentes, e sem isso as duas
// disputam a mesma busca com o mesmo título.
const tituloSemSufixo = computed(() => {
  const substantivo = tipoValido.value?.plural ?? 'Documentos'
  const ano = anoValido.value ? ` de ${anoValido.value}` : ''
  const pagina = paginaAtual.value > 1 ? ` — página ${paginaAtual.value}` : ''

  return `${substantivo}${ano}${pagina}`
})

const descricao = computed(() => {
  const recorte = tipoValido.value
    ? `${tipoValido.value.plural} do Lar Anália Franco`
    : 'Balanços, atas, editais, certidões e relatórios do Lar Anália Franco'
  const ano = anoValido.value ? ` referentes a ${anoValido.value}` : ''

  return `${recorte}${ano}, em PDF, para consulta e download. Prestação de contas pública, atualizada pela própria instituição.`
})

/**
 * Canônico com o filtro, sem o lixo: `page=1` não entra (é o mesmo endereço sem parâmetro
 * nenhum) e parâmetro que a API ignora também não. `page=2` entra, porque é outra página de
 * resultados de verdade — apontá-la para a primeira esconderia do buscador tudo que não cabe
 * na página 1.
 */
const canonicalPath = computed(() => {
  const params = new URLSearchParams()
  if (anoValido.value) params.set('year', anoValido.value)
  if (tipoValido.value) params.set('type', tipoValido.value.value)
  if (paginaAtual.value > 1) params.set('page', String(paginaAtual.value))

  const query = params.toString()

  return `/transparencia/documentos${query ? `?${query}` : ''}`
})

usePageSeo({
  title: () => `${tituloSemSufixo.value} — Transparência — Lar Anália Franco`,
  description: () => descricao.value,
  canonicalPath: () => canonicalPath.value,
})
</script>

<template>
  <div>
    <nav class="breadcrumb" aria-label="Trilha de navegação">
      <ol>
        <li><NuxtLink to="/">Início</NuxtLink></li>
        <li><NuxtLink to="/transparencia">Transparência</NuxtLink></li>
        <li><span aria-current="page">Documentos</span></li>
      </ol>
    </nav>

    <AppSectionNav />

    <h1>Documentos</h1>
    <p class="prose">
      Balanços, atas, editais e relatórios do Lar Anália Franco. O filtro abaixo funciona sem
      JavaScript — recarrega a página com o ano e o tipo escolhidos na própria URL, para que o
      acervo continue indexável por buscadores.
    </p>

    <form method="get" class="doc-filter" aria-label="Filtrar documentos">
      <div class="doc-filter__field">
        <label for="filter-year">Ano</label>
        <input
          id="filter-year"
          type="number"
          name="year"
          :value="yearQuery"
          min="1900"
          placeholder="Todos"
        />
      </div>
      <div class="doc-filter__field">
        <label for="filter-type">Tipo</label>
        <select id="filter-type" name="type">
          <option value="" :selected="!typeQuery">Todos</option>
          <option
            v-for="option in typeOptions"
            :key="option.value"
            :value="option.value"
            :selected="typeQuery === option.value"
          >
            {{ option.label }}
          </option>
        </select>
      </div>
      <button type="submit" class="btn btn--primary">Filtrar</button>
      <a v-if="yearQuery || typeQuery" href="/transparencia/documentos" class="doc-filter__clear">
        Limpar filtro
      </a>
    </form>

    <p v-if="documents.length === 0" class="doc-empty">
      Nenhum documento encontrado com esse filtro.
    </p>

    <ul v-else class="doc-list">
      <li v-for="document in documents" :key="document.uuid" class="doc-list__item card">
        <div class="doc-list__meta">
          <span class="doc-list__type">{{ document.type_label }}</span>
          <span class="doc-list__year">{{ document.year }}</span>
        </div>
        <h2 class="doc-list__title">{{ document.title }}</h2>
        <p class="doc-list__details">
          {{ formatSize(document.file_size) }} · {{ document.download_count }} downloads
        </p>
        <!-- Mesma URL para ver e para baixar: ela é a canônica, indexável, e o atributo
             `download` é o que faz o navegador salvar em vez de abrir. Duas URLs para o mesmo
             arquivo dividiriam o sinal de busca entre as duas. -->
        <a :href="document.path" class="btn btn--secondary" download>
          <Download :size="16" aria-hidden="true" />
          Baixar PDF
        </a>
      </li>
    </ul>

    <nav v-if="meta && meta.last_page > 1" class="doc-pagination" aria-label="Paginação de documentos">
      <!-- Âncoras comuns, com o filtro embutido na URL: um rastreador segue estes links sem
           executar JavaScript nenhum, que é o que torna o acervo inteiro alcançável. `rel`
           diz a ordem da série a quem a usa. -->
      <a v-if="meta.current_page > 1" rel="prev" :href="pageHref(meta.current_page - 1)">← Anterior</a>
      <span>Página {{ meta.current_page }} de {{ meta.last_page }}</span>
      <a v-if="meta.current_page < meta.last_page" rel="next" :href="pageHref(meta.current_page + 1)">Próxima →</a>
    </nav>
  </div>
</template>

<style scoped>
.doc-filter {
  display: flex;
  flex-wrap: wrap;
  align-items: end;
  gap: var(--space-4);
  margin-block: var(--space-6);
  padding: var(--space-5);
  background: var(--color-surface-raised);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
}

.doc-filter__field {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
}

.doc-filter__field label {
  font-size: var(--text-xs);
  font-weight: var(--weight-medium);
  color: var(--color-text-muted);
}

.doc-filter__field input,
.doc-filter__field select {
  font: inherit;
  font-size: var(--text-sm);
  height: var(--control-height-sm);
  padding-inline: var(--space-3);
  line-height: normal;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface-raised);
  color: var(--color-text);
  min-width: 10rem;
}

/* Mesma altura pequena do input/select acima — na barra de filtro, "Filtrar" usa .btn
   (control-height-md por padrão) e ficava mais alto que os campos ao lado. Ver
   docs/tarefas/03-alinhamento-visual.md. */
.doc-filter .btn {
  height: var(--control-height-sm);
}

.doc-filter__clear {
  font-size: var(--text-sm);
  color: var(--color-text-muted);
}

.doc-empty {
  color: var(--color-text-muted);
}

.doc-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.doc-list__item {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-2) var(--space-5);
}

.doc-list__meta {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: var(--space-1);
  min-width: 8rem;
}

.doc-list__type {
  font-size: var(--text-xs);
  font-weight: var(--weight-semibold);
  letter-spacing: var(--tracking-wide);
  text-transform: uppercase;
  color: var(--color-focus);
}

.doc-list__year {
  font-family: var(--font-display);
  font-weight: var(--weight-bold);
  font-size: var(--text-lg);
  color: var(--color-text);
}

.doc-list__title {
  flex: 1;
  min-width: 14rem;
  margin: 0;
  font-family: var(--font-body);
  font-size: var(--text-base);
  font-weight: var(--weight-semibold);
}

.doc-list__details {
  margin: 0;
  font-size: var(--text-xs);
  color: var(--color-text-muted);
  white-space: nowrap;
}

.doc-pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-5);
  margin-top: var(--space-7);
  font-size: var(--text-sm);
  color: var(--color-text-muted);
}
</style>
