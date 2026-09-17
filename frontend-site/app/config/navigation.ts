// Fonte única da navegação de topo — header, gaveta mobile e rodapé leem daqui. Nenhum
// desses três componentes declara link solto em template (ver docs/design/navegacao.md §3).
//
// Não confundir com app/config/siteNav.ts: aquele arquivo alimenta a navegação de seção
// (AppSectionNav.vue) — a lista completa de páginas-irmãs dentro de uma seção. Este arquivo
// é só a estrutura rasa do topo (5 itens + CTA), curada, não a árvore inteira do site (ver
// docs/design/navegacao.md §1: "o topo abre portas; não reproduz o sitemap").
//
// Voluntariado, Empresas parceiras e Novidades do bazar ficam de fora de propósito —
// docs/design/navegacao.md §2, "fora do lançamento". Não adicionar aqui sem atualizar essa
// decisão de escopo.
export type NavItem = {
  label: string
  to: string
  children?: { label: string; to: string; hint?: string }[]
}

export const navigation: NavItem[] = [
  {
    label: 'Quem somos',
    to: '/quem-somos',
    children: [
      { label: 'Nossa história', to: '/quem-somos/nossa-historia' },
      { label: 'Governança', to: '/quem-somos/governanca' },
    ],
  },
  {
    label: 'O que fazemos',
    to: '/o-que-fazemos',
    children: [
      {
        label: 'Educação infantil',
        to: '/educacao-infantil',
        hint: 'Creche e pré-escola, 1 a 5 anos',
      },
      {
        label: 'Escola de contraturno',
        to: '/contraturno',
        hint: 'Crianças e adolescentes de 6 a 15 anos, turmas previstas para 2027',
      },
      {
        label: 'Bazar beneficente',
        to: '/bazar',
        hint: 'Sustenta o que o convênio não cobre',
      },
    ],
  },
  {
    label: 'Como ajudar',
    to: '/como-ajudar',
    children: [
      { label: 'Doar', to: '/doar' },
      { label: 'Doar itens ao bazar', to: '/bazar/agendar-coleta' },
      { label: 'Parceiros', to: '/como-ajudar/parceiros' },
    ],
  },
  {
    label: 'Transparência',
    to: '/transparencia',
  },
  {
    label: 'Contato',
    to: '/contato',
  },
]

export const ctaItem: NavItem = { label: 'Doar', to: '/doar' }
