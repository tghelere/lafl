<script setup lang="ts">
// Cartões de /doar — Pix e transferência bancária —, renderizados no lugar do marcador
// {{formas_de_doacao}} do texto do CMS (ver app/pages/doar.vue). Os dados vêm de
// app/config/institution.ts, nunca do CMS. O QR é desenhado no servidor (SSR) como SVG a
// partir do payload; o único JS do cliente é o de copiar.
//
// Um só DOM serve os dois layouts (CSS decide): no desktop o QR fica ao lado da legenda,
// visível; no celular o QR fica num <details>. O SVG existe nas duas posições (é pequeno,
// ~3 KB) porque o SSR não sabe a largura da tela.
import { encode } from 'uqr'

import { bankTransfer, pix, pixKeyFormatted } from '~/config/institution'

const QUIET_ZONE = 4 // módulos de margem branca exigidos pela especificação do QR

// Preto sobre branco fixo, de propósito: leitor de QR espera esse contraste mesmo em tema escuro.
const qrSvg = (() => {
  const { data, size } = encode(pix.payload, { ecc: 'M', border: 0 })
  const total = size + QUIET_ZONE * 2
  let path = ''

  data.forEach((row, y) => {
    let x = 0
    while (x < size) {
      if (!row[x]) {
        x++
        continue
      }
      const start = x
      while (x < size && row[x]) x++
      path += `M${start + QUIET_ZONE},${y + QUIET_ZONE}h${x - start}v1h-${x - start}z`
    }
  })

  return (
    `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${total} ${total}" shape-rendering="crispEdges" role="img" aria-label="QR Code do Pix do Lar Anália Franco">` +
    `<rect width="${total}" height="${total}" fill="#ffffff"/><path fill="#000000" d="${path}"/></svg>`
  )
})()

const keyFormatted = pixKeyFormatted()

type Target = 'code' | 'key' | 'agency' | 'account' | 'cnpj'

const values: Record<Target, string> = {
  code: pix.payload,
  key: pix.key,
  agency: bankTransfer.agency,
  account: bankTransfer.account,
  cnpj: bankTransfer.cnpj,
}

const messages: Record<Target, string> = {
  code: 'Código copiado',
  key: 'Chave copiada',
  agency: 'Agência copiada',
  account: 'Conta copiada',
  cnpj: 'CNPJ copiado',
}

const transferRows: { target: Exclude<Target, 'code' | 'key'>; label: string; value: string }[] = [
  { target: 'agency', label: 'Agência', value: bankTransfer.agency },
  { target: 'account', label: bankTransfer.accountType, value: bankTransfer.account },
  { target: 'cnpj', label: 'CNPJ', value: bankTransfer.cnpj },
]

const copied = ref<Target | null>(null)
const manual = ref(false)
const codeField = ref<HTMLInputElement | null>(null)
const root = ref<HTMLElement | null>(null)
let timer: ReturnType<typeof setTimeout> | undefined

function selectManually(target: Target): void {
  manual.value = true
  nextTick(() => {
    if (target === 'code') {
      codeField.value?.focus()
      codeField.value?.select()
      return
    }
    const element = root.value?.querySelector<HTMLElement>(`[data-valor="${target}"]`)
    if (element) {
      const range = document.createRange()
      range.selectNodeContents(element)
      const selection = window.getSelection()
      selection?.removeAllRanges()
      selection?.addRange(range)
    }
  })
}

async function copy(target: Target): Promise<void> {
  try {
    await navigator.clipboard.writeText(values[target])
  } catch {
    // Sem permissão ou sem a API (HTTP, navegador antigo): deixa o texto selecionado para
    // a pessoa copiar à mão.
    selectManually(target)
    return
  }

  copied.value = target
  clearTimeout(timer)
  timer = setTimeout(() => (copied.value = null), 2000)
}

onBeforeUnmount(() => clearTimeout(timer))

const status = computed(() => (copied.value ? messages[copied.value] : ''))
</script>

