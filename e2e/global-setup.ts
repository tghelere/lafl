import { spawnSync } from 'node:child_process'
import { mkdirSync, rmSync } from 'node:fs'
import { fileURLToPath } from 'node:url'

import { firefox } from '@playwright/test'

import { ADMIN_URL } from './support/env'
import { AUTH_DIR, E2E_PASSWORD, ROLE_KEYS, ROLE_USERS, storageStatePath } from './support/users'

const BACKEND_DIR = fileURLToPath(new URL('../backend/', import.meta.url))

/**
 * Roda DEPOIS dos servidores do `webServer` subirem (o runner do Playwright executa os plugins
 * de webServer antes do globalSetup), o que é o que permite fazer as duas coisas aqui:
 *
 * 1. recriar o banco de e2e do zero — `php artisan e2e:prepare`, que por sua vez recusa rodar
 *    se o banco resolvido não for `lar_analia_franco_e2e` (ver
 *    App\Console\Commands\PrepareE2eDatabase). Nenhum comando destrutivo é disparado daqui
 *    direto, de propósito: a guarda mora do lado do Laravel, onde o nome do banco é resolvido.
 *
 * 2. fazer UM login por papel e guardar o cookie de sessão em .auth/<papel>.json. Os testes
 *    reaproveitam esses arquivos em vez de passar pela tela de login: além de economizar
 *    tempo, é o que mantém a bateria longe do rate limit de login (5 por minuto por IP+e-mail,
 *    ver App\Providers\AppServiceProvider) — só os testes que são sobre login fazem login, e
 *    esses usam contas próprias.
 */
export default async function globalSetup(): Promise<void> {
  prepareDatabase()
  await captureSessions()
}

function prepareDatabase(): void {
  const result = spawnSync('php', ['artisan', 'e2e:prepare'], {
    cwd: BACKEND_DIR,
    env: { ...process.env, APP_ENV: 'e2e' },
    stdio: 'inherit',
  })

  if (result.status !== 0) {
    throw new Error(
      'Falha ao preparar o banco de e2e (php artisan e2e:prepare). ' +
        'Confira se o Postgres do docker-compose está no ar — ver e2e/README.md.',
    )
  }
}

async function captureSessions(): Promise<void> {
  rmSync(AUTH_DIR, { recursive: true, force: true })
  mkdirSync(AUTH_DIR, { recursive: true })

  const browser = await firefox.launch()

  try {
    for (const role of ROLE_KEYS) {
      const context = await browser.newContext()
      const page = await context.newPage()

      await page.goto(`${ADMIN_URL}/login`)
      await page.getByLabel('E-mail').fill(ROLE_USERS[role].email)
      await page.getByLabel('Senha').fill(E2E_PASSWORD)
      await page.getByRole('button', { name: 'Entrar' }).click()

      // Espera o painel de fato abrir: o nome da pessoa na barra superior só aparece depois
      // de /auth/user responder, então isto confirma sessão válida, não só a troca de URL.
      await page.getByRole('link', { name: ROLE_USERS[role].name }).waitFor()

      await context.storageState({ path: storageStatePath(role) })
      await context.close()
    }
  } finally {
    await browser.close()
  }
}
