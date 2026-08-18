<script setup lang="ts"></script>

<template>
  <div class="layout">
    <header class="layout__header">
      <strong>Lar Anália Franco</strong>
    </header>

    <main class="layout__main">
      <slot />
    </main>

    <footer class="layout__footer">
      <p>Lar Anália Franco — conteúdo institucional em construção.</p>
    </footer>
  </div>
</template>

<style scoped>
.layout {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

.layout__header,
.layout__footer {
  padding: 1rem 1.5rem;
}

.layout__main {
  flex: 1;
  padding: 1.5rem;
}
</style>
