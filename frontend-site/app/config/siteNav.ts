// Estrutura de navegação do site — fonte única para o submenu do topo (AppHeader.vue) e
// para a navegação de seção (AppSectionNav.vue). Não existe endpoint público de listagem de
// páginas (ver nuxt.config.ts), então esta lista é mantida à mão, igual aos `navItems` que
// AppHeader.vue já hardcodava antes desta sessão.
//
// Voluntariado (/como-ajudar/voluntariado) e apoio empresarial ao contraturno
// (/contraturno/apoiar) ficam de fora de propósito — decisão de escopo desta sessão: o site
// vai ao ar com um conjunto fechado de páginas, e essas duas entram depois do lançamento.
// As páginas continuam existindo (não foram apagadas) e seguem alcançáveis por quem tem o
// link direto, só não aparecem em nenhuma navegação agora. Não adicione novo item aqui sem
// atualizar a decisão de escopo correspondente.
export type SiteNavItem = {
  label: string
  to: string
}

export type SiteNavSection = SiteNavItem & {
  children: SiteNavItem[]
}

export const siteNav: SiteNavSection[] = [
  {
    label: 'Quem somos',
    to: '/quem-somos',
    children: [
      { label: 'Nossa história', to: '/quem-somos/nossa-historia' },
      { label: 'Missão, visão e valores', to: '/quem-somos/missao-visao-valores' },
      { label: 'Governança', to: '/quem-somos/governanca' },
      // "O Lar hoje" removido daqui: página em status Draft (404 real), bloqueada até
      // revisão de advogado — ver docs/roadmap.md, "BLOQUEIO DE PUBLICAÇÃO". Devolver o item
      // quando a página voltar a Published no ContentPagesSeeder.
    ],
  },
  {
    label: 'Educação infantil',
    to: '/educacao-infantil',
    children: [
      { label: 'Dia da criança', to: '/educacao-infantil/dia-da-crianca' },
      { label: 'Proposta pedagógica', to: '/educacao-infantil/proposta-pedagogica' },
      { label: 'Alimentação e saúde', to: '/educacao-infantil/alimentacao-e-saude' },
      { label: 'Estrutura', to: '/educacao-infantil/estrutura' },
      { label: 'Depoimentos', to: '/educacao-infantil/depoimentos' },
      { label: 'Matrícula', to: '/educacao-infantil/matricula' },
    ],
  },
  {
    label: 'Contraturno',
    to: '/contraturno',
    children: [
      { label: 'O projeto', to: '/contraturno/o-projeto' },
      { label: 'Para quem é', to: '/contraturno/para-quem-e' },
      { label: 'Como funciona', to: '/contraturno/como-funciona' },
      { label: 'Parceiros', to: '/contraturno/parceiros' },
      { label: 'O que vem por aí', to: '/contraturno/o-que-vem-por-ai' },
      { label: 'Avise-me quando abrir', to: '/contraturno/inscricao' },
    ],
  },
  {
    label: 'Bazar',
    to: '/bazar',
    children: [
      { label: 'Visite a loja', to: '/bazar/visite-a-loja' },
      { label: 'O que aceitamos', to: '/bazar/o-que-aceitamos' },
      { label: 'Agendar coleta', to: '/bazar/agendar-coleta' },
      { label: 'Para onde vai', to: '/bazar/para-onde-vai' },
      { label: 'Sua compra vira educação', to: '/bazar/sua-compra-vira-educacao' },
    ],
  },
  {
    label: 'Como ajudar',
    to: '/como-ajudar',
    children: [
      { label: 'Doar', to: '/como-ajudar/doar' },
      { label: 'Parceiros', to: '/como-ajudar/parceiros' },
    ],
  },
  {
    label: 'Transparência',
    to: '/transparencia',
    children: [{ label: 'Documentos', to: '/transparencia/documentos' }],
  },
]
