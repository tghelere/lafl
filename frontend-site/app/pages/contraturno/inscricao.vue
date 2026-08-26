<script setup lang="ts">
// Inscrição na Escola de Contraturno — titular é o responsável, nunca o adolescente (ver ADR
// 0007). Idade em número inteiro, nunca data de nascimento.
const { hasError, fieldFailed } = useFormErrorState()

useSeoMeta({
  title: 'Inscrição — Contraturno — Lar Anália Franco',
  description: 'Inscreva um adolescente na Escola de Contraturno do Lar Anália Franco.',
})
</script>

<template>
  <div>
    <nav class="breadcrumb" aria-label="Trilha de navegação">
      <ol>
        <li><NuxtLink to="/">Início</NuxtLink></li>
        <li><NuxtLink to="/contraturno">Contraturno</NuxtLink></li>
        <li><span aria-current="page">Inscrição</span></li>
      </ol>
    </nav>

    <h1>Inscrição</h1>
    <p class="prose">
      Preencha seus dados que a coordenação do contraturno entra em contato. Os dados do
      adolescente são confirmados presencialmente.
    </p>

    <div v-if="hasError" class="form-alert" role="alert">
      <p>Não foi possível enviar sua inscrição. Confira os campos abaixo e tente novamente.</p>
    </div>

    <form method="post" action="/api/forms/inscricao" class="form">
      <div class="form__field" :class="{ 'form__field--invalid': fieldFailed('guardian_name') }">
        <label for="guardian_name">Seu nome</label>
        <input id="guardian_name" name="guardian_name" type="text" autocomplete="name" required />
        <p v-if="fieldFailed('guardian_name')" class="form__error">Informe seu nome.</p>
      </div>

      <div class="form__field" :class="{ 'form__field--invalid': fieldFailed('phone') }">
        <label for="phone">Telefone</label>
        <input id="phone" name="phone" type="tel" autocomplete="tel" required />
        <p v-if="fieldFailed('phone')" class="form__error">Informe um telefone válido.</p>
      </div>

      <div class="form__field" :class="{ 'form__field--invalid': fieldFailed('email') }">
        <label for="email">E-mail</label>
        <input id="email" name="email" type="email" autocomplete="email" required />
        <p v-if="fieldFailed('email')" class="form__error">Informe um e-mail válido.</p>
      </div>

      <div class="form__field" :class="{ 'form__field--invalid': fieldFailed('teen_age') }">
        <label for="teen_age">Idade do adolescente</label>
        <input id="teen_age" name="teen_age" type="number" min="10" max="19" required />
        <p v-if="fieldFailed('teen_age')" class="form__error">Informe a idade em anos completos.</p>
      </div>

      <div class="form__field">
        <label for="school">Escola (opcional)</label>
        <input id="school" name="school" type="text" />
      </div>

      <div class="form__field">
        <label for="message">Mensagem (opcional)</label>
        <textarea id="message" name="message" maxlength="2000" />
        <p class="form__hint">Não escreva o nome do adolescente aqui.</p>
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

      <button type="submit" class="btn btn--primary">Enviar inscrição</button>
    </form>
  </div>
</template>
