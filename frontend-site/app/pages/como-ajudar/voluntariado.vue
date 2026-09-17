<script setup lang="ts">
// Candidatura de voluntariado — titular é o próprio voluntário (adulto).
const { hasError, fieldFailed } = useFormErrorState()

useSeoMeta({
  title: 'Voluntariado — Como Ajudar — Lar Anália Franco',
  description: 'Seja voluntário no Lar Anália Franco — bazar, contraturno, eventos ou apoio administrativo.',
  robots: 'noindex, nofollow',
})
</script>

<template>
  <div>
    <nav class="breadcrumb" aria-label="Trilha de navegação">
      <ol>
        <li><NuxtLink to="/">Início</NuxtLink></li>
        <li><NuxtLink to="/como-ajudar">Como Ajudar</NuxtLink></li>
        <li><span aria-current="page">Voluntariado</span></li>
      </ol>
    </nav>

    <h1>Voluntariado</h1>
    <p class="prose">
      Conte um pouco sobre sua disponibilidade e área de interesse — a equipe entra em contato.
    </p>

    <div v-if="hasError" class="form-alert" role="alert">
      <p>Não foi possível enviar sua candidatura. Confira os campos abaixo e tente novamente.</p>
    </div>

    <form method="post" action="/api/forms/voluntariado" class="form">
      <div class="form__field" :class="{ 'form__field--invalid': fieldFailed('name') }">
        <label for="name">Seu nome</label>
        <input id="name" name="name" type="text" autocomplete="name" required />
        <p v-if="fieldFailed('name')" class="form__error">Informe seu nome.</p>
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

      <div class="form__field" :class="{ 'form__field--invalid': fieldFailed('availability') }">
        <label for="availability">Disponibilidade</label>
        <input
          id="availability"
          name="availability"
          type="text"
          placeholder="ex.: fins de semana"
          required
        />
        <p v-if="fieldFailed('availability')" class="form__error">Informe sua disponibilidade.</p>
      </div>

      <div class="form__field" :class="{ 'form__field--invalid': fieldFailed('interest_area') }">
        <label for="interest_area">Área de interesse</label>
        <select id="interest_area" name="interest_area" required>
          <option value="" disabled selected>Selecione</option>
          <option value="bazar">Bazar</option>
          <option value="contraturno">Contraturno</option>
          <option value="eventos">Eventos</option>
          <option value="administrativo">Administrativo</option>
        </select>
        <p v-if="fieldFailed('interest_area')" class="form__error">Informe a área de interesse.</p>
      </div>

      <div class="form__field">
        <label for="message">Mensagem (opcional)</label>
        <textarea id="message" name="message" maxlength="2000" />
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

      <button type="submit" class="btn btn--primary">Enviar candidatura</button>
    </form>
  </div>
</template>
