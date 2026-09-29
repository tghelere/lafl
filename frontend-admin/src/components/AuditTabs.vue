<script setup lang="ts">
/**
 * As duas abas da tela de Auditoria: formulários recebidos e imagens. Cada aba é uma rota, e não
 * um estado da tela, para o link ser compartilhável e o voltar do navegador funcionar. É
 * navegação, então são links (`aria-current` vem do RouterLink), e não o padrão `tablist` do
 * ARIA, que é para painéis na mesma página.
 */
</script>

<template>
  <nav
    class="audit-tabs"
    aria-label="O que auditar"
  >
    <RouterLink
      :to="{ name: 'audit.index' }"
      class="audit-tabs__link"
      exact-active-class="audit-tabs__link--active"
    >
      Formulários
    </RouterLink>
    <RouterLink
      :to="{ name: 'audit.media' }"
      class="audit-tabs__link"
      exact-active-class="audit-tabs__link--active"
    >
      Imagens
    </RouterLink>
  </nav>
</template>
