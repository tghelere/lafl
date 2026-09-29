<script setup lang="ts">
// Ampliação das imagens de conteúdo (sessão 30). Sem biblioteca: <dialog> nativo, eventos de
// ponteiro e CSS. O navegador já dá o fundo inerte, o Esc e a camada de cima; o resto é pouco
// código, e cada dependência nova é superfície de ataque que o site não precisa.
//
// Progressiva: toda imagem ampliável já é um link para a maior derivada (`a[data-ampliar]`,
// montado pela API no texto e por AppImagem na galeria). Sem JavaScript, o link abre a foto.
// Com ele, um único ouvinte de clique no documento intercepta o link e abre esta ampliação.
// Clique com Ctrl, Cmd, Shift ou botão do meio segue o link, para abrir em outra aba.
//
// Grupo: as imagens com o mesmo `data-grupo` (galeria) ou dentro do mesmo
// `[data-grupo-ampliacao]` (texto da página) navegam entre si, na ordem em que aparecem.
//
// Acessibilidade, além do que o <dialog> modal já dá (fundo inerte, Esc):
// - o foco fica preso aqui dentro: Tab e Shift+Tab circulam entre os controles, sem depender
//   do navegador (no Firefox, o Tab no último controle pode ir para a barra de endereço);
// - ao fechar, por qualquer caminho, o foco volta ao link da imagem de ORIGEM, a que foi
//   clicada, para quem usa teclado continuar de onde estava;
// - o botão de navegação que desliga na ponta não leva o foco embora: ele passa ao outro;
// - cada troca é anunciada ("Imagem 2 de 4: …") numa região aria-live, porque a imagem nova
//   não recebe foco.
import { ChevronLeft, ChevronRight, X } from '@lucide/vue'

type Fonte = { url: string; largura: number }

type Item = {
  link: HTMLAnchorElement
  fontes: Fonte[]
  largura: number
  altura: number
  alt: string
  legenda: string | null
  credito: string | null
}

const dialogo = ref<HTMLDialogElement | null>(null)
const fechador = ref<HTMLButtonElement | null>(null)
const anterior = ref<HTMLButtonElement | null>(null)
const proxima = ref<HTMLButtonElement | null>(null)
let origem: HTMLAnchorElement | null = null
const palco = ref<HTMLElement | null>(null)
const itens = ref<Item[]>([])
const indice = ref(0)
const espaco = ref({ largura: 0, altura: 0, densidade: 1 })

const atual = computed(() => itens.value[indice.value] ?? null)
const variasImagens = computed(() => itens.value.length > 1)

const anuncio = computed(() => {
  const item = atual.value

  if (!item) {
    return ''
  }

  return variasImagens.value ? `Imagem ${indice.value + 1} de ${itens.value.length}: ${item.alt}` : item.alt
})

/**
 * A maior derivada que cabe na tela: a imagem aparece do tamanho que o palco permite (sem
 * passar da proporção), e a derivada escolhida é a maior cuja largura não passa desse tamanho
 * em pixels do aparelho. Nenhuma coube (tela menor que a menor derivada): a menor.
 */
const escolhida = computed<Fonte | null>(() => {
  const item = atual.value

  // Antes de medir o palco não há tamanho: esperar, em vez de baixar a menor e trocar em seguida.
  if (!item || item.fontes.length === 0 || espaco.value.largura === 0) {
    return null
  }

  const { largura, altura, densidade } = espaco.value
  const proporcao = item.largura / item.altura
  const exibida = Math.min(largura, altura * proporcao)
  const alvo = exibida * densidade
  const cabem = item.fontes.filter((fonte) => fonte.largura <= alvo)

  return cabem.length > 0 ? cabem[cabem.length - 1]! : item.fontes[0]!
})

function lerFontes(img: HTMLImageElement, link: HTMLAnchorElement): Fonte[] {
  const fontes = (img.getAttribute('srcset') ?? '')
    .split(',')
    .map((parte) => parte.trim().split(/\s+/))
    .filter(([url, descritor]) => url && descritor?.endsWith('w'))
    .map(([url, descritor]) => ({ url: url!, largura: Number.parseInt(descritor!, 10) }))
    .sort((a, b) => a.largura - b.largura)

  return fontes.length > 0 ? fontes : [{ url: link.getAttribute('href') ?? '', largura: img.width }]
}

function lerItem(link: HTMLAnchorElement): Item | null {
  const img = link.querySelector('img')

  if (!img) {
    return null
  }

  const legenda = link.closest('figure')?.querySelector('figcaption')?.textContent?.trim()

  return {
    link,
    fontes: lerFontes(img, link),
    largura: Number(img.getAttribute('width')) || img.naturalWidth || 1,
    altura: Number(img.getAttribute('height')) || img.naturalHeight || 1,
    alt: img.getAttribute('alt') ?? '',
    legenda: legenda || null,
    credito: link.dataset.credito || null,
  }
}

