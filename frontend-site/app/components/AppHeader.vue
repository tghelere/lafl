<script setup lang="ts">
// Sete itens de primeiro nível, peso igual — ver docs/estrutura-site.md §1.1. Cada item
// (exceto Home) abre um submenu com as páginas filhas, definidas em app/config/siteNav.ts —
// única fonte de verdade compartilhada com AppSectionNav.vue.
//
// O submenu abre só por clique/toque (@click num <button>), nunca por :hover — um botão
// nativo já responde a mouse, teclado (Enter/Espaço) e toque sem tratamento especial; :hover
// sozinho não serve toque nenhum. Ver relatório da sessão.
import { siteNav } from '~/config/siteNav'

const navItems = [{ label: 'Home', to: '/', children: [] }, ...siteNav]

const isOpen = ref(false)
const openSection = ref<string | null>(null)
const route = useRoute()
const headerEl = ref<HTMLElement | null>(null)

function close() {
  isOpen.value = false
  openSection.value = null
}

function toggle() {
  isOpen.value = !isOpen.value
}

function toggleSection(to: string) {
  openSection.value = openSection.value === to ? null : to
}

watch(
  () => route.fullPath,
  () => close(),
)

function onKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape') close()
}

function onDocumentClick(event: MouseEvent) {
  if (openSection.value && headerEl.value && !headerEl.value.contains(event.target as Node)) {
    openSection.value = null
  }
}

onMounted(() => document.addEventListener('click', onDocumentClick))
onBeforeUnmount(() => document.removeEventListener('click', onDocumentClick))
</script>

<template>
  <header ref="headerEl" class="site-header">
    <div class="container site-header__bar">
      <NuxtLink to="/" class="site-header__mark">Lar Anália Franco</NuxtLink>

      <nav class="site-header__nav" aria-label="Menu principal">
        <ul>
          <li v-for="item in navItems" :key="item.to" class="site-header__item">
            <div class="site-header__link-group">
              <NuxtLink :to="item.to">{{ item.label }}</NuxtLink>
              <button
                v-if="item.children.length"
                type="button"
                class="site-header__submenu-toggle"
                :aria-expanded="openSection === item.to"
                :aria-controls="`submenu-${item.to.slice(1)}`"
                :aria-label="`Abrir submenu de ${item.label}`"
                @click="toggleSection(item.to)"
                @keydown="onKeydown"
              >
                <span aria-hidden="true" class="site-header__chevron" />
              </button>
            </div>
            <ul
              v-if="item.children.length"
              :id="`submenu-${item.to.slice(1)}`"
              v-show="openSection === item.to"
              class="site-header__submenu"
              @keydown="onKeydown"
            >
              <li v-for="child in item.children" :key="child.to">
                <NuxtLink :to="child.to">{{ child.label }}</NuxtLink>
              </li>
            </ul>
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
            <ul v-if="item.children.length" class="mobile-nav__children">
              <li v-for="child in item.children" :key="child.to">
                <NuxtLink :to="child.to">{{ child.label }}</NuxtLink>
              </li>
            </ul>
          </li>
        </ul>
        <NuxtLink to="/como-ajudar/doar" class="btn btn--primary mobile-nav__cta">
          Doar
        </NuxtLink>
      </nav>
    </Transition>
  </header>
</template>
