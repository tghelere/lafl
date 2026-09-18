<script setup lang="ts">
// Aviso de interesse na Escola de Contraturno — o programa ainda não abriu inscrições (ver
// docs/contexto.md), então este NÃO é um formulário de inscrição: coleta só nome e telefone
// do responsável, para avisar quando as inscrições abrirem. Nenhum dado de criança ou
// adolescente (ver ADR 0007).
const { hasError, fieldFailed } = useFormErrorState()

usePageSeo({
  title: 'Avise-me — Contraturno — Lar Anália Franco',
  description: 'Deixe seu contato para ser avisado quando as inscrições da Escola de Contraturno abrirem.',
})
</script>

<template>
  <div>
    <nav class="breadcrumb" aria-label="Trilha de navegação">
      <ol>
        <li><NuxtLink to="/">Início</NuxtLink></li>
        <li><NuxtLink to="/contraturno">Contraturno</NuxtLink></li>
        <li><span aria-current="page">Avise-me</span></li>
      </ol>
    </nav>

    <AppSectionNav />

    <h1>Avise-me quando abrir</h1>
    <p class="prose">
      A Escola de Contraturno ainda não abriu turmas nem inscrições — início previsto para
      2027. Deixe seu nome e telefone que avisamos assim que as inscrições abrirem.
    </p>

    <div v-if="hasError" class="form-alert" role="alert">
      <p>Não foi possível enviar seu contato. Confira os campos abaixo e tente novamente.</p>
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

      <button type="submit" class="btn btn--primary">Avise-me</button>
    </form>
  </div>
</template>
