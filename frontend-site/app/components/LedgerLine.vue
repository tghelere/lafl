<script setup lang="ts">
// Linha de registro — a assinatura do sistema (ver docs/decisoes/0009-direcao-visual.md).
// Todo número institucional aparece como item de linha, sempre com data, nunca com fonte
// externa: a instituição é a fonte dos próprios números.
//
// `example` marca a linha como dado de exemplo, não publicável — usar sempre que o valor
// vier de docs/contexto.md marcado [CONFIRMAR]/[VALIDAR] e ainda não tiver sido validado
// pela instituição (ver nota em docs/roadmap.md).
withDefaults(
  defineProps<{
    /** Numeral (variante "stat") ou ano (variante "entry"). */
    value: string
    label: string
    /** Data no formato curto, ex.: "ago/2026" ou "2022–26". Nunca uma fonte externa. */
    date?: string
    variant?: 'stat' | 'entry'
    example?: boolean
  }>(),
  { date: undefined, variant: 'stat', example: false },
)
</script>

<template>
  <div class="ledger__item" :class="{ 'ledger__item--entry': variant === 'entry' }">
    <div class="ledger__figure">
      <span v-if="example" class="ledger__badge">Exemplo, a validar</span>
      <span class="ledger__value">{{ value }}</span>
      <span class="ledger__label">{{ label }}</span>
    </div>
    <span v-if="date" class="ledger__date">{{ date }}</span>
  </div>
</template>
