<script setup lang="ts">
withDefaults(
  defineProps<{
    message?: string
  }>(),
  { message: 'Nenhum resultado encontrado.' },
)
</script>

<template>
  <p
    class="state-message"
    role="status"
  >
    {{ message }}
  </p>
</template>
