/**
 * Contato institucional — dois locais distintos, sede/CEI e bazar (ver docs/contexto.md, "Dois
 * locais distintos"). Fonte única para o que antes se repetia em quatro lugares — AppFooter.vue,
 * contato.vue, useOrganizationJsonLd.ts e app/error.vue — cada um formatando o mesmo endereço e
 * telefone à própria mão (ver docs/relatorio-sessao-23.md). Mudar um número agora é editar só
 * este arquivo.
 *
 * Nunca lido da API, de propósito: é a fonte também de app/error.vue, no 503 — se a API caiu, é
 * exatamente quando um canal alternativo mais importa. Não inventar número novo aqui — vem de
 * docs/contexto.md.
 *
 * Quando a instituição virar entidade editável no painel (`settings`, ver docs/roadmap.md), é
 * este arquivo que ela substitui — os quatro consumidores continuam lendo a mesma forma (as
 * funções abaixo), só a fonte dos dados muda.
 */
const CITY = 'Londrina'
const STATE = 'PR'

export type InstitutionLocation = {
  /** Nome estruturado — só o JSON-LD usa (schema.org `location[].name`). */
  name: string
  street: string
  /** Forma completa do bairro — só o JSON-LD usa (schema.org `streetAddress`). */
  neighborhood: string
  /** Forma abreviada — prosa (rodapé, /contato, error.vue) e o texto do link de mapa. */
  neighborhoodAbbrev: string
  ddd: string
  /** Sem DDD, com hífen — ex. "3325-8060". */
  number: string
}

export const headquarters: InstitutionLocation = {
  name: 'Sede e CEI Anália Franco',
  street: 'Av. Anália Franco, 33',
  neighborhood: 'Jardim Aeroporto',
  neighborhoodAbbrev: 'Jd. Aeroporto',
  ddd: '43',
  number: '3325-8060',
}

export const bazaar: InstitutionLocation = {
  name: 'Bazar Beneficente',
  street: 'Rua Rosa Siqueira, 152',
  neighborhood: 'Jardim Aeroporto',
  neighborhoodAbbrev: 'Jd. Aeroporto',
  ddd: '43',
  number: '3322-2373',
}

/** "(43) 3325-8060" — telefone como a pessoa lê. */
export function phone(location: InstitutionLocation): string {
  return `(${location.ddd}) ${location.number}`
}

/** "tel:+554333258060" — para o atributo `href`. */
export function phoneHref(location: InstitutionLocation): string {
  return `tel:+55${location.ddd}${location.number.replace('-', '')}`
}

/** "+55 43 3325-8060" — formato que o JSON-LD (schema.org `telephone`) já publicava. */
export function jsonLdTelephone(location: InstitutionLocation): string {
  return `+55 ${location.ddd} ${location.number}`
}

/** "Av. Anália Franco, 33 — Jd. Aeroporto, Londrina/PR" — a linha de prosa (rodapé, /contato, error.vue). */
export function addressLine(location: InstitutionLocation): string {
  return `${location.street} — ${location.neighborhoodAbbrev}, ${CITY}/${STATE}`
}

/** Mesma linha, com vírgula no lugar do travessão — o texto que AppMapaLocal manda para o link de mapa. */
export function mapQuery(location: InstitutionLocation): string {
  return `${location.street}, ${location.neighborhoodAbbrev}, ${CITY}/${STATE}`
}

/** "Av. Anália Franco, 33 — Jardim Aeroporto" — schema.org `streetAddress`; cidade/UF são campos à parte ali. */
export function jsonLdStreetAddress(location: InstitutionLocation): string {
  return `${location.street} — ${location.neighborhood}`
}

/**
 * O WhatsApp é o do Bazar (único número confirmado — não há WhatsApp da sede, ver
 * docs/contexto.md), usado como canal geral da instituição em /contato e em app/error.vue.
 * `bazar/agendar-coleta.vue` também lê o número daqui, mas passa a `whatsappHref` uma
 * mensagem própria do contexto de coleta — a mensagem muda por página, o número não.
 */
export const institutionContact = {
  whatsappNumber: '5543999500183',
  whatsappPhone: '(43) 99950-0183',
  whatsappMessage: 'Olá! Gostaria de falar com o Lar Anália Franco.',
} as const

export function whatsappHref(message: string = institutionContact.whatsappMessage): string {
  return `https://wa.me/${institutionContact.whatsappNumber}?text=${encodeURIComponent(message)}`
}
