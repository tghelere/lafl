<script setup lang="ts">
// Local do Google Maps embutido direto plantaria cookie de terceiro no primeiro
// carregamento, antes de qualquer clique — mesma lógica que levou a instituição a não usar
// Google Analytics (ver docs/decisoes/0006-umami-em-vez-de-google-analytics.md). Por isso o
// estado inicial é uma ilustração estática própria (sem imagem de satélite real: não há
// coordenada de latitude/longitude confirmada para os dois endereços, e inventar uma
// arriscaria apontar pino no lugar errado — mesma cautela de "não inventar" que rege telefone
// e outros dados institucionais neste projeto). O iframe do mapa interativo só é criado no
// DOM depois do clique em "Ver mapa interativo" — nenhuma requisição a domínio do Google
// antes disso (conferido na aba de rede).
import { MapPin } from '@lucide/vue'

const props = defineProps<{
  label: string
  address: string
}>()

const interactiveLoaded = ref(false)

const mapsAppHref = computed(
  () => `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(props.address)}`,
)

// Embed sem chave de API — o projeto não tem NUXT_PUBLIC_GOOGLE_MAPS_KEY nem justificativa
// para adicionar uma dependência paga só para isto (ver CLAUDE.md, "não instale dependência
// sem justificar"). Busca por endereço em texto, não por coordenada.
const embedSrc = computed(
  () => `https://www.google.com/maps?q=${encodeURIComponent(props.address)}&output=embed`,
)

function loadInteractive() {
  interactiveLoaded.value = true
}
</script>

<template>
  <div class="app-mapa">
    <div
      v-if="!interactiveLoaded"
      class="app-mapa__static"
      role="img"
      :aria-label="`Ilustração de localização — ${label}, endereço completo abaixo`"
    >
      <MapPin :size="32" aria-hidden="true" class="app-mapa__pin" />
      <span class="app-mapa__static-label">{{ label }}</span>
    </div>

    <iframe
      v-else
      class="app-mapa__frame"
      :src="embedSrc"
      :title="`Mapa interativo — ${label}`"
      loading="lazy"
      referrerpolicy="no-referrer-when-downgrade"
    />

    <div class="app-mapa__actions">
      <button
        v-if="!interactiveLoaded"
        type="button"
        class="btn btn--secondary"
        @click="loadInteractive"
      >
        Ver mapa interativo
      </button>
      <a class="btn btn--secondary" :href="mapsAppHref" target="_blank" rel="noopener noreferrer">
        Abrir no aplicativo de mapas
      </a>
    </div>
  </div>
</template>

<style scoped>
.app-mapa {
  margin-top: var(--space-4);
}

.app-mapa__static,
.app-mapa__frame {
  aspect-ratio: 16 / 9;
  width: 100%;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
}

.app-mapa__static {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  background:
    repeating-linear-gradient(
      0deg,
      transparent,
      transparent 23px,
      color-mix(in srgb, var(--color-border) 60%, transparent) 24px
    ),
    repeating-linear-gradient(
      90deg,
      transparent,
      transparent 23px,
      color-mix(in srgb, var(--color-border) 60%, transparent) 24px
    ),
    var(--color-surface-raised);
  color: var(--color-text-muted);
}

.app-mapa__pin {
  color: var(--color-accent);
}

.app-mapa__static-label {
  font-size: var(--text-xs);
  font-weight: var(--weight-medium);
  text-align: center;
  padding-inline: var(--space-4);
}

.app-mapa__actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-3);
  margin-top: var(--space-3);
}
</style>