function grupoDe(link: HTMLAnchorElement): string {
  return link.dataset.grupo ?? link.closest<HTMLElement>('[data-grupo-ampliacao]')?.dataset.grupoAmpliacao ?? ''
}

function medir(): void {
  const caixa = palco.value?.getBoundingClientRect()

  espaco.value = {
    largura: caixa?.width ?? window.innerWidth,
    altura: caixa?.height ?? window.innerHeight,
    densidade: window.devicePixelRatio || 1,
  }
}

async function abrir(link: HTMLAnchorElement): Promise<boolean> {
  const grupo = grupoDe(link)
  const links = grupo
    ? Array.from(document.querySelectorAll<HTMLAnchorElement>('a[data-ampliar]')).filter((candidato) => grupoDe(candidato) === grupo)
    : [link]
  const lidos = links.map(lerItem).filter((item): item is Item => item !== null)
  const posicao = lidos.findIndex((item) => item.link === link)

  if (posicao === -1 || !dialogo.value) {
    return false
  }

  itens.value = lidos
  indice.value = posicao
  origem = link
  espaco.value = { largura: 0, altura: 0, densidade: 1 }
  document.documentElement.classList.add('ampliacao-aberta')
  // Desenhar o conteúdo ANTES de abrir: o showModal() põe o foco no primeiro controle que
  // existir, e sem o conteúdo desenhado o foco cairia no próprio diálogo, fora dos botões.
  await nextTick()
  dialogo.value.showModal()
  fechador.value?.focus()
  medir()

  return true
}

function fechar(): void {
  dialogo.value?.close()
}

function aoFechar(): void {
  document.documentElement.classList.remove('ampliacao-aberta')
  itens.value = []
  origem?.focus()
  origem = null
}

async function irPara(novo: number): Promise<void> {
  if (novo < 0 || novo >= itens.value.length) {
    return
  }

  indice.value = novo
  await nextTick()

  // Na ponta, o botão com foco desliga; o foco passa ao outro sentido, e não para o <body>.
  const focado = document.activeElement

  if (focado === proxima.value && proxima.value?.disabled) {
    anterior.value?.focus()
  } else if (focado === anterior.value && anterior.value?.disabled) {
    proxima.value?.focus()
  }
}

function controles(): HTMLElement[] {
  return Array.from(dialogo.value?.querySelectorAll<HTMLElement>('button:not([disabled])') ?? [])
}

function prenderFoco(evento: KeyboardEvent): void {
  const lista = controles()

  if (lista.length === 0) {
    return
  }

  const primeiro = lista[0]!
  const ultimo = lista[lista.length - 1]!
  const dentro = lista.includes(document.activeElement as HTMLElement)

  if (evento.shiftKey && (document.activeElement === primeiro || !dentro)) {
    evento.preventDefault()
    ultimo.focus()
  } else if (!evento.shiftKey && (document.activeElement === ultimo || !dentro)) {
    evento.preventDefault()
    primeiro.focus()
  }
}

function aoClicarNoDocumento(evento: MouseEvent): void {
  if (evento.defaultPrevented || evento.button !== 0 || evento.metaKey || evento.ctrlKey || evento.shiftKey || evento.altKey) {
    return
  }

  const link = (evento.target as Element | null)?.closest?.<HTMLAnchorElement>('a[data-ampliar]')

  if (!link) {
    return
  }

  evento.preventDefault()
  void abrir(link).then((abriu) => {
    // Sem imagem legível no link (não deveria acontecer): o link segue o caminho normal.
    if (!abriu) {
      window.location.href = link.href
    }
  })
}

/** Clique fora da imagem e dos controles fecha: no fundo do diálogo ou no palco vazio. */
function aoClicarNoDialogo(evento: MouseEvent): void {
  if (arrastou) {
    arrastou = false

    return
  }

  if (evento.target === dialogo.value || evento.target === palco.value) {
    fechar()
  }
}

function aoTeclar(evento: KeyboardEvent): void {
  if (evento.key === 'Tab') {
    prenderFoco(evento)
  } else if (evento.key === 'ArrowLeft') {
    evento.preventDefault()
    void irPara(indice.value - 1)
  } else if (evento.key === 'ArrowRight') {
    evento.preventDefault()
    void irPara(indice.value + 1)
  }
}

// Deslize no toque: horizontal, com pelo menos 50px e mais largo que alto (para não brigar
// com a rolagem). Um deslize não conta como clique fora.
let inicio: { x: number; y: number } | null = null
let arrastou = false

function aoTocar(evento: PointerEvent): void {
  inicio = { x: evento.clientX, y: evento.clientY }
  arrastou = false
}

