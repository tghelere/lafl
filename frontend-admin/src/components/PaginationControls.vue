<script setup lang="ts">
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'

import AppIcon from '@/components/AppIcon.vue'

defineProps<{
  currentPage: number
  lastPage: number
}>()

const emit = defineEmits<{
  change: [page: number]
}>()
</script>

<template>
  <nav
    v-if="lastPage > 1"
    class="pagination"
    aria-label="Paginação"
  >
    <button
      type="button"
      :disabled="currentPage <= 1"
      @click="emit('change', currentPage - 1)"
    >
      <AppIcon :icon="ChevronLeft" />
      Anterior
    </button>
    <span>Página {{ currentPage }} de {{ lastPage }}</span>
    <button
      type="button"
      :disabled="currentPage >= lastPage"
      @click="emit('change', currentPage + 1)"
    >
      Próxima
      <AppIcon :icon="ChevronRight" />
    </button>
  </nav>
</template>
