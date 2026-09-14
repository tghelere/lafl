<script setup lang="ts">
// Navegação de seção: lista as páginas irmãs da seção atual (ver app/config/siteNav.ts),
// com a página corrente destacada e sem link — mesmo padrão do breadcrumb em [...slug].vue.
// Não renderiza nada fora de uma seção conhecida (home, contato, política de privacidade —
// ver relatório da sessão sobre por que essas ficam de fora por design, não por omissão).
import { siteNav } from '~/config/siteNav'

const route = useRoute()

const section = computed(() => {
  const firstSegment = route.path.split('/').filter(Boolean)[0]
  return siteNav.find((item) => item.to === `/${firstSegment}`) ?? null
})

const items = computed(() => {
  if (!section.value) return []
  return [
    { label: section.value.label, to: section.value.to },
    ...section.value.children,
  ]
})
</script>

<template>
  <nav v-if="section" class="section-nav" aria-label="Páginas desta seção">
    <ul>
      <li v-for="item in items" :key="item.to">
        <NuxtLink v-if="item.to !== route.path" :to="item.to">{{ item.label }}</NuxtLink>
        <span v-else aria-current="page">{{ item.label }}</span>
      </li>
    </ul>
  </nav>
</template>
