// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },

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
    // O crawler do `nuxt generate` só segue links de página — robots.txt e sitemap.xml são
    // rotas do Nitro sem link nenhum apontando pra elas, então precisam ser listadas aqui
    // para entrar no output estático (SSG).
    prerender: {
      routes: [
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
