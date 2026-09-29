<script setup lang="ts">
// "Doar" virou item de topo com rota própria (ver docs/design/navegacao.md §2), mas o
// conteúdo continua sendo a mesma página do CMS (slug "como-ajudar/doar") — não duplicamos
// conteúdo, só damos a ele uma URL de primeiro nível. O link antigo redireciona (301) para
// cá: ver server/routes/como-ajudar/doar.ts.
const { data, error } = await usePublicPage('como-ajudar/doar')

if (error.value) {
  // 404 só quando a API disse que a página não existe; qualquer outra falha vira 503 (ver
  // app/utils/apiPageError.ts).
  lancarErroDePagina(error.value)
}

const page = computed(() => (data.value && 'data' in data.value ? data.value.data : null))

usePageSeo({
  title: () => page.value?.meta_title || page.value?.title || 'Doar — Lar Anália Franco',
  description: () => page.value?.meta_description || undefined,
})
</script>

<template>
  <template v-if="page">
    <nav class="breadcrumb" aria-label="Trilha de navegação">
      <ol>
        <li><NuxtLink to="/">Início</NuxtLink></li>
        <li><span aria-current="page">{{ page.title }}</span></li>
      </ol>
    </nav>

    <article class="prose">
      <h1>{{ page.title }}</h1>
      <!-- Mesma observação de [...slug].vue: conteúdo do CMS, sanitizado no backend ao
           salvar (ver docs/decisoes/0010-html-do-cms-sanitizado-no-backend.md). -->
      <div class="page-content" data-grupo-ampliacao="texto" v-html="page.content" />
    </article>
  </template>
</template>
