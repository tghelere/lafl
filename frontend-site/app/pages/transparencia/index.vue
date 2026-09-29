<script setup lang="ts">
// Sobrepõe [...slug].vue para acomodar a galeria da seção — mesmo padrão de quem-somos.vue.
// As fotos vêm da biblioteca do painel, na ordem da galeria da página (AppGaleria).
const { data, error } = await usePublicPage('transparencia')

if (error.value) {
  // 404 só quando a API disse que a página não existe; qualquer outra falha vira 503 (ver
  // app/utils/apiPageError.ts).
  lancarErroDePagina(error.value)
}

const page = computed(() => (data.value && 'data' in data.value ? data.value.data : null))

usePageSeo({
  title: () => page.value?.meta_title || page.value?.title || 'Lar Anália Franco',
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

    <AppSectionNav />

    <article class="prose">
      <h1>{{ page.title }}</h1>
      <!-- Conteúdo vem do CMS, sanitizado no backend ao salvar contra uma allowlist
           explícita (App\Support\Html\ContentSanitizer, ver
           docs/decisoes/0010-html-do-cms-sanitizado-no-backend.md) — é o que torna este
           v-html seguro, não a confiança em quem escreve pelo painel. -->
      <div class="page-content" data-grupo-ampliacao="texto" v-html="page.content" />
    </article>

    <AppGaleria
      :imagens="page.images.gallery"
      titulo="Controles internos, em registro"
      rotulo="Registro dos controles internos"
      coluna-minima="14rem"
      prioridade
    />
  </template>
</template>

