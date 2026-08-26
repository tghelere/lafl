<script setup lang="ts">
withDefaults(
  defineProps<{
    message?: string
  }>(),
  { message: 'Carregando…' },
)
</script>

<template>
  <p
    class="state-message"
    role="status"
    aria-live="polite"
  >
    {{ message }}
  </p>
</template>
