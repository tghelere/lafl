<script setup lang="ts">
/**
 * Cabeçalho de tela: o `<h1>`, o selo de estado ao lado dele e as ações da tela à direita.
 *
 * Existe para que essas três coisas fiquem alinhadas do mesmo jeito em todas as telas. Antes
 * cada uma resolvia por conta própria: a edição de página tinha um `.page-form__header` com
 * flex, a de documento soltava o selo num `<span>` avulso abaixo do título (que caía para a
 * linha de baixo), e a listagem pendurava o botão "+ Novo" dentro da barra de filtro com
 * `margin-left: auto`.
 *
 * O selo fica centrado verticalmente no título porque a linha é um flex com
 * `align-items: center` — não por margem calibrada à mão, que é o que se desfaz assim que o
 * tamanho do título muda.
 */
defineProps<{
  title: string
}>()

defineSlots<{
  /** Selo de estado da tela: Publicada/Rascunho, Ativo/Inativo, status de atendimento. */
  badge?: () => unknown
  /** Ações da tela (novo registro, abrir no site). Vão para a direita da mesma linha. */
  actions?: () => unknown
}>()
</script>

<template>
  <header class="page-header">
    <div class="page-header__main">
      <h1 class="page-header__title">
        {{ title }}
      </h1>
      <slot name="badge" />
    </div>

    <div
      v-if="$slots.actions"
      class="page-header__actions"
    >
      <slot name="actions" />
    </div>
  </header>
</template>
