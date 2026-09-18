<script setup lang="ts">
// Sobrepõe [...slug].vue para acomodar as fotos da seção — mesmo padrão de quem-somos.vue.
const { data, error } = await usePublicPage('bazar')

if (error.value) {
  throw createError({ statusCode: 404, statusMessage: 'Página não encontrada', fatal: true })
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
      <div class="page-content" v-html="page.content" />
    </article>

    <section class="foto-galeria" aria-label="Fotos da loja">
      <h2>A loja, em fotos</h2>
      <div class="foto-galeria__grid">
        <AppFoto slug="bazar-placa" contexto="metade" prioridade />
        <AppFoto slug="bazar-entrada" contexto="metade" />
        <AppFoto slug="bazar-moveis" contexto="metade" />
        <AppFoto slug="bazar-salao" contexto="metade" />
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
