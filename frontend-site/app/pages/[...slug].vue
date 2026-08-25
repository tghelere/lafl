<script setup lang="ts">
// Catch-all: serve qualquer página do CMS pelo slug (docs/estrutura-site.md §1.2 e §3.1),
// respeitando o limite de dois níveis que App\Actions\Content\SavePage já garante no
// backend. Uma rota com página própria (ex.: /transparencia/documentos) tem prioridade sobre
// esta, por precedência padrão do roteador do Nuxt — rota estática sempre vence catch-all.
const route = useRoute()

const rawSlug = route.params.slug
const segments = Array.isArray(rawSlug) ? rawSlug : rawSlug ? [rawSlug] : []

if (segments.length === 0 || segments.length > 2) {
  throw createError({ statusCode: 404, statusMessage: 'Página não encontrada', fatal: true })
}

const slug = segments.join('/')
const parentSlug = segments.length === 2 ? segments[0] : null

const { data, error } = await usePublicPage(slug)

if (error.value) {
  throw createError({ statusCode: 404, statusMessage: 'Página não encontrada', fatal: true })
}

if (data.value && 'redirect_to' in data.value) {
  // 301 real e visível ao navegador acontece aqui, não na API (ver
  // App\Http\Controllers\Api\V1\Public\PageController, que devolve 200 nesse hop
  // servidor-a-servidor).
  await navigateTo(`/${data.value.redirect_to}`, { redirectCode: 301, external: false })
}

const page = computed(() => (data.value && 'data' in data.value ? data.value.data : null))

// Título da página-mãe só é buscado quando existe segundo nível — evita um fetch extra nas
// páginas de primeiro nível, que são a maioria.
const { data: parentData } = parentSlug ? await usePublicPage(parentSlug) : { data: ref(null) }
const parentPage = computed(() =>
  parentData.value && 'data' in parentData.value ? parentData.value.data : null,
)

// Trilha de navegação derivada do slug: um segmento vira "Início / Título"; dois segmentos
// viram "Início / Título da mãe / Título atual". A última posição nunca é link.
const breadcrumbItems = computed(() => {
  const items: Array<{ label: string; to?: string }> = [{ label: 'Início', to: '/' }]
  if (parentSlug) {
    items.push({ label: parentPage.value?.title ?? '', to: `/${parentSlug}` })
  }
  items.push({ label: page.value?.title ?? '' })
  return items
})

useSeoMeta({
  title: () => page.value?.meta_title || page.value?.title || 'Lar Anália Franco',
  description: () => page.value?.meta_description || undefined,
  ogTitle: () => page.value?.meta_title || page.value?.title || 'Lar Anália Franco',
  ogDescription: () => page.value?.meta_description || undefined,
})
</script>

<template>
  <template v-if="page">
    <nav class="breadcrumb" aria-label="Trilha de navegação">
      <ol>
        <li v-for="item in breadcrumbItems" :key="item.label">
          <NuxtLink v-if="item.to" :to="item.to">{{ item.label }}</NuxtLink>
          <span v-else aria-current="page">{{ item.label }}</span>
        </li>
      </ol>
    </nav>

    <article class="prose">
      <h1>{{ page.title }}</h1>
      <!-- Conteúdo vem do CMS, escrito por usuário autenticado do painel — não há input de
           visitante aqui. Sanitização no backend é entregável de sessão futura (ver
           docs/roadmap.md); até lá, quem escreve é sempre interno e confiável. -->
      <div class="page-content" v-html="page.content" />
    </article>
  </template>
</template>
