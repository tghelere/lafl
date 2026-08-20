<script setup lang="ts">
// [[slug]] (parâmetro único opcional, não catch-all) casa só com "/quem-somos" e
// "/quem-somos/:um-segmento" — o mesmo limite de dois níveis que a Action SavePage garante
// no backend. Uma URL de três níveis nem chega a resolver esta rota.
const route = useRoute()

const slugParam = route.params.slug as string | undefined
const slug = slugParam ? `quem-somos/${slugParam}` : 'quem-somos'

const { data, error } = await usePublicPage(slug)

if (error.value) {
  throw createError({ statusCode: 404, statusMessage: 'Página não encontrada', fatal: true })
}

if (data.value && 'redirect_to' in data.value) {
  // Slug histórico: o 301 real e visível ao navegador acontece aqui, não na API (ver
  // App\Http\Controllers\Api\V1\Public\PageController).
  await navigateTo(`/${data.value.redirect_to}`, { redirectCode: 301, external: false })
}

const page = computed(() => (data.value && 'data' in data.value ? data.value.data : null))

// Trilha de navegação: derivada da presença do parâmetro de slug, não de dado novo — a
// última posição nunca é link (é a página atual).
const breadcrumbItems = computed(() => {
  const items: Array<{ label: string; to?: string }> = [{ label: 'Início', to: '/' }]
  if (slugParam) {
    items.push({ label: 'Quem somos', to: '/quem-somos' })
    items.push({ label: page.value?.title ?? '' })
  } else {
    items.push({ label: 'Quem somos' })
  }
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
