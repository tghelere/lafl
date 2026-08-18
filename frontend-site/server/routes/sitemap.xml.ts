/**
 * Sitemap estático para o esqueleto (só a home existe). Geração dinâmica a partir de
 * `updated_at` de pages/posts é para quando esse domínio de conteúdo existir (ver
 * docs/arquitetura.md, seção SEO) — nesse momento, considerar um módulo dedicado
 * (@nuxtjs/sitemap) em vez de estender esta rota manualmente.
 */
export default defineEventHandler((event) => {
  const { siteUrl } = useRuntimeConfig(event).public

  setHeader(event, 'Content-Type', 'application/xml; charset=utf-8')

  const homeUrl = siteUrl ? `${siteUrl}/` : '/'

  return `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc>${homeUrl}</loc>
  </url>
</urlset>
`
})
