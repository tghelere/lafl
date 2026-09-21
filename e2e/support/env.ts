/**
 * Endereços da pilha sob teste. As portas são próprias da bateria (8100/5175/3100), distintas
 * das de desenvolvimento (8000/5173/3000), para que `npm run test:e2e` possa rodar com o
 * ambiente de desenvolvimento no ar na mesma máquina sem disputar porta nem sessão.
 *
 * As variáveis E2E_* existem para o CI e para depuração (apontar a bateria para uma pilha já
 * de pé); no uso normal ninguém precisa defini-las — o webServer do playwright.config.ts sobe
 * exatamente estes três endereços.
 *
 * Sempre `localhost`, nunca `127.0.0.1`: o cookie de sessão do Sanctum é emitido para o
 * domínio `localhost` (ver SESSION_DOMAIN em backend/.env.e2e), e um cookie com
 * `Domain=localhost` é recusado pelo navegador numa resposta servida por `127.0.0.1`.
 */
export const API_URL = process.env.E2E_API_URL ?? 'http://localhost:8100'
export const ADMIN_URL = process.env.E2E_ADMIN_URL ?? 'http://localhost:5175'
export const SITE_URL = process.env.E2E_SITE_URL ?? 'http://localhost:3100'

/**
 * Segunda instância do MESMO build do site, subida por tests/homologacao/noindex.spec.ts com
 * `NUXT_PUBLIC_ENVIRONMENT=staging` — é a única forma de provar que o bloqueio de indexação
 * é decidido em tempo de execução, e não gravado no pacote. Fora daquele arquivo ninguém a
 * usa, e ela só fica no ar durante ele.
 */
export const STAGING_SITE_URL = process.env.E2E_STAGING_SITE_URL ?? 'http://localhost:3101'

/**
 * Terceira instância do MESMO build do site (ver playwright.config.ts), com
 * `NUXT_PUBLIC_API_URL` apontado para `API_URL_FORA_DO_AR` — uma porta sem nada escutando.
 * Existe só para o projeto `site-sem-api`, que cobre o que a bateria normal não alcança: a
 * chamada que precisaria falhar é do SSR, fora do page.route() do Playwright (ver
 * docs/decisoes/0019-falha-da-api-responde-503-nao-404.md). Nasce sempre — não só quando o
 * projeto `site-sem-api` roda — porque o `webServer` do Playwright sobe todo o array antes de
 * escolher quais projetos rodam.
 */
export const SITE_SEM_API_URL = process.env.E2E_SITE_SEM_API_URL ?? 'http://localhost:3102'

/**
 * Porta fechada de propósito, para simular a API fora do ar sem depender de derrubar nada.
 * `SITE_SEM_API_URL` acima aponta para cá.
 */
export const API_URL_FORA_DO_AR = process.env.E2E_API_URL_FORA_DO_AR ?? 'http://localhost:8199'

/** Host de escuta dos três servidores. Loopback: nada da bateria fica exposto na rede. */
export const BIND_HOST = '127.0.0.1'

export function portOf(url: string): string {
  return new URL(url).port
}
