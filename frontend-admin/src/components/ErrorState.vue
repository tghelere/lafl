<script setup lang="ts">
withDefaults(
  defineProps<{
    message?: string
  }>(),
  { message: 'Não foi possível carregar os dados. Tente novamente.' },
)
</script>

<template>
  <p
    class="state-message state-message--error"
    role="alert"
  >
    {{ message }}
  </p>
</template>
