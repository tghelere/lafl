<script setup lang="ts">
/**
 * A declaração "a imagem mostra alguém que hoje ainda é criança ou adolescente atendido?" —
 * duas opções, nenhuma marcada de início: é uma resposta, e a pessoa precisa dá-la de
 * propósito. O que cada resposta provoca (recusa no envio, imagem fora do site) é decisão da
 * API; aqui só se explica.
 *
 * O critério é a pessoa HOJE, não a data da foto (sessão 28): o acervo histórico, em que todos
 * os retratados já são adultos, responde "Não"; foto recente de atendidos responde "Sim"; e,
 * na dúvida sobre a idade de alguém hoje, "Sim". Ver docs/decisoes/0024-biblioteca-de-midia.md.
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
      A imagem mostra alguém que hoje ainda é criança ou adolescente e que é ou foi atendido pela
      instituição?
    </legend>
    <ul class="field__hint media-declaration__criteria">
      <li>Foto recente de crianças ou adolescentes atendidos: <strong>Sim</strong>.</li>
      <li>
        Foto de acervo antigo, em que todas as pessoas retratadas já são adultas hoje:
        <strong>Não</strong>.
      </li>
      <li>Em dúvida sobre a idade de alguém hoje: <strong>Sim</strong>.</li>
    </ul>
    <p class="field__hint">
      Foto que responde “Sim” exige consentimento de imagem do responsável, e o sistema ainda
      não registra esse consentimento. Por isso ela não é aceita aqui.
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
