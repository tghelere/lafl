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

/** Host de escuta dos três servidores. Loopback: nada da bateria fica exposto na rede. */
export const BIND_HOST = '127.0.0.1'

export function portOf(url: string): string {
  return new URL(url).port
}
