import { fileURLToPath } from 'node:url'

/**
 * Espelho de backend/database/seeders/E2eSeeder.php — é aquele seeder que cria estas contas,
 * e mudar uma lista sem a outra quebra a bateria inteira no login. Nenhum dado real: os nomes
 * são fictícios e o domínio @e2e.local é reservado.
 */
export const E2E_PASSWORD = 'senha-de-teste-e2e'

/** Papéis que ganham storageState no global setup — um login só cada, reaproveitado depois. */
export const ROLE_USERS = {
  super_admin: { email: 'super.admin@e2e.local', name: 'Sara Super Admin' },
  direcao: { email: 'direcao@e2e.local', name: 'Dora Direção' },
  financeiro: { email: 'financeiro@e2e.local', name: 'Fabio Financeiro' },
  contraturno: { email: 'contraturno@e2e.local', name: 'Clara Contraturno' },
  bazar: { email: 'bazar@e2e.local', name: 'Beto Bazar' },
  atendimento: { email: 'atendimento@e2e.local', name: 'Alice Atendimento' },
  comunicacao: { email: 'comunicacao@e2e.local', name: 'Cauê Comunicação' },
  financeiro_contraturno: { email: 'financeiro.contraturno@e2e.local', name: 'Marta Multipapel' },
} as const

export type RoleKey = keyof typeof ROLE_USERS

export const ROLE_KEYS = Object.keys(ROLE_USERS) as RoleKey[]

/**
 * Contas de uso exclusivo dos testes que alteram o estado da própria conta (senha, sessão).
 * Nunca entram no storageState compartilhado: se entrassem, trocar a senha de uma delas
 * derrubaria a sessão salva e os outros testes passariam a depender da ordem de execução
 * (Sanctum\AuthenticateSession compara o hash da senha guardado na sessão com o atual).
 */
export const STANDALONE_USERS = {
  loginValido: { email: 'login.valido@e2e.local', name: 'Lina Login' },
  loginSenhaErrada: { email: 'login.senha-errada@e2e.local', name: 'Sérgio Senha' },
  loginDesativado: { email: 'login.desativado@e2e.local', name: 'Davi Desativado' },
  trocaDeSenha: { email: 'troca.de.senha@e2e.local', name: 'Tereza Troca' },
} as const

/** Diretório dos storageState gravados pelo global setup. Ignorado pelo git. */
export const AUTH_DIR = fileURLToPath(new URL('../.auth/', import.meta.url))

export function storageStatePath(role: RoleKey): string {
  return `${AUTH_DIR}${role}.json`
}