function aoSoltar(evento: PointerEvent): void {
  if (!inicio) {
    return
  }

  const dx = evento.clientX - inicio.x
  const dy = evento.clientY - inicio.y
  inicio = null

  if (Math.abs(dx) >= 50 && Math.abs(dx) > Math.abs(dy) * 1.5) {
    arrastou = true
    void irPara(indice.value + (dx < 0 ? 1 : -1))
  }
}

onMounted(() => {
  document.addEventListener('click', aoClicarNoDocumento)
  window.addEventListener('resize', medir)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', aoClicarNoDocumento)
  window.removeEventListener('resize', medir)
})
</script>

<template>
  <dialog
    ref="dialogo"
    class="ampliacao"
    aria-label="Imagem ampliada"
    :aria-describedby="atual?.legenda || atual?.credito ? 'ampliacao-legenda' : undefined"
    @close="aoFechar"
    @click="aoClicarNoDialogo"
    @keydown="aoTeclar"
  >
    <template v-if="atual">
      <p class="visually-hidden" aria-live="polite">{{ anuncio }}</p>

      <div class="ampliacao__barra">
        <p v-if="variasImagens" class="ampliacao__posicao" aria-hidden="true">{{ indice + 1 }} de {{ itens.length }}</p>
        <button ref="fechador" type="button" class="ampliacao__botao ampliacao__fechar" @click="fechar">
          <X :size="22" aria-hidden="true" />
          Fechar
        </button>
      </div>

      <div
        ref="palco"
        class="ampliacao__palco"
        @pointerdown="aoTocar"
        @pointerup="aoSoltar"
      >
        <img
          v-if="escolhida"
          :key="escolhida.url"
          class="ampliacao__imagem"
          :src="escolhida.url"
          :data-largura="escolhida.largura"
          :width="atual.largura"
          :height="atual.altura"
          :alt="atual.alt"
          draggable="false"
        />
      </div>

      <div v-if="variasImagens" class="ampliacao__navegacao">
        <button
          ref="anterior"
          type="button"
          class="ampliacao__botao"
          :disabled="indice === 0"
          @click="irPara(indice - 1)"
        >
          <ChevronLeft :size="22" aria-hidden="true" />
          Imagem anterior
        </button>
        <button
          ref="proxima"
          type="button"
          class="ampliacao__botao"
          :disabled="indice === itens.length - 1"
          @click="irPara(indice + 1)"
        >
          Próxima imagem
          <ChevronRight :size="22" aria-hidden="true" />
        </button>
      </div>

      <div v-if="atual.legenda || atual.credito" id="ampliacao-legenda" class="ampliacao__legenda">
        <p v-if="atual.legenda">{{ atual.legenda }}</p>
        <p v-if="atual.credito" class="ampliacao__credito">{{ atual.credito }}</p>
      </div>
    </template>
  </dialog>
</template>

<style scoped>
.ampliacao {
  width: min(100vw - 2rem, 80rem);
  height: min(100dvh - 2rem, 60rem);
  max-width: none;
  max-height: none;
  margin: auto;
  padding: var(--space-4);
  border: 0;
  border-radius: var(--radius-lg);
  background: #111;
  color: #f4f1ec;
}

.ampliacao[open] {
  display: grid;
  grid-template-rows: auto minmax(0, 1fr) auto auto;
  gap: var(--space-3);
}

.ampliacao::backdrop {
  background: rgb(0 0 0 / 0.8);
}

.ampliacao__barra {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: var(--space-3);
}

.ampliacao__posicao {
  margin: 0 auto 0 0;
  font-size: var(--text-sm);
  font-variant-numeric: tabular-nums;
}

.ampliacao__palco {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 0;
  touch-action: pan-y;
}

.ampliacao__imagem {
  width: auto;
  height: auto;
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
  user-select: none;
}

.ampliacao__navegacao {
  display: flex;
  justify-content: center;
  gap: var(--space-3);
}

.ampliacao__botao {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  min-height: 2.75rem;
  padding: 0 var(--space-4);
  border: 1px solid rgb(255 255 255 / 0.35);
  border-radius: var(--radius-md);
  background: transparent;
  color: inherit;
  font: inherit;
  font-size: var(--text-sm);
  cursor: pointer;
}

.ampliacao__botao:hover:not(:disabled) {
  background: rgb(255 255 255 / 0.12);
}

.ampliacao__botao:disabled {
  opacity: 0.4;
  cursor: default;
}

.ampliacao__legenda {
  max-width: 60ch;
  margin: 0 auto;
  text-align: center;
  font-size: var(--text-sm);
}

.ampliacao__legenda p {
  margin: 0;
}

.ampliacao__credito {
  margin-top: var(--space-1);
  opacity: 0.75;
  font-size: var(--text-xs);
}
</style>
