/**
 * Contato geral da instituição — sede / CEI Anália Franco. Existe para pontos do site que
 * precisam funcionar mesmo com a API fora do ar (hoje só app/error.vue, no 503): se a API caiu,
 * é exatamente quando um canal alternativo mais importa, então este arquivo nunca é lido dela.
 *
 * Os mesmos dados aparecem em prosa no rodapé (AppFooter.vue), em /contato e no JSON-LD
 * (useOrganizationJsonLd.ts) — quatro lugares com o mesmo dado, hoje; unificar depende de a
 * instituição virar entidade editável no painel (`settings`, ver docs/roadmap.md). Não inventar
 * número novo aqui — vem de docs/contexto.md.
 *
 * O WhatsApp é o do Bazar (único número confirmado — não há WhatsApp da sede, ver
 * docs/contexto.md), usado aqui como canal geral da instituição, do mesmo jeito que /contato
 * já faz.
 */
export const institutionContact = {
  address: 'Av. Anália Franco, 33 — Jd. Aeroporto, Londrina/PR',
  phone: '(43) 3325-8060',
  phoneHref: 'tel:+554333258060',
  whatsappNumber: '5543999500183',
  whatsappMessage: 'Olá! Gostaria de falar com o Lar Anália Franco.',
} as const

export function whatsappHref(message: string = institutionContact.whatsappMessage): string {
  return `https://wa.me/${institutionContact.whatsappNumber}?text=${encodeURIComponent(message)}`
}