<template>
  <div ref="root" class="donate" :class="{ 'donate--manual': manual }" data-testid="formas-de-doacao">
    <section class="card donate__card" aria-labelledby="pix-titulo" data-testid="doe-por-pix">
      <h3 id="pix-titulo" class="donate__title">
        <svg class="donate__icon" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M12 2.5 21.5 12 12 21.5 2.5 12z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
          <path d="M8.5 12 12 8.5l3.5 3.5-3.5 3.5z" fill="currentColor" />
        </svg>
        Doe por Pix
      </h3>

      <div class="donate__body">
        <div class="pix__qr-row">
          <figure class="pix__qr pix__qr--wide">
            <!-- eslint-disable-next-line vue/no-v-html -- SVG gerado aqui mesmo, a partir de constante -->
            <div class="pix__qr-frame" v-html="qrSvg" />
          </figure>
          <p class="pix__caption pix__qr--wide">Aponte a câmera do app do banco para o QR Code, ou copie o código e cole em <strong>Pix copia e cola</strong>.</p>
        </div>

        <details class="pix__qr pix__qr--narrow">
          <summary>Mostrar QR Code (para pagar de outro aparelho)</summary>
          <!-- eslint-disable-next-line vue/no-v-html -- idem -->
          <div class="pix__qr-frame" v-html="qrSvg" />
        </details>

        <input
          ref="codeField"
          class="pix__code"
          type="text"
          readonly
          :value="pix.payload"
          aria-label="Código Pix copia e cola"
          data-testid="pix-codigo"
        />
        <button type="button" class="btn btn--primary donate__wide-button" data-testid="copiar-codigo" @click="copy('code')">
          {{ copied === 'code' ? 'Código copiado' : 'Copiar código Pix' }}
        </button>

        <div class="donate__row">
          <p class="donate__field">
            <span class="donate__label">Chave Pix (CNPJ)</span>
            <span class="donate__value" data-valor="key" data-testid="pix-chave">{{ keyFormatted }}</span>
          </p>
          <button type="button" class="btn btn--secondary donate__copy" data-testid="copiar-chave" @click="copy('key')">
            <svg class="donate__copy-icon" viewBox="0 0 24 24" aria-hidden="true">
              <rect x="9" y="9" width="11" height="11" rx="2" fill="none" stroke="currentColor" stroke-width="2" />
              <path d="M5 15V6a2 2 0 0 1 2-2h9" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
            </svg>
            {{ copied === 'key' ? 'Copiada' : 'Copiar' }}
          </button>
        </div>
      </div>

      <p class="donate__footer">Recebedor: {{ pix.receiverName }}</p>
    </section>

    <section class="card donate__card" aria-labelledby="transferencia-titulo" data-testid="doe-por-transferencia">
      <h3 id="transferencia-titulo" class="donate__title">
        <svg class="donate__icon" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M3 10 12 4l9 6M5 10v8m4-8v8m6-8v8m4-8v8M3 20h18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        Transferência bancária
      </h3>

      <div class="donate__body">
        <div class="donate__row">
          <p class="donate__field">
            <span class="donate__label">Banco</span>
            <span class="donate__value" data-testid="transferencia-banco">{{ bankTransfer.bank }} ({{ bankTransfer.bankCode }})</span>
          </p>
        </div>

        <div v-for="row in transferRows" :key="row.target" class="donate__row">
          <p class="donate__field">
            <span class="donate__label">{{ row.label }}</span>
            <span class="donate__value" :data-valor="row.target" :data-testid="`transferencia-${row.target}`">{{ row.value }}</span>
          </p>
          <button type="button" class="btn btn--secondary donate__copy" :data-testid="`copiar-${row.target}`" @click="copy(row.target)">
            <svg class="donate__copy-icon" viewBox="0 0 24 24" aria-hidden="true">
              <rect x="9" y="9" width="11" height="11" rx="2" fill="none" stroke="currentColor" stroke-width="2" />
              <path d="M5 15V6a2 2 0 0 1 2-2h9" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
            </svg>
            {{ copied === row.target ? 'Copiado' : 'Copiar' }}
          </button>
        </div>
      </div>

      <p class="donate__footer">Favorecido: {{ pix.receiverName }}</p>
    </section>

    <p class="visually-hidden" role="status" aria-live="polite">{{ status }}</p>
  </div>
</template>

<style scoped>
.donate {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: var(--space-5);
  margin-block: var(--space-5) var(--space-6);
}

.donate__card {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
  min-width: 0;
}

.donate__title {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  margin: 0;
}

.donate__icon {
  width: 1.25rem;
  height: 1.25rem;
  flex: none;
  color: var(--color-primary, currentColor);
}

.donate__copy {
  flex: none;
  min-width: 6.5rem;
  height: 2.25rem;
  padding-inline: var(--space-3);
  gap: var(--space-1);
}

.donate__copy-icon {
  width: 1rem;
  height: 1rem;
  flex: none;
}

.donate__body {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.donate__row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  height: 3.5rem; /* igual em todas as linhas, com ou sem botão */
}

.donate__field {
  display: grid;
  min-width: 0;
  margin: 0;
}

.donate__label {
  font-size: var(--text-xs);
  color: var(--color-text-muted);
}

.donate__value {
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  font-size: var(--text-base);
  color: var(--color-heading);
  font-variant-numeric: tabular-nums;
  overflow-wrap: anywhere;
}

.donate__wide-button {
  width: 100%;
}

/* Rodapé empurrado para o fim do cartão: alinha os dois no desktop, com alturas iguais. */
.donate__footer {
  margin: auto 0 0;
  padding-top: var(--space-3);
  border-top: 1px solid var(--color-border);
  font-size: var(--text-sm);
  color: var(--color-text-muted);
}

.pix__qr-row {
  display: flex;
  align-items: center;
  gap: var(--space-4);
}

.pix__caption {
  margin: 0;
  font-size: var(--text-sm);
  color: var(--color-text-muted);
}

/* Preto sobre branco sempre — o frame não herda tema. */
.pix__qr-frame {
  width: 8.75rem;
  background: #fff;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  overflow: hidden;
}

.pix__qr {
  margin: 0;
}

.pix__qr--narrow summary {
  cursor: pointer;
  font-size: var(--text-sm);
  color: var(--color-text);
  min-height: var(--control-height-sm);
  display: flex;
  align-items: center;
}

.pix__qr--narrow .pix__qr-frame {
  margin: var(--space-3) auto 0;
}

/* Campo do código: só aparece se a cópia falhar (seleção manual). */
.pix__code {
  display: none;
  min-width: 0;
  height: var(--control-height-md);
  padding-inline: var(--space-3);
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  font-size: var(--text-xs);
  color: var(--color-text);
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  text-overflow: ellipsis;
}

.donate--manual .pix__code {
  display: block;
}

.pix__qr--wide {
  display: none;
}

@media (min-width: 48rem) {
  .donate {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    align-items: stretch;
  }

  .pix__qr--wide {
    display: block;
  }

  .pix__qr-row {
    display: flex;
  }

  .pix__qr--narrow {
    display: none;
  }
}
</style>
