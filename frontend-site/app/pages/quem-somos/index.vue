<script setup lang="ts">
// Sobrepõe [...slug].vue para acomodar a foto da seção (ver docs/design/navegacao.md sobre
// rota estática vencer catch-all) — mesmo padrão de página CMS de nível 1, com a galeria da
// página logo abaixo do título. Conteúdo, fotos e busca vêm do CMS (slug "quem-somos"). A
// capa desta página não aparece aqui: é o destaque da página inicial.
const { data, error } = await usePublicPage('quem-somos')

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
      <AppImagem
        v-for="(imagem, indice) in page.images.gallery"
        :key="imagem.src"
        :imagem="imagem"
        contexto="metade"
        grupo="galeria"
        :prioridade="indice === 0"
      />
      <!-- Conteúdo vem do CMS, sanitizado no backend ao salvar contra uma allowlist
           explícita (App\Support\Html\ContentSanitizer, ver
           docs/decisoes/0010-html-do-cms-sanitizado-no-backend.md) — é o que torna este
           v-html seguro, não a confiança em quem escreve pelo painel. -->
      <div class="page-content" data-grupo-ampliacao="texto" v-html="page.content" />
    </article>
  </template>
</template>
