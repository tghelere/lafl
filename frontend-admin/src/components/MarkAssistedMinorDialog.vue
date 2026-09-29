<script setup lang="ts">
import { ShieldAlert, X } from 'lucide-vue-next'
import { computed, ref } from 'vue'

import AppIcon from '@/components/AppIcon.vue'
import type { MediaUsage } from '@/types/media'

/**
 * Confirmação forte antes de marcar uma imagem como foto de criança ou adolescente atendido.
 * A marcação tira a imagem do site em todas as páginas e não se desfaz (App\Actions\Media\
 * UpdateMediaDetails), então um clique distraído num `confirm()` não basta: a pessoa lê o que
 * vai acontecer, vê onde a imagem está e digita a frase.
 *
 * A frase é só para a tela. Quem barra é a API, que exige `confirm_marking` para marcar
 * (UpdateMediaRequest); este diálogo é o que faz a tela mandá-lo de propósito.
 */
const PHRASE = 'tirar do site'

defineProps<{
  usages: MediaUsage[]
}>()

const emit = defineEmits<{
  confirm: []
}>()

const dialog = ref<HTMLDialogElement | null>(null)
const typed = ref('')

const matches = computed(() => typed.value.trim().toLocaleLowerCase('pt-BR') === PHRASE)

function open(): void {
  typed.value = ''
  dialog.value?.showModal()
}

function confirm(): void {
  if (!matches.value) {
    return
  }

  dialog.value?.close()
  emit('confirm')
}

defineExpose({ open })
</script>

<template>
  <dialog
    ref="dialog"
    class="media-picker mark-dialog"
    aria-labelledby="mark-dialog-title"
  >
    <div class="media-picker__header">
      <h2
        id="mark-dialog-title"
        class="media-picker__title"
      >
        <AppIcon :icon="ShieldAlert" />
        Marcar como foto de criança ou adolescente atendido
      </h2>
      <button
        type="button"
        class="btn btn--secondary"
        @click="dialog?.close()"
      >
        <AppIcon :icon="X" />
        Fechar
      </button>
    </div>

    <p>Ao marcar:</p>
    <ul class="mark-dialog__consequences">
      <li>a imagem sai do site <strong>agora</strong>, em todas as páginas, com a legenda;</li>
      <li>salvar uma página que ainda a use passa a ser recusado;</li>
      <li><strong>a marcação não pode ser desfeita</strong>, nem pelo painel nem por pacote de conteúdo.</li>
    </ul>

    <template v-if="usages.length > 0">
      <p>Ela está hoje nestas páginas:</p>
      <ul class="mark-dialog__pages">
        <li
          v-for="usage in usages"
          :key="usage.uuid"
        >
          {{ usage.title }} <span class="field__hint">/{{ usage.slug }}</span>
        </li>
      </ul>
    </template>
    <p
      v-else
      class="field__hint"
    >
      Nenhuma página usa esta imagem hoje.
    </p>

    <form @submit.prevent="confirm">
      <div class="field">
        <label for="mark-dialog-phrase">Para confirmar, digite <strong>{{ PHRASE }}</strong></label>
        <input
          id="mark-dialog-phrase"
          v-model="typed"
          type="text"
          autocomplete="off"
          spellcheck="false"
        >
      </div>
      <div class="mark-dialog__actions">
        <button
          type="submit"
          class="btn btn--danger"
          :disabled="!matches"
        >
          Marcar e tirar do site
        </button>
        <button
          type="button"
          class="btn btn--secondary"
          @click="dialog?.close()"
        >
          Cancelar
        </button>
      </div>
    </form>
  </dialog>
</template>
