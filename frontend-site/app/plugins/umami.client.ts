/**
 * Umami (analytics cookieless) — ver docs/arquitetura.md, seção Analytics. Sem GA4, sem
 * banner de consentimento: Umami não usa cookie nem localStorage.
 *
 * Não injeta nada enquanto NUXT_PUBLIC_UMAMI_WEBSITE_ID/NUXT_PUBLIC_UMAMI_URL não estiverem
 * configurados (ver .env.example) — isso é esperado até o domínio/conta Umami existirem.
 */
export default defineNuxtPlugin(() => {
  const config = useRuntimeConfig().public

  if (!config.umamiWebsiteId || !config.umamiUrl) {
    return
  }

  useHead({
    script: [
      {
        src: config.umamiUrl,
        'data-website-id': config.umamiWebsiteId,
        defer: true,
      },
    ],
  })
})
