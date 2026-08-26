<script setup lang="ts">
// Formulário de contato geral — titular é o próprio visitante (adulto).
const { hasError, fieldFailed } = useFormErrorState()

useSeoMeta({
  title: 'Contato — Lar Anália Franco',
  description: 'Fale com o Lar Anália Franco.',
})
</script>

<template>
  <div>
    <nav class="breadcrumb" aria-label="Trilha de navegação">
      <ol>
        <li><NuxtLink to="/">Início</NuxtLink></li>
        <li><span aria-current="page">Contato</span></li>
      </ol>
    </nav>

    <h1>Contato</h1>
    <p class="prose">
      Dúvidas, sugestões ou pedidos de imprensa — escreva para a gente.
    </p>

    <div v-if="hasError" class="form-alert" role="alert">
      <p>Não foi possível enviar sua mensagem. Confira os campos abaixo e tente novamente.</p>
    </div>

    <form method="post" action="/api/forms/contato" class="form">
      <div class="form__field" :class="{ 'form__field--invalid': fieldFailed('name') }">
        <label for="name">Seu nome</label>
        <input id="name" name="name" type="text" autocomplete="name" required />
        <p v-if="fieldFailed('name')" class="form__error">Informe seu nome.</p>
      </div>

      <div class="form__field" :class="{ 'form__field--invalid': fieldFailed('email') }">
        <label for="email">E-mail</label>
        <input id="email" name="email" type="email" autocomplete="email" required />
        <p v-if="fieldFailed('email')" class="form__error">Informe um e-mail válido.</p>
      </div>

      <div class="form__field" :class="{ 'form__field--invalid': fieldFailed('subject') }">
        <label for="subject">Assunto</label>
        <input id="subject" name="subject" type="text" required />
        <p v-if="fieldFailed('subject')" class="form__error">Informe o assunto da mensagem.</p>
      </div>

      <div class="form__field" :class="{ 'form__field--invalid': fieldFailed('message') }">
        <label for="message">Mensagem</label>
        <textarea id="message" name="message" maxlength="2000" required />
        <p v-if="fieldFailed('message')" class="form__error">Escreva sua mensagem.</p>
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

      <button type="submit" class="btn btn--primary">Enviar mensagem</button>
    </form>
  </div>
</template>
