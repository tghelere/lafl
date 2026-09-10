<script setup lang="ts">
// Manifestação de interesse na matrícula — titular é o responsável, nunca a criança (ver ADR
// 0007 e docs/estrutura-site.md §2.1). Faixa etária, nunca data de nascimento. `<form
// method="post">` de verdade: funciona sem JavaScript, submete para o proxy em
// server/api/forms/matricula.post.ts, que repassa ao backend e redireciona.
const { hasError, fieldFailed } = useFormErrorState()

useSeoMeta({
  title: 'Matrícula — Educação Infantil — Lar Anália Franco',
  description: 'Manifeste interesse na matrícula do CEI Anália Franco. Os dados completos da criança são coletados presencialmente.',
})
</script>

<template>
  <div>
    <nav class="breadcrumb" aria-label="Trilha de navegação">
      <ol>
        <li><NuxtLink to="/">Início</NuxtLink></li>
        <li><NuxtLink to="/educacao-infantil">Educação Infantil</NuxtLink></li>
        <li><span aria-current="page">Matrícula</span></li>
      </ol>
    </nav>

    <h1>Matrícula</h1>
    <p class="prose">
      Preencha seus dados que a secretaria do CEI Anália Franco entra em contato. Os dados da
      criança — nome, data de nascimento, documentos — são coletados presencialmente, junto
      com o termo de consentimento, no momento da matrícula efetiva.
    </p>

    <div v-if="hasError" class="form-alert" role="alert">
      <p>Não foi possível enviar sua manifestação de interesse. Confira os campos abaixo e tente novamente.</p>
    </div>

    <form method="post" action="/api/forms/matricula" class="form">
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

      <div class="form__field" :class="{ 'form__field--invalid': fieldFailed('child_age_range') }">
        <label for="child_age_range">Faixa etária da criança</label>
        <select id="child_age_range" name="child_age_range" required>
          <option value="" disabled selected>Selecione</option>
          <option value="1_ano">1 ano</option>
          <option value="2_anos">2 anos</option>
          <option value="3_anos">3 anos</option>
          <option value="4_anos">4 anos</option>
          <option value="5_anos">5 anos</option>
        </select>
        <p v-if="fieldFailed('child_age_range')" class="form__error">Informe a faixa etária da criança.</p>
      </div>

      <div class="form__field" :class="{ 'form__field--invalid': fieldFailed('desired_period') }">
        <label for="desired_period">Período pretendido</label>
        <select id="desired_period" name="desired_period" required>
          <option value="" disabled selected>Selecione</option>
          <option value="manha">Manhã</option>
          <option value="tarde">Tarde</option>
          <option value="integral">Integral</option>
        </select>
        <p v-if="fieldFailed('desired_period')" class="form__error">Informe o período pretendido.</p>
      </div>

      <div class="form__field">
        <label for="message">Mensagem (opcional)</label>
        <textarea id="message" name="message" maxlength="2000" />
        <p class="form__hint">
          Não escreva o nome da criança aqui — esse dado é coletado presencialmente, na
          matrícula efetiva.
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

      <button type="submit" class="btn btn--primary">Enviar manifestação de interesse</button>
    </form>
  </div>
</template>
