import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'

/**
 * A mesma consulta da media query que devolve a navegação lateral fixa em
 * src/assets/css/components.css — 64rem (1024px).
 *
 * O limiar está escrito duas vezes de propósito: o CSS decide o DESENHO (coluna ao lado do
 * conteúdo ou gaveta fora do fluxo) e o JavaScript decide o COMPORTAMENTO (prender o foco,
 * fechar com Esc, cortinado, travar a rolagem de fundo). Não há como um ler o outro sem
 * inventar um atributo só para isso. Mudou aqui, mude lá.
 */
export const NAV_STATIC_QUERY = '(min-width: 64rem)'

/**
 * O que é focável dentro da gaveta AGORA. Consultado a cada Tab, nunca guardado: o conteúdo do
 * menu muda com o mapa de acesso do usuário e com o contador de não lidos, e uma lista
 * memorizada no momento da abertura envelheceria em silêncio.
 */
function focusablesOf(container: HTMLElement | null): HTMLElement[] {
  if (!container) {
    return []
  }

  const candidates = container.querySelectorAll<HTMLElement>(
    'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
  )

  // getClientRects().length descarta o que está escondido por CSS (o botão Fechar some em
  // telas largas) sem precisar repetir aqui a regra que o esconde.
  return [...candidates].filter((element) => element.getClientRects().length > 0)
}

/**
 * A navegação lateral como gaveta, abaixo de 64rem: abre pelo botão da barra superior, fecha
 * ao navegar, ao clicar fora, no botão Fechar e com Esc, e enquanto está aberta o foco não sai
 * de dentro dela.
 *
 * O foco preso não é enfeite: a gaveta cobre o conteúdo, e sem isso o Tab continuaria andando
 * pelos links da tela ATRÁS do cortinado — invisíveis, mas focáveis. Quem navega por teclado
 * perderia o rastro do foco na primeira tecla.
 *
 * `drawerEl` devolve o elemento da gaveta; `toggleEl`, o botão que a abre — é para ele que o
 * foco volta quando a gaveta fecha, senão o foco cairia no `<body>` e a navegação por teclado
 * recomeçaria do topo da página. São funções, e não refs, porque a gaveta é um componente: o
 * elemento de raiz dele só existe depois da montagem, e quem chama já tem um jeito de chegar
 * até ele.
 */
export function useNavDrawer(drawerEl: () => HTMLElement | null, toggleEl: () => HTMLElement | null) {
  // Lido já na criação, não só no onMounted: o painel é uma SPA (sem SSR), e esperar a
  // montagem faria a primeira pintura sair sem o botão de menu em tela estreita.
  const isStatic = ref(window.matchMedia(NAV_STATIC_QUERY).matches)
  const isOpen = ref(false)

  const route = useRoute()
  const media = window.matchMedia(NAV_STATIC_QUERY)

  function open(): void {
    isOpen.value = true

    // Duas quebras de quadro: a primeira deixa o Vue aplicar a classe que torna a gaveta
    // visível, a segunda deixa o navegador refletir isso — um foco pedido em elemento ainda
    // `visibility: hidden` simplesmente não acontece.
    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        focusablesOf(drawerEl())[0]?.focus()
      })
    })
  }

  function close(): void {
    // Só devolve o foco se ele estava DENTRO da gaveta. Fechar clicando no cortinado deixa o
    // foco no `<body>`; puxá-lo para o botão de menu nesse caso seria mover o foco de alguém
    // que estava usando o mouse.
    const hadFocus = drawerEl()?.contains(document.activeElement) ?? false

    isOpen.value = false

    if (hadFocus) {
      toggleEl()?.focus()
    }
  }

  function toggleOpen(): void {
    if (isOpen.value) {
      close()

      return
    }

    open()
  }

  function onKeydown(event: KeyboardEvent): void {
    if (!isOpen.value || isStatic.value) {
      return
    }

    if (event.key === 'Escape') {
      close()

      return
    }

    if (event.key !== 'Tab') {
      return
    }

    const focusables = focusablesOf(drawerEl())
    const first = focusables[0]
    const last = focusables[focusables.length - 1]

    if (!first || !last) {
      return
    }

    const active = document.activeElement
    const inside = drawerEl()?.contains(active) ?? false

    if (event.shiftKey ? active === first || !inside : active === last || !inside) {
      event.preventDefault()
      ;(event.shiftKey ? last : first).focus()
    }
  }

  function onMediaChange(event: MediaQueryListEvent): void {
    isStatic.value = event.matches

    // Alargou a janela no meio da gaveta aberta: ela vira coluna fixa outra vez, e o estado
    // "aberta" deixa de significar qualquer coisa. Sem isto, o cortinado e a trava de rolagem
    // continuariam valendo numa tela em que não existe gaveta nenhuma.
    if (event.matches) {
      isOpen.value = false
    }
  }

  // Rolar o conteúdo de trás enquanto a gaveta cobre a tela é desorientador: a pessoa volta de
  // um menu que não levou a lugar nenhum e a tela está em outro ponto.
  watch([isOpen, isStatic], ([open, staticNav]) => {
    document.body.classList.toggle('has-open-drawer', open && !staticNav)
  })

  watch(() => route.fullPath, close)

  onMounted(() => {
    media.addEventListener('change', onMediaChange)
    document.addEventListener('keydown', onKeydown)
  })

  onBeforeUnmount(() => {
    media.removeEventListener('change', onMediaChange)
    document.removeEventListener('keydown', onKeydown)
    document.body.classList.remove('has-open-drawer')
  })

  return { isOpen, isStatic, open, close, toggle: toggleOpen }
}
