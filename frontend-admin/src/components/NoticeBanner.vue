<script setup lang="ts">
withDefaults(
  defineProps<{
    variant?: 'default' | 'info' | 'error'
  }>(),
  { variant: 'default' },
)
</script>

<template>
  <div
    class="notice"
    :class="variant !== 'default' ? `notice--${variant}` : ''"
    role="note"
  >
    <slot />
  </div>
</template>
