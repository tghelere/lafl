<script setup lang="ts">
// Agendamento de coleta do Bazar Beneficente — titular é o doador (adulto). Endereço é o
// dado mais sensível desta fase (ver docs/dominio.md); só existe nesta tela, nunca é exibido
// de volta.
//
// WhatsApp é o canal principal (ver docs/contexto.md) — hoje o agendamento já é feito por
// telefone e WhatsApp. O formulário abaixo é o caminho secundário, para fora do horário de
// atendimento; ele já notifica o setor do bazar automaticamente (ver
// config/forms.php:notification_recipients.pickup_request no backend).
const { hasError, fieldFailed } = useFormErrorState()

const WHATSAPP_NUMBER = '5543999500183'
const WHATSAPP_MESSAGE = 'Olá! Gostaria de agendar uma coleta de doação para o Bazar Beneficente.'
const whatsappHref = `https://wa.me/${WHATSAPP_NUMBER}?text=${encodeURIComponent(WHATSAPP_MESSAGE)}`

useSeoMeta({
  title: 'Agendar Coleta — Bazar — Lar Anália Franco',
  description: 'Agende a coleta de itens para doação ao Bazar Beneficente do Lar Anália Franco pelo WhatsApp (43) 99950-0183.',
})
</script>

<template>
  <div>
    <nav class="breadcrumb" aria-label="Trilha de navegação">
      <ol>
        <li><NuxtLink to="/">Início</NuxtLink></li>
        <li><NuxtLink to="/bazar">Bazar</NuxtLink></li>
        <li><span aria-current="page">Agendar Coleta</span></li>
      </ol>
    </nav>

    <h1>Agendar Coleta</h1>
    <p class="prose">
      O jeito mais rápido de agendar é pelo WhatsApp. Veja em
      <NuxtLink to="/bazar/o-que-aceitamos">o que aceitamos</NuxtLink> antes de chamar.
    </p>

    <p>
      <a
        class="btn btn--primary"
        :href="whatsappHref"
        target="_blank"
        rel="noopener noreferrer"
      >
        Chamar no WhatsApp (43) 99950-0183
      </a>
    </p>

    <h2>Fora do horário de atendimento</h2>
    <p class="prose">
      Prefere deixar por escrito, ou é fora do horário de atendimento? Preencha o formulário
      abaixo — a equipe do bazar recebe automaticamente e entra em contato para combinar a
      coleta.
    </p>

    <div v-if="hasError" class="form-alert" role="alert">
      <p>Não foi possível enviar o agendamento. Confira os campos abaixo e tente novamente.</p>
    </div>

    <form method="post" action="/api/forms/coleta" class="form">
      <div class="form__field" :class="{ 'form__field--invalid': fieldFailed('donor_name') }">
        <label for="donor_name">Seu nome</label>
        <input id="donor_name" name="donor_name" type="text" autocomplete="name" required />
        <p v-if="fieldFailed('donor_name')" class="form__error">Informe seu nome.</p>
      </div>

      <div class="form__field" :class="{ 'form__field--invalid': fieldFailed('phone') }">
        <label for="phone">Telefone</label>
        <input id="phone" name="phone" type="tel" autocomplete="tel" required />
        <p v-if="fieldFailed('phone')" class="form__error">Informe um telefone válido.</p>
      </div>

      <div class="form__field" :class="{ 'form__field--invalid': fieldFailed('address') }">
        <label for="address">Endereço para a coleta</label>
        <textarea id="address" name="address" autocomplete="street-address" required />
        <p v-if="fieldFailed('address')" class="form__error">Informe o endereço para a coleta.</p>
      </div>

      <div class="form__field" :class="{ 'form__field--invalid': fieldFailed('items_description') }">
        <label for="items_description">O que você quer doar</label>
        <textarea id="items_description" name="items_description" maxlength="2000" required />
        <p v-if="fieldFailed('items_description')" class="form__error">Descreva os itens a doar.</p>
      </div>

      <div class="form__field" :class="{ 'form__field--invalid': fieldFailed('availability_window') }">
        <label for="availability_window">Quando você costuma estar disponível</label>
        <input
          id="availability_window"
          name="availability_window"
          type="text"
          placeholder="ex.: sábados de manhã"
          required
        />
        <p v-if="fieldFailed('availability_window')" class="form__error">
          Informe quando você costuma estar disponível.
        </p>
      </div>

      <div class="form__honeypot" aria-hidden="false">
        <label for="website">Deixe este campo em branco</label>
        <input id="website" name="website" type="text" tabindex="-1" autocomplete="off" />
      </div>

      <label class="form__consent">
        <input type="checkbox" name="consent" value="1" required />
        <span>
          Li e concordo com a
          <NuxtLink to="/politica-de-privacidade">política de privacidade</NuxtLink>.
        </span>
      </label>

      <button type="submit" class="btn btn--secondary">Enviar pedido de coleta</button>
    </form>
  </div>
</template>
