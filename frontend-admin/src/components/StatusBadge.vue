<script setup lang="ts">
import { computed } from 'vue'

import AppIcon from '@/components/AppIcon.vue'
import { STATUS_ICONS } from '@/config/icons'

/**
 * Selo de estado. Serve às três famílias do painel — status de atendimento, leitura e
 * publicação/ativação —, porque visualmente são a mesma coisa e o que muda é só o tom e o
 * ícone.
 *
 * O ícone é reforço, nunca a informação: o rótulo escrito continua ao lado, e é por ele que a
 * pessoa lê o estado. Status que não tiver ícone no mapa simplesmente não mostra nenhum, em
 * vez de quebrar a tela.
 */
const props = defineProps<{
  status: string
  label: string
}>()

const icon = computed(() => STATUS_ICONS[props.status])
</script>

<template>
  <span
    class="badge"
    :class="`badge--${status}`"
  >
    <AppIcon
      v-if="icon"
      :icon="icon"
    />
    {{ label }}
  </span>
</template>
