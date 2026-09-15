<script setup lang="ts">
// Sobrepõe [...slug].vue para acomodar a foto da seção (ver docs/design/navegacao.md sobre
// rota estática vencer catch-all) — mesmo padrão de página CMS de nível 1, só com uma
// <AppFoto> a mais. Conteúdo e busca continuam vindo do CMS (slug "quem-somos").
const { data, error } = await usePublicPage('quem-somos')

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
      <AppFoto slug="equipe-formacao" contexto="metade" prioridade />
      <!-- Conteúdo vem do CMS, escrito por usuário autenticado do painel — não há input de
           visitante aqui. Sanitização no backend é entregável de sessão futura (ver
           docs/roadmap.md); até lá, quem escreve é sempre interno e confiável. -->
      <div class="page-content" v-html="page.content" />
    </article>
  </template>
</template>
