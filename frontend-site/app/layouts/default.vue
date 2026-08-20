<script setup lang="ts"></script>

<template>
  <div class="layout">
    <a class="skip-link" href="#main">Pular para o conteúdo</a>

    <AppHeader />

    <main id="main" class="layout__main">
      <div class="container">
        <slot />
      </div>
    </main>

    <AppFooter />
  </div>
</template>

<style scoped>
.layout {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

.layout__main {
  flex: 1;
  padding-block: var(--space-7);
}

/* Só aparece com foco de teclado — primeiro elemento tabulável da página, antes do menu. */
.skip-link {
  position: fixed;
  top: -3rem;
  left: var(--space-4);
  z-index: 50;
  background: var(--color-ink);
  color: var(--color-paper);
  padding: var(--space-3) var(--space-5);
  border-radius: var(--radius-md);
  text-decoration: none;
}

.skip-link:focus-visible {
  top: var(--space-4);
}
</style>
