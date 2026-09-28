<script setup lang="ts">
import type { Component } from 'vue'

/**
 * Todo ícone do painel passa por aqui — nenhum componente do lucide é usado direto na tela.
 *
 * O que o invólucro garante, e que se perderia se cada tela repetisse os mesmos atributos:
 * tamanho único (18px, dentro da faixa de 18 a 20 pedida), traço um pouco mais fino que o
 * padrão do lucide (2), para não competir em peso com o texto de 13–14px do painel, e
 * `aria-hidden` SEMPRE.
 *
 * `aria-hidden` sem exceção porque no painel o ícone nunca é a informação: ele acompanha um
 * rótulo escrito — "Filtrar", "Sair", "Publicada". Um ícone anunciado junto do rótulo faria o
 * leitor de tela dizer a mesma coisa duas vezes. Onde o ícone fosse a única informação, o
 * certo seria um texto em .visually-hidden ao lado, não trocar este atributo.
 *
 * `focusable="false"` cobre o Internet Explorer/Edge legado, em que um <svg> entra na ordem de
 * tabulação sozinho; custa nada e evita um foco invisível no meio de um botão.
 */
defineProps<{
  icon: Component
}>()
</script>

<template>
  <component
    :is="icon"
    class="icon"
    :size="18"
    :stroke-width="1.75"
    aria-hidden="true"
    focusable="false"
  />
</template>
