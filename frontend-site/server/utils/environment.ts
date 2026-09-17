/**
 * `NUXT_PUBLIC_ENVIRONMENT` é o ambiente em que este site está servindo: vazio (ou qualquer
 * outro valor) em desenvolvimento e em produção, `staging` na homologação.
 *
 * Existe por um motivo só: a homologação fica num domínio público, com o conteúdo real da
 * instituição, e não pode ser indexada por buscador nenhum — conteúdo duplicado tira o site
 * de verdade da busca, e a instituição depende da busca orgânica (ver docs/arquitetura.md,
 * seção Analytics/SEO). A autenticação básica do Nginx (ver docs/tarefas/07b) é a primeira
 * barreira; esta é a segunda, do lado da aplicação, para o caso de a primeira ser afrouxada
 * um dia para deixar alguém da instituição testar sem senha.
 */
export function isStaging(event: Parameters<typeof useRuntimeConfig>[0]): boolean {
  return useRuntimeConfig(event).public.environment === 'staging'
}
