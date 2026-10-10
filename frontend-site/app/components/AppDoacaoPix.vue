<script setup lang="ts">
// Seção "Doe por Pix" de /doar. O QR é desenhado no servidor (SSR) como SVG a partir do
// payload de app/config/institution.ts — nenhum JS no cliente para desenhá-lo. O único JS do
// cliente é o de copiar.
//
// Um só DOM serve os dois layouts (CSS decide): no desktop o QR fica à esquerda, visível; no
// celular o botão de copiar vem primeiro e o QR fica num <details>. O SVG existe nas duas
// posições (é pequeno — corridas horizontais, ~3 KB) porque o SSR não sabe a largura da tela.
import { encode } from 'uqr'

import { pix, pixKeyFormatted } from '~/config/institution'

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

type Target = 'code' | 'key'

const copied = ref<Target | null>(null)
const manual = ref(false)
const codeField = ref<HTMLInputElement | null>(null)
const keyText = ref<HTMLElement | null>(null)
let timer: ReturnType<typeof setTimeout> | undefined

function selectManually(target: Target): void {
  manual.value = true
  nextTick(() => {
    if (target === 'code') {
      codeField.value?.focus()
      codeField.value?.select()
    } else if (keyText.value) {
      const range = document.createRange()
      range.selectNodeContents(keyText.value)
      const selection = window.getSelection()
      selection?.removeAllRanges()
      selection?.addRange(range)
    }
  })
}

async function copy(target: Target): Promise<void> {
  const value = target === 'code' ? pix.payload : pix.key

  try {
    await navigator.clipboard.writeText(value)
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

const status = computed(() =>
  copied.value === 'code' ? 'Código copiado' : copied.value === 'key' ? 'Chave copiada' : '',
)
</script>

<template>
  <section class="pix card" :class="{ 'pix--manual': manual }" aria-labelledby="pix-titulo" data-testid="doe-por-pix">
    <figure class="pix__qr pix__qr--wide">
      <!-- eslint-disable-next-line vue/no-v-html -- SVG gerado aqui mesmo, a partir de constante -->
      <div class="pix__qr-frame" v-html="qrSvg" />
      <figcaption>Aponte a câmera do app do banco</figcaption>
    </figure>

    <div class="pix__body">
      <h2 id="pix-titulo" class="pix__title">Doe por Pix</h2>
      <p class="pix__lead">Rápido, sem taxa e sem cadastro. A doação cai direto na conta do Lar.</p>

      <div class="pix__block">
        <input
          ref="codeField"
          class="pix__code"
          type="text"
          readonly
          :value="pix.payload"
          aria-label="Código Pix copia e cola"
          data-testid="pix-codigo"
        />
        <button type="button" class="btn btn--primary pix__button" data-testid="copiar-codigo" @click="copy('code')">
          <template v-if="copied === 'code'">Código copiado</template>
          <template v-else>
            <span class="pix__wide">Copiar código</span>
            <span class="pix__narrow">Copiar código Pix</span>
          </template>
        </button>
      </div>
      <p class="pix__hint">Depois, cole no app do banco em <strong>Pix copia e cola</strong>.</p>

      <div class="pix__block pix__block--key">
        <p class="pix__key">
          <span class="pix__key-label">Chave Pix (CNPJ)</span>
          <span ref="keyText" class="pix__key-value" data-testid="pix-chave">{{ keyFormatted }}</span>
        </p>
        <button type="button" class="btn btn--secondary pix__button" data-testid="copiar-chave" @click="copy('key')">
          <template v-if="copied === 'key'">Chave copiada</template>
          <template v-else>
            <span class="pix__wide">Copiar chave</span>
            <span class="pix__narrow">Copiar</span>
          </template>
        </button>
      </div>

      <details class="pix__qr pix__qr--narrow">
        <summary>Mostrar QR Code (para pagar de outro aparelho)</summary>
        <div class="pix__qr-frame" v-html="qrSvg" />
      </details>

      <ol class="pix__steps">
        <li>Abra a área Pix do app do seu banco.</li>
        <li>Escaneie o QR Code ou cole o código copiado.</li>
        <li>Digite o valor e confira o recebedor: {{ pix.receiverName }}.</li>
      </ol>

      <p class="visually-hidden" role="status" aria-live="polite">{{ status }}</p>
    </div>
  </section>
</template>

<style scoped>
.pix {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: var(--space-5);
  margin-block: var(--space-6) var(--space-7);
}

.pix__title {
  margin-top: 0;
}

.pix__lead {
  color: var(--color-text-muted);
}

.pix__block {
  display: flex;
  align-items: center;
  gap: var(--space-3);
}

.pix__block--key {
  margin-top: var(--space-5);
}

.pix__code {
  flex: 1;
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
  white-space: nowrap;
  overflow: hidden;
}

.pix__button {
  flex: none;
}

.pix__hint {
  margin: var(--space-2) 0 0;
  font-size: var(--text-sm);
  color: var(--color-text-muted);
}

.pix__key {
  flex: 1;
  min-width: 0;
  margin: 0;
  display: grid;
}

.pix__key-label {
  font-size: var(--text-xs);
  color: var(--color-text-muted);
}

.pix__key-value {
  font-size: var(--text-base);
  font-weight: var(--weight-medium);
  color: var(--color-heading);
  font-variant-numeric: tabular-nums;
}

.pix__steps {
  margin: var(--space-5) 0 0;
  padding-left: var(--space-5);
  font-size: var(--text-sm);
  color: var(--color-text-muted);
}

.pix__steps li + li {
  margin-top: var(--space-1);
}

/* Preto sobre branco sempre — o frame não herda tema. */
.pix__qr-frame {
  width: 11.25rem;
  background: #fff;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  overflow: hidden;
}

.pix__qr {
  margin: 0;
}

.pix__qr figcaption {
  margin-top: var(--space-2);
  max-width: 11.25rem;
  font-size: var(--text-xs);
  text-align: center;
  color: var(--color-text-muted);
}

.pix__qr--narrow {
  margin-top: var(--space-5);
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

/* Celular: botão em largura total vem primeiro; campo do código só aparece se a cópia falhar. */
.pix__qr--wide,
.pix__wide {
  display: none;
}

.pix__block:not(.pix__block--key) {
  flex-direction: column;
  align-items: stretch;
}

.pix__block:not(.pix__block--key) .pix__code {
  display: none;
  order: 2;
}

.pix--manual .pix__block:not(.pix__block--key) .pix__code {
  display: block;
}

.pix__block:not(.pix__block--key) .pix__button {
  width: 100%;
}

@media (min-width: 48rem) {
  .pix {
    grid-template-columns: auto minmax(0, 1fr);
    gap: var(--space-6);
    padding: var(--space-6);
    align-items: start;
  }

  .pix__qr--wide {
    display: block;
  }

  .pix__qr--narrow,
  .pix__narrow,
  .pix__hint {
    display: none;
  }

  .pix__wide {
    display: inline;
  }

  .pix__button {
    min-width: 10rem;
  }

  .pix__key-value {
    font-size: var(--text-lg);
  }

  .pix__block:not(.pix__block--key) {
    flex-direction: row;
    align-items: center;
  }

  .pix__block:not(.pix__block--key) .pix__code {
    display: block;
    order: 0;
  }

  .pix__block:not(.pix__block--key) .pix__button {
    width: auto;
  }
}
</style>
