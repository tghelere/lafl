<script setup lang="ts">
// Sete itens de primeiro nível, peso igual — ver docs/estrutura-site.md §1.1. As seis rotas
// além de "Quem somos" ainda não existem como página (ver docs/roadmap.md); o link já reflete
// o mapa do site, mas `crawlLinks: false` em nuxt.config.ts impede que isso quebre o
// `nuxt generate` enquanto elas não forem implementadas.
const navItems = [
  { label: 'Home', to: '/' },
  { label: 'Quem somos', to: '/quem-somos' },
  { label: 'Educação infantil', to: '/educacao-infantil' },
  { label: 'Contraturno', to: '/contraturno' },
  { label: 'Bazar', to: '/bazar' },
  { label: 'Como ajudar', to: '/como-ajudar' },
  { label: 'Transparência', to: '/transparencia' },
]

const isOpen = ref(false)
const route = useRoute()

function close() {
  isOpen.value = false
}

function toggle() {
  isOpen.value = !isOpen.value
}

watch(
  () => route.fullPath,
  () => close(),
)

function onKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape') close()
}
</script>

<template>
  <header class="site-header">
    <div class="container site-header__bar">
      <NuxtLink to="/" class="site-header__mark">Lar Anália Franco</NuxtLink>

      <nav class="site-header__nav" aria-label="Menu principal">
        <ul>
          <li v-for="item in navItems" :key="item.to">
            <NuxtLink :to="item.to">{{ item.label }}</NuxtLink>
          </li>
        </ul>
      </nav>

      <NuxtLink to="/como-ajudar/doar" class="btn btn--primary site-header__cta">
        Doar
      </NuxtLink>

      <button
        type="button"
        class="site-header__toggle"
        :aria-expanded="isOpen"
        aria-controls="mobile-nav"
        @click="toggle"
        @keydown="onKeydown"
      >
        <span class="site-header__toggle-icon" aria-hidden="true" />
        {{ isOpen ? 'Fechar' : 'Menu' }}
      </button>
    </div>

    <Transition name="mobile-nav">
      <nav
        v-if="isOpen"
        id="mobile-nav"
        class="mobile-nav"
        aria-label="Menu principal (mobile)"
        @keydown="onKeydown"
      >
        <ul>
          <li v-for="item in navItems" :key="item.to">
            <NuxtLink :to="item.to">{{ item.label }}</NuxtLink>
          </li>
        </ul>
        <NuxtLink to="/como-ajudar/doar" class="btn btn--primary mobile-nav__cta">
          Doar
        </NuxtLink>
      </nav>
    </Transition>
  </header>
</template>
