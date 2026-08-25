// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },

  // Ordem importa: fontes antes de tokens, tokens antes de base, base antes de componentes.
  css: [
    '~/assets/css/fonts.css',
    '~/assets/css/tokens.css',
    '~/assets/css/base.css',
    '~/assets/css/components.css',
  ],

  runtimeConfig: {
    public: {
      // Preenchidos via NUXT_PUBLIC_* no .env — ver .env.example.
      apiUrl: '',
      siteUrl: '',
      umamiWebsiteId: '',
      umamiUrl: '',
    },
  },

  app: {
    head: {
      htmlAttrs: { lang: 'pt-BR' },
    },
  },

  nitro: {
    prerender: {
      // O cabeçalho (AppHeader.vue) já linka os sete itens do menu principal de
      // docs/estrutura-site.md §1.1, mas só "Quem somos" existe como página de fato — as
      // outras seis (educação infantil, contraturno, bazar, como ajudar, transparência)
      // ainda não têm rota implementada (ver docs/roadmap.md). Com o crawler padrão
      // (`crawlLinks: true`), `nuxt generate` seguiria esses links e falharia tentando
      // prerenderizar uma rota sem página. `crawlLinks: false` faz o generate prerenderizar
      // só o que está listado abaixo — mesma lógica manual que "Quem somos" já usa: quando
      // uma rota nova entrar, ela entra nesta lista.
      crawlLinks: false,
      routes: [
        '/',
        '/robots.txt',
        '/sitemap.xml',
        // "Quem somos" e as quatro subpáginas de docs/estrutura-site.md §1.2 — slugs atuais
        // conhecidos no momento do build. Renomear uma delas exige rodar `nuxt generate` de
        // novo para o novo slug entrar no output estático; o slug antigo só resolve com 301
        // se o site estiver rodando com o servidor Nitro (`nuxt build` + node), não em
        // hospedagem 100% estática (ver docs/roadmap.md).
        '/quem-somos',
        '/quem-somos/nossa-historia',
        '/quem-somos/missao-visao-valores',
        '/quem-somos/governanca',
        '/quem-somos/o-lar-hoje',
      ],
    },
  },
})
