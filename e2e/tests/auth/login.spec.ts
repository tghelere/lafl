import { expect, test } from '@playwright/test'

import { loginThroughForm } from '../../support/admin'
import { STANDALONE_USERS } from '../../support/users'

/**
 * Contexto limpo, sem o storageState do global setup: estes três testes são sobre a própria
 * tela de login, então entrar já autenticado não testaria nada. Cada um usa uma conta
 * exclusiva (ver E2eSeeder) — assim nenhum deles gasta tentativa de login de outro no
 * limitador por IP+e-mail.
 */
test.use({ storageState: { cookies: [], origins: [] } })

test('login com a senha certa abre o painel', async ({ page }) => {
  await loginThroughForm(page, STANDALONE_USERS.loginValido.email)

  await expect(page).toHaveURL(/\/admin$/)
  await expect(page.getByRole('heading', { name: 'Início' })).toBeVisible()
  await expect(page.getByRole('link', { name: STANDALONE_USERS.loginValido.name })).toBeVisible()
})

test('senha errada mostra a mensagem genérica, sem dizer se o e-mail existe', async ({ page }) => {
  await loginThroughForm(page, STANDALONE_USERS.loginSenhaErrada.email, 'senha-obviamente-errada')

  // Texto vindo da API (App\Actions\Auth\LoginUser), não decidido pelo painel — e genérico de
  // propósito: senha errada não pode revelar se a conta existe ou está desativada.
  await expect(page.getByRole('alert')).toHaveText('Credenciais inválidas.')
  await expect(page).toHaveURL(/\/login$/)
})

test('conta desativada mostra a mensagem específica, e só depois da senha certa', async ({ page }) => {
  await loginThroughForm(page, STANDALONE_USERS.loginDesativado.email)

  await expect(page.getByRole('alert')).toHaveText('Esta conta foi desativada. Fale com a direção.')
  await expect(page).toHaveURL(/\/login$/)
})
