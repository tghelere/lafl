export default defineEventHandler((event) => {
  const { siteUrl } = useRuntimeConfig(event).public

  setHeader(event, 'Content-Type', 'text/plain; charset=utf-8')

  const lines = ['User-agent: *', 'Allow: /']

  // Sem domínio registrado ainda (ver CLAUDE.md, "Estado do projeto"), a diretiva Sitemap
  // exige URL absoluta — só a incluímos quando NUXT_PUBLIC_SITE_URL estiver configurado.
  if (siteUrl) {
    lines.push('', `Sitemap: ${siteUrl}/sitemap.xml`)
  }

  return lines.join('\n') + '\n'
})
