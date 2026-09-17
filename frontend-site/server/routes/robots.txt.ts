/**
 * Rota dinâmica de propósito — saiu de `nitro.prerender.routes` nesta sessão. Um robots.txt
 * gravado no build carrega a decisão do momento do build: o mesmo pacote publicado em
 * homologação e em produção diria "Allow: /" nos dois, e a homologação entraria na busca.
 * Resolvido a cada requisição, quem decide é o ambiente em que o servidor está rodando.
 *
 * O custo é uma rota a mais em SSR (três linhas de texto) e o `nuxt generate` deixar de
 * emitir o arquivo — o site já não é hospedável como estático de qualquer forma (os cinco
 * formulários, /transparencia/documentos e o redirect de slug antigo exigem o servidor
 * Nitro; ver docs/roadmap.md).
 */
export default defineEventHandler((event) => {
  setHeader(event, 'Content-Type', 'text/plain; charset=utf-8')

  // Homologação não é indexável, ponto — nem o sitemap é anunciado (ver
  // server/plugins/staging-noindex.ts, que cuida do cabeçalho equivalente).
  if (isStaging(event)) {
    setHeader(event, 'Cache-Control', 'no-store')

    return 'User-agent: *\nDisallow: /\n'
  }

  const { siteUrl } = useRuntimeConfig(event).public

  const lines = ['User-agent: *', 'Allow: /']

  // Sem domínio registrado ainda (ver CLAUDE.md, "Estado do projeto"), a diretiva Sitemap
  // exige URL absoluta — só a incluímos quando NUXT_PUBLIC_SITE_URL estiver configurado.
  if (siteUrl) {
    lines.push('', `Sitemap: ${siteUrl}/sitemap.xml`)
  }

  return lines.join('\n') + '\n'
})
