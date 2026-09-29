<script setup lang="ts">
/**
 * A declaração "a imagem mostra criança ou adolescente atendido?" — duas opções, nenhuma
 * marcada de início: é uma resposta, e a pessoa precisa dá-la de propósito. O que cada resposta
 * provoca (recusa no envio, imagem fora do site) é decisão da API; aqui só se explica.
 *
 * `locked`: a imagem já foi marcada, e a marcação não se desfaz (App\Actions\Media\
 * UpdateMediaDetails). A opção "Não" fica desabilitada para a tela não oferecer o que a API
 * recusaria.
 */
const model = defineModel<boolean | null>({ required: true })

defineProps<{
  error?: string
  locked?: boolean
}>()
</script>

<template>
  <fieldset class="field media-declaration">
    <legend class="field__legend">
      A imagem mostra criança ou adolescente atendido pela instituição?
    </legend>
    <p class="field__hint">
      Foto de assistido só pode ir para o site com consentimento de imagem do responsável, e o
      sistema ainda não registra esse consentimento. Por isso ela não é aceita aqui.
    </p>

    <label class="media-declaration__option">
      <input
        v-model="model"
        type="radio"
        name="depicts-assisted-minor"
        :value="false"
        :disabled="locked"
      >
      Não
    </label>
    <label class="media-declaration__option">
      <input
        v-model="model"
        type="radio"
        name="depicts-assisted-minor"
        :value="true"
      >
      Sim, mostra
    </label>

    <span
      v-if="error"
      class="field__error"
    >{{ error }}</span>
  </fieldset>
</template>
