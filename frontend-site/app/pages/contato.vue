<script setup lang="ts">
// Formulário de contato geral — titular é o próprio visitante (adulto).
import { MapPin, Phone } from '@lucide/vue'

const { hasError, fieldFailed } = useFormErrorState()

// WhatsApp do Bazar (único número confirmado — não há WhatsApp da sede, ver
// docs/roadmap.md). Mensagem própria do contexto desta página: contato geral, não
// agendamento de coleta (esse é o texto usado em /bazar/agendar-coleta).
const WHATSAPP_NUMBER = '5543999500183'
const WHATSAPP_MESSAGE = 'Olá! Gostaria de falar com o Lar Anália Franco.'
const whatsappHref = `https://wa.me/${WHATSAPP_NUMBER}?text=${encodeURIComponent(WHATSAPP_MESSAGE)}`

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

    <div class="contact-locations">
      <div class="card">
        <h2>Sede / CEI Anália Franco</h2>
        <p class="contact-locations__line">
          <MapPin :size="16" aria-hidden="true" />
          Av. Anália Franco, 33 — Jd. Aeroporto, Londrina/PR
        </p>
        <p class="contact-locations__line">
          <Phone :size="16" aria-hidden="true" />
          (43) 3325-8060
        </p>
      </div>
      <div class="card">
        <h2>Bazar Beneficente</h2>
        <p class="contact-locations__line">
          <MapPin :size="16" aria-hidden="true" />
          Rua Rosa Siqueira, 152 — Jd. Aeroporto, Londrina/PR
        </p>
        <p class="contact-locations__line">
          <Phone :size="16" aria-hidden="true" />
          (43) 3322-2373
        </p>
        <a
          class="btn btn--secondary contact-locations__whatsapp"
          :href="whatsappHref"
          target="_blank"
          rel="noopener noreferrer"
        >
          <AppWhatsappIcon :size="16" />
          WhatsApp (43) 99950-0183
        </a>
      </div>
    </div>

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

<style scoped>
.contact-locations {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr));
  gap: var(--space-5);
  margin-block: var(--space-6);
}

.contact-locations h2 {
  margin-top: 0;
  font-size: var(--text-lg);
}

.contact-locations p {
  margin: 0;
}

.contact-locations p + p {
  margin-top: var(--space-2);
}

.contact-locations__line {
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

.contact-locations__line svg {
  flex-shrink: 0;
  color: var(--color-text-muted);
}

.contact-locations__whatsapp {
  margin-top: var(--space-3);
}
</style>
