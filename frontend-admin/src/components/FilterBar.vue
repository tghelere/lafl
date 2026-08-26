<script setup lang="ts">
import type { StatusOption } from '@/types/forms'

defineProps<{
  statusOptions: StatusOption[]
}>()

const status = defineModel<string>('status', { default: '' })
const from = defineModel<string>('from', { default: '' })
const to = defineModel<string>('to', { default: '' })

const emit = defineEmits<{
  apply: []
  clear: []
}>()
</script>

<template>
  <form
    class="filter-bar"
    @submit.prevent="emit('apply')"
  >
    <div class="filter-bar__field">
      <label for="filter-status">Status</label>
      <select
        id="filter-status"
        v-model="status"
      >
        <option value="">
          Todos
        </option>
        <option
          v-for="option in statusOptions"
          :key="option.value"
          :value="option.value"
        >
          {{ option.label }}
        </option>
      </select>
    </div>

    <div class="filter-bar__field">
      <label for="filter-from">De</label>
      <input
        id="filter-from"
        v-model="from"
        type="date"
      >
    </div>

    <div class="filter-bar__field">
      <label for="filter-to">Até</label>
      <input
        id="filter-to"
        v-model="to"
        type="date"
      >
    </div>

    <button
      type="submit"
      class="btn btn--primary"
    >
      Filtrar
    </button>
    <button
      type="button"
      class="btn btn--secondary"
      @click="emit('clear')"
    >
      Limpar
    </button>
  </form>
</template>
