import { expect, test } from '@playwright/test'

import { sidebar } from '../../support/admin'
import { storageStatePath } from '../../support/users'

/**
 * Dois caminhos diferentes até a mesma tela (ver AppLayout.vue e NotFoundState.vue): uma URL
 * que não bate com nenhuma rota do vue-router (coringa) e um `:resource` que bate com
 * `/admin/:resource` mas não existe no mapa de acesso devolvido por /auth/user (`unmapped`).
 * Antes desta tela existir, o primeiro caso renderizava em branco (sem rota coringa) e o
 * segundo tinha texto próprio, diferente do coringa — ver docs/tarefas/05-correcoes-de-codigo.md.
 */

test.use({ storageState: storageStatePath('super_admin') })

test('URL que não bate com nenhuma rota mostra a tela de não encontrada, dentro do layout', async ({ page }) => {
  await page.goto('/rota-que-nao-existe')

  await expect(page.getByRole('heading', { name: 'Página não encontrada' })).toBeVisible()

  // Dentro do layout: sidebar continua ali, igual ao acesso negado (ver
  // acesso-por-papel.spec.ts) — nunca redireciona.
  await expect(sidebar(page)).toBeVisible()
  await expect(page).toHaveURL(/\/rota-que-nao-existe$/)

  await page.getByRole('link', { name: 'Voltar ao Início' }).click()
  await expect(page).toHaveURL(/\/admin$/)
})

test('recurso inexistente em /admin/:resource cai na mesma tela', async ({ page }) => {
  await page.goto('/admin/recurso-que-nao-existe')

  await expect(page.getByRole('heading', { name: 'Página não encontrada' })).toBeVisible()
  await expect(sidebar(page)).toBeVisible()
})
