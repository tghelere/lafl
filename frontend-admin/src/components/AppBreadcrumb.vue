<script setup lang="ts">
import { computed } from 'vue'

import type { BreadcrumbItem } from '@/types/breadcrumb'

/**
 * Trilha de navegação do painel. Uma só, para todas as telas — antes cada tela montava a sua
 * `<nav class="breadcrumb">` à mão, e foi assim que a de edição de página acabou com uma
 * barra literal na marcação ALÉM da que a folha de estilo já desenha no `::after`, exibindo
 * "PÁGINAS / / EDITAR PÁGINA".
 *
 * O separador continua sendo só do CSS (ver `.breadcrumb li:not(:last-child)::after` em
 * components.css): barra nenhuma entra na marcação, então ela também não é lida em voz alta
 * nem copiada junto do texto.
 */
const props = defineProps<{
  items: BreadcrumbItem[]
}>()

/** Itens sem rótulo e sem esqueleto são descartados: degrau vazio não vira degrau. */
const steps = computed(() => props.items.filter((item) => item.loading || item.label !== ''))

function isLast(index: number): boolean {
  return index === steps.value.length - 1
}
</script>

<template>
  <nav
    class="breadcrumb"
    aria-label="Trilha de navegação"
  >
    <ol>
      <li
        v-for="(item, index) in steps"
        :key="index"
      >
        <!-- Enquanto o nome do registro não chegou: bloco cinza no lugar do rótulo, e o texto
             que o leitor de tela anuncia — o esqueleto sozinho seria um degrau mudo. -->
        <template v-if="item.loading">
          <span
            class="breadcrumb__skeleton"
            aria-hidden="true"
          />
          <span class="visually-hidden">Carregando…</span>
        </template>

        <RouterLink
          v-else-if="item.to && !isLast(index)"
          :to="item.to"
        >
          {{ item.label }}
        </RouterLink>

        <span
          v-else
          :aria-current="isLast(index) ? 'page' : undefined"
        >{{ item.label }}</span>
      </li>
    </ol>
  </nav>
</template>
