<script setup lang="ts">
// Sobrepõe [...slug].vue para acomodar as fotos da seção — mesmo padrão de quem-somos.vue.
const { data, error } = await usePublicPage('educacao-infantil')

if (error.value) {
  throw createError({ statusCode: 404, statusMessage: 'Página não encontrada', fatal: true })
}

const page = computed(() => (data.value && 'data' in data.value ? data.value.data : null))

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
      <div class="page-content" v-html="page.content" />
    </article>

    <section class="foto-galeria" aria-label="Fotos da estrutura">
      <h2>A estrutura, em fotos</h2>
      <div class="foto-galeria__grid">
        <AppFoto slug="horta-kids" contexto="metade" prioridade />
        <AppFoto slug="sala-multiuso-conto" contexto="metade" />
        <AppFoto slug="sala-multiuso-imaginacao" contexto="metade" />
        <AppFoto slug="sala-multiuso-brinquedos" contexto="metade" />
      </div>
    </section>
  </template>
</template>

<style scoped>
.foto-galeria {
  margin-block: var(--space-8);
}

.foto-galeria__grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr));
  gap: var(--space-5);
  margin-top: var(--space-5);
}

.foto-galeria__grid picture {
  border-radius: var(--radius-md);
  overflow: hidden;
}
</style>
