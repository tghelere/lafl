<script setup lang="ts">
// Proposta de parceria empresarial — titular é o contato PJ (adulto), sem envolvimento de
// criança nesta ponta (ver docs/dominio.md).
const { hasError, fieldFailed } = useFormErrorState()

usePageSeo({
  title: 'Apoiar o Projeto — Contraturno — Lar Anália Franco',
  description: 'Empresas interessadas em apoiar a Escola de Contraturno do Lar Anália Franco.',
  noindex: true,
})
</script>

<template>
  <div>
    <nav class="breadcrumb" aria-label="Trilha de navegação">
      <ol>
        <li><NuxtLink to="/">Início</NuxtLink></li>
        <li><NuxtLink to="/contraturno">Contraturno</NuxtLink></li>
        <li><span aria-current="page">Apoiar o Projeto</span></li>
      </ol>
    </nav>

    <h1>Apoiar o Projeto</h1>
    <p class="prose">
      Sua empresa pode apoiar a Escola de Contraturno com recursos financeiros, materiais,
      voluntariado corporativo ou patrocínio. Conte um pouco sobre a proposta.
    </p>

    <div v-if="hasError" class="form-alert" role="alert">
      <p>Não foi possível enviar a proposta. Confira os campos abaixo e tente novamente.</p>
    </div>

    <form method="post" action="/api/forms/parceria" class="form">
      <div class="form__field" :class="{ 'form__field--invalid': fieldFailed('company_name') }">
        <label for="company_name">Empresa</label>
        <input id="company_name" name="company_name" type="text" autocomplete="organization" required />
        <p v-if="fieldFailed('company_name')" class="form__error">Informe o nome da empresa.</p>
      </div>

      <div class="form__field" :class="{ 'form__field--invalid': fieldFailed('tax_id') }">
        <label for="tax_id">CNPJ</label>
        <input id="tax_id" name="tax_id" type="text" placeholder="00.000.000/0000-00" required />
        <p v-if="fieldFailed('tax_id')" class="form__error">Informe um CNPJ válido, com 14 dígitos.</p>
      </div>

      <div class="form__field" :class="{ 'form__field--invalid': fieldFailed('contact_name') }">
        <label for="contact_name">Nome do contato</label>
        <input id="contact_name" name="contact_name" type="text" autocomplete="name" required />
        <p v-if="fieldFailed('contact_name')" class="form__error">Informe o nome do contato.</p>
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

      <div class="form__field" :class="{ 'form__field--invalid': fieldFailed('support_type') }">
        <label for="support_type">Tipo de apoio</label>
        <select id="support_type" name="support_type" required>
          <option value="" disabled selected>Selecione</option>
          <option value="financial">Apoio financeiro</option>
          <option value="in_kind">Doação de materiais ou serviços</option>
          <option value="volunteering">Voluntariado corporativo</option>
          <option value="sponsorship">Patrocínio de evento</option>
          <option value="other">Outro</option>
        </select>
        <p v-if="fieldFailed('support_type')" class="form__error">Informe o tipo de apoio pretendido.</p>
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

      <button type="submit" class="btn btn--primary">Enviar proposta</button>
    </form>
  </div>
</template>
