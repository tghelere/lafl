<script setup lang="ts">
// Sobrepõe [...slug].vue para acomodar a foto da seção (ver docs/design/navegacao.md sobre
// rota estática vencer catch-all) — mesmo padrão de página CMS de nível 1, só com uma
// <AppFoto> a mais. Conteúdo e busca continuam vindo do CMS (slug "quem-somos").
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
      <AppFoto slug="equipe-formacao" contexto="metade" prioridade />
      <!-- Conteúdo vem do CMS, sanitizado no backend ao salvar contra uma allowlist
           explícita (App\Support\Html\ContentSanitizer, ver
           docs/decisoes/0010-html-do-cms-sanitizado-no-backend.md) — é o que torna este
           v-html seguro, não a confiança em quem escreve pelo painel. -->
      <div class="page-content" v-html="page.content" />
    </article>
  </template>
</template>
