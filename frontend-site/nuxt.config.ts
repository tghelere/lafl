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
      routes: ['/robots.txt', '/sitemap.xml'],
    },
  },
})
