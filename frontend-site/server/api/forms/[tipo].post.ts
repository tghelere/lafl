import {
  createError,
  defineEventHandler,
  getRequestIP,
  getRouterParam,
  readFormData,
  sendRedirect,
} from 'h3'

// Proxy servidor-a-servidor para os cinco formulários públicos (ver docs/estrutura-site.md
// Parte 2). Existe para o <form method="post"> de cada página funcionar sem JavaScript: um
// POST direto ao backend Laravel devolveria só o JSON da API, nunca o redirect visível para
// /obrigado/:tipo que a UX exige — e a API precisa continuar REST puro, sem view (ver
// CLAUDE.md), então quem decide "o que o navegador vê" é este proxy, não o Laravel.
//
// Repassa X-Forwarded-For com o IP real do visitante — o backend só confia nesse cabeçalho
// vindo do próprio host do Nuxt (ver TRUSTED_PROXIES em bootstrap/app.php); sem isso, o rate
// limit por IP do Laravel veria sempre o IP deste servidor, não o de cada visitante.
type FormType = 'inscricao' | 'coleta' | 'voluntariado' | 'parceria' | 'contato'

const FORM_ROUTES: Record<FormType, { endpoint: string; origin: string }> = {
  inscricao: { endpoint: 'program-applications', origin: '/contraturno/inscricao' },
  coleta: { endpoint: 'pickup-requests', origin: '/bazar/agendar-coleta' },
  voluntariado: { endpoint: 'volunteer-applications', origin: '/como-ajudar/voluntariado' },
  parceria: { endpoint: 'partnership-inquiries', origin: '/contraturno/apoiar' },
  contato: { endpoint: 'contact-messages', origin: '/contato' },
}

function isFormType(value: string | undefined): value is FormType {
  return value !== undefined && value in FORM_ROUTES
}

function failedFields(error: unknown): string[] {
  const data = (error as { data?: { errors?: Record<string, unknown> } } | undefined)?.data
  return data?.errors ? Object.keys(data.errors) : []
}

export default defineEventHandler(async (event) => {
  const tipo = getRouterParam(event, 'tipo')

  if (!isFormType(tipo)) {
    throw createError({ statusCode: 404, statusMessage: 'Formulário não encontrado' })
  }

  const route = FORM_ROUTES[tipo]
  const form = await readFormData(event)

  // Honeypot (ver App\Support\Honeypot no backend, mesmo princípio): resposta idêntica à de
  // sucesso, sem sequer chamar a API.
  if (form.get('website')) {
    return sendRedirect(event, `/obrigado/${tipo}`, 303)
  }

  const config = useRuntimeConfig()
  const ip = getRequestIP(event, { xForwardedFor: true }) ?? '0.0.0.0'

  const body: Record<string, string> = {}
  for (const [key, value] of form.entries()) {
    if (key === 'website' || typeof value !== 'string') continue
    body[key] = value
  }

  try {
    await $fetch(`${config.public.apiUrl}/api/v1/public/${route.endpoint}`, {
      method: 'POST',
      body,
      headers: { 'x-forwarded-for': ip },
    })

    return sendRedirect(event, `/obrigado/${tipo}`, 303)
  } catch (error) {
    const fields = failedFields(error)
    const query = fields.length > 0 ? `?erro=1&campos=${encodeURIComponent(fields.join(','))}` : '?erro=1'

    return sendRedirect(event, `${route.origin}${query}`, 303)
  }
})
