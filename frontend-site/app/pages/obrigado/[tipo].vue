<script setup lang="ts">
// Confirmação pós-formulário, noindex, URL própria por tipo — permite medir conversão no
// Umami sem depender de JavaScript no formulário em si (ver docs/estrutura-site.md §1.3).
type ThankYouType = 'inscricao' | 'coleta' | 'voluntariado' | 'parceria' | 'contato'

const MESSAGES: Record<ThankYouType, { title: string; body: string }> = {
  inscricao: {
    title: 'Contato recebido',
    body: 'Vamos avisar você assim que as inscrições da Escola de Contraturno abrirem.',
  },
  coleta: {
    title: 'Agendamento recebido',
    body: 'Recebemos seu pedido de coleta. A equipe do bazar entrará em contato para combinar o horário.',
  },
  voluntariado: {
    title: 'Candidatura recebida',
    body: 'Recebemos sua candidatura de voluntariado. Entraremos em contato em breve.',
  },
  parceria: {
    title: 'Proposta recebida',
    body: 'Recebemos sua proposta de parceria. Entraremos em contato para conversar sobre os próximos passos.',
  },
  contato: {
    title: 'Mensagem recebida',
    body: 'Recebemos sua mensagem e responderemos o quanto antes.',
  },
}

function isThankYouType(value: unknown): value is ThankYouType {
  return typeof value === 'string' && value in MESSAGES
}

const route = useRoute()
const tipo = route.params.tipo

if (!isThankYouType(tipo)) {
  throw createError({ statusCode: 404, statusMessage: 'Página não encontrada', fatal: true })
}

const content = MESSAGES[tipo]

usePageSeo({
  title: `${content.title} — Lar Anália Franco`,
  noindex: true,
})

const { track } = useUmamiTrack()

onMounted(() => {
  track(`formulario:${tipo}`)
})
</script>

<template>
  <div class="thank-you">
    <h1>{{ content.title }}</h1>
    <p class="prose">{{ content.body }}</p>
    <NuxtLink to="/" class="btn btn--secondary">Voltar para o início</NuxtLink>
  </div>
</template>

<style scoped>
.thank-you {
  max-width: 34rem;
  padding-block: var(--space-6);
}
</style>
