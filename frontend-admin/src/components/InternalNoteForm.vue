<script setup lang="ts">
import { Save } from 'lucide-vue-next'
import { ref, watch } from 'vue'

import AppIcon from '@/components/AppIcon.vue'
import { STATUS_OPTIONS } from '@/types/forms'

const props = withDefaults(
  defineProps<{
    status: string
    internalNote: string | null
    submitting?: boolean
  }>(),
  { submitting: false },
)

const emit = defineEmits<{
  submit: [payload: { status: string; internalNote: string | null }]
}>()

const localStatus = ref(props.status)
const localNote = ref(props.internalNote ?? '')

// A tela de detalhe recarrega o registro depois de salvar (ver views que usam este
// componente) — sincroniza o formulário com o valor atualizado vindo da API, em vez de
// deixar o campo com o rascunho anterior.
watch(
  () => props.status,
  (value) => {
    localStatus.value = value
  },
)
watch(
  () => props.internalNote,
  (value) => {
    localNote.value = value ?? ''
  },
)

function handleSubmit(): void {
  emit('submit', {
    status: localStatus.value,
    internalNote: localNote.value.trim() === '' ? null : localNote.value,
  })
}
</script>

<template>
  <form
    class="card"
    @submit.prevent="handleSubmit"
  >
    <h2>Atualizar atendimento</h2>

    <div class="field">
      <label for="note-status">Status</label>
      <select
        id="note-status"
        v-model="localStatus"
      >
        <option
          v-for="option in STATUS_OPTIONS"
          :key="option.value"
          :value="option.value"
        >
          {{ option.label }}
        </option>
      </select>
    </div>

    <div class="field">
      <label for="note-text">Anotação interna</label>
      <textarea
        id="note-text"
        v-model="localNote"
        maxlength="2000"
        placeholder="Visível só para a equipe — nunca aparece para quem enviou o formulário."
      />
    </div>

    <button
      type="submit"
      class="btn btn--primary"
      :disabled="submitting"
    >
      <AppIcon :icon="Save" />
      {{ submitting ? 'Salvando…' : 'Salvar' }}
    </button>
  </form>
</template>
