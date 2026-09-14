<script setup lang="ts">
// Etapa 1 (docs/design/navegacao.md): fonte de dados. A interação completa do painel
// desktop (medidas, hover com delay, clamp de borda, acessibilidade — spec §4-8) é a Etapa
// 2; a gaveta mobile em tela cheia (spec §9) é a Etapa 3. Por ora, este componente só lê de
// app/config/navigation.ts e mantém um disclosure simples por clique, para o site continuar
// navegável entre as etapas.
import { ctaItem, navigation } from '~/config/navigation'

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

      <nav class="site-header__nav" aria-label="Navegação principal">
        <ul>
          <li v-for="item in navigation" :key="item.to" class="site-header__item">
            <div class="site-header__link-group">
              <NuxtLink :to="item.to">{{ item.label }}</NuxtLink>
              <button
                v-if="item.children?.length"
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
              v-if="item.children?.length"
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

      <NuxtLink :to="ctaItem.to" class="btn btn--primary site-header__cta">
        {{ ctaItem.label }}
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
        aria-label="Navegação principal (mobile)"
        @keydown="onKeydown"
      >
        <ul>
          <li v-for="item in navigation" :key="item.to">
            <NuxtLink :to="item.to">{{ item.label }}</NuxtLink>
            <ul v-if="item.children?.length" class="mobile-nav__children">
              <li v-for="child in item.children" :key="child.to">
                <NuxtLink :to="child.to">{{ child.label }}</NuxtLink>
              </li>
            </ul>
          </li>
        </ul>
        <NuxtLink :to="ctaItem.to" class="btn btn--primary mobile-nav__cta">
          {{ ctaItem.label }}
        </NuxtLink>
      </nav>
    </Transition>
  </header>
</template>
