<script setup lang="ts">
// Header desktop — docs/design/navegacao.md §4-8, §11. A gaveta mobile (§9) ainda é a versão
// simples da Etapa 1; a Etapa 3 reescreve o bloco <Transition name="mobile-nav"> abaixo.
import { ctaItem, navigation, type NavItem } from '~/config/navigation'

const route = useRoute()

const openTo = ref<string | null>(null)
const panelAlign = ref<'left' | 'right'>('left')
let openTimer: ReturnType<typeof setTimeout> | null = null
let closeTimer: ReturnType<typeof setTimeout> | null = null

const itemRefs: Record<string, HTMLElement | null> = {}
const panelRefs: Record<string, HTMLElement | null> = {}

function setItemRef(to: string, el: Element | null) {
  itemRefs[to] = el as HTMLElement | null
}

function setPanelRef(to: string, el: Element | null) {
  panelRefs[to] = el as HTMLElement | null
}

function clearTimers() {
  if (openTimer) {
    clearTimeout(openTimer)
    openTimer = null
  }
  if (closeTimer) {
    clearTimeout(closeTimer)
    closeTimer = null
  }
}

async function measureClamp() {
  await nextTick()
  const panel = openTo.value ? panelRefs[openTo.value] : null
  if (!panel) return
  panelAlign.value = 'left'
  await nextTick()
  const rect = panel.getBoundingClientRect()
  if (rect.right > window.innerWidth - 16) {
    panelAlign.value = 'right'
  }
}

watch(openTo, (value) => {
  if (value) void measureClamp()
})

function isCoarsePointer() {
  return typeof window !== 'undefined' && window.matchMedia('(hover: none)').matches
}

function openNow(to: string) {
  clearTimers()
  openTo.value = to
}

function onItemMouseEnter(item: NavItem) {
  if (!item.children?.length || isCoarsePointer()) return
  clearTimers()
  if (openTo.value && openTo.value !== item.to) {
    // Mouse entra em outro item com painel aberto: troca imediata, sem delay (spec §7).
    openTo.value = item.to
    return
  }
  openTimer = setTimeout(() => {
    openTo.value = item.to
  }, 80)
}

function onItemMouseLeave() {
  if (isCoarsePointer()) return
  clearTimers()
  closeTimer = setTimeout(() => {
    openTo.value = null
  }, 150)
}

function onItemFocusOut(item: NavItem) {
  // Não usa event.relatedTarget: não vem populado de forma confiável em todo navegador para
  // focusout disparado por Tab (testado em browser real). requestAnimationFrame +
  // document.activeElement funciona em qualquer caso — o foco já pousou no próximo elemento
  // quando o callback roda.
  requestAnimationFrame(() => {
    const container = itemRefs[item.to]
    if (container && !container.contains(document.activeElement)) {
      clearTimers()
      if (openTo.value === item.to) openTo.value = null
    }
  })
}

function onTriggerKeydown(item: NavItem, event: KeyboardEvent) {
  // Enter/Espaço não são tratados aqui: preventDefault() no keydown não suprime de forma
  // confiável o click sintético que o navegador dispara para ativação de <button> por
  // teclado (testado em browser real — a mesma tecla acabava chamando as duas rotas).
  // onTriggerClick distingue teclado de mouse via event.detail === 0.
  if (event.key === 'Escape') {
    clearTimers()
    openTo.value = null
    itemRefs[item.to]?.querySelector('button')?.focus()
  }
}

function onTriggerClick(item: NavItem, event: MouseEvent) {
  if (!item.children?.length) return

  // event.detail === 0 identifica um click sintético de teclado (Enter/Espaço num
  // <button>) — cliques de mouse reais sempre têm detail >= 1. Spec não define o
  // comportamento da segunda ativação por teclado — escolha registrada no commit: espelha
  // o "primeiro toque abre, segundo navega" do ponteiro coarse (§7).
  const isKeyboardActivation = event.detail === 0

  if (isKeyboardActivation || isCoarsePointer()) {
    if (openTo.value !== item.to) {
      openNow(item.to)
      return
    }
  }

  clearTimers()
  openTo.value = null
  navigateTo(item.to)
}

watch(
  () => route.fullPath,
  () => {
    clearTimers()
    openTo.value = null
  },
)

function onDocumentClick(event: MouseEvent) {
  if (!openTo.value) return
  const container = itemRefs[openTo.value]
  if (container && !container.contains(event.target as Node)) {
    clearTimers()
    openTo.value = null
  }
}

onMounted(() => document.addEventListener('click', onDocumentClick))
onBeforeUnmount(() => {
  document.removeEventListener('click', onDocumentClick)
  clearTimers()
})

// --- Gaveta mobile (Etapa 3 reescreve isto) ---
const isMobileOpen = ref(false)

function closeMobile() {
  isMobileOpen.value = false
}

function toggleMobile() {
  isMobileOpen.value = !isMobileOpen.value
}

watch(
  () => route.fullPath,
  () => closeMobile(),
)

function onMobileKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape') closeMobile()
}
</script>

<template>
  <header class="site-header">
    <div class="site-header__container site-header__bar">
      <NuxtLink to="/" class="site-header__mark">Lar Anália Franco</NuxtLink>

      <nav class="site-header__nav" aria-label="Navegação principal">
        <ul>
          <li
            v-for="item in navigation"
            :key="item.to"
            :ref="(el) => setItemRef(item.to, el)"
            class="site-header__item"
            @mouseenter="onItemMouseEnter(item)"
            @mouseleave="onItemMouseLeave"
            @focusout="onItemFocusOut(item)"
          >
            <button
              v-if="item.children?.length"
              type="button"
              class="site-header__trigger"
              :aria-expanded="openTo === item.to"
              :aria-controls="`panel-${item.to.slice(1)}`"
              @click="onTriggerClick(item, $event)"
              @keydown="onTriggerKeydown(item, $event)"
            >
              <span class="site-header__label">{{ item.label }}</span>
              <span aria-hidden="true" class="site-header__arrow" />
            </button>
            <NuxtLink v-else :to="item.to" class="site-header__trigger">
              <span class="site-header__label">{{ item.label }}</span>
            </NuxtLink>

            <ul
              v-if="item.children?.length"
              :id="`panel-${item.to.slice(1)}`"
              :ref="(el) => setPanelRef(item.to, el)"
              v-show="openTo === item.to"
              class="site-header__panel"
              :class="{
                'site-header__panel--wide': item.to === '/o-que-fazemos',
                'site-header__panel--right': panelAlign === 'right',
              }"
            >
              <li v-for="child in item.children" :key="child.to">
                <NuxtLink :to="child.to" class="site-header__panel-link">
                  <span>{{ child.label }}</span>
                  <span v-if="child.hint" class="site-header__panel-hint">{{ child.hint }}</span>
                </NuxtLink>
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
        :aria-expanded="isMobileOpen"
        aria-controls="mobile-nav"
        @click="toggleMobile"
        @keydown="onMobileKeydown"
      >
        <span class="site-header__toggle-icon" aria-hidden="true" />
        {{ isMobileOpen ? 'Fechar' : 'Menu' }}
      </button>
    </div>

    <Transition name="mobile-nav">
      <nav
        v-if="isMobileOpen"
        id="mobile-nav"
        class="mobile-nav"
        aria-label="Navegação principal (mobile)"
        @keydown="onMobileKeydown"
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
