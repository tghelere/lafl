import { expect, test } from '@playwright/test'

import { gotoSite } from '../../support/site'

/**
 * Cobertura da tarefa 04 (docs/tarefas/04-marca-e-credito-softhing.md), Etapa 6: a logo
 * aparece com nome acessível no header do site e no login do painel; o link da Softhing
 * aparece, com `href` e `rel` corretos, na home, numa página do CMS e no login do painel.
 */

const SOFTHING_HREF_PATTERN = /^https:\/\/softhing\.com\.br\/\?utm_source=lar-analia-franco&utm_medium=referral&utm_campaign=credito-/

async function expectSofthingCredit(page: import('@playwright/test').Page, expectedCampaign: string): Promise<void> {
  const link = page.getByRole('link', { name: 'Softhing — abre o site da desenvolvedora em nova aba' })

  await expect(link).toBeVisible()
  await expect(link).toHaveAttribute('href', SOFTHING_HREF_PATTERN)
  await expect(link).toHaveAttribute('href', new RegExp(`utm_campaign=${expectedCampaign}$`))
  await expect(link).toHaveAttribute('target', '_blank')

  // rel="noopener" apenas — nunca noreferrer (esconderia a origem da visita no analytics da
  // Softhing) nem nofollow (ver docs/tarefas/04-marca-e-credito-softhing.md).
  const rel = await link.getAttribute('rel')

  expect(rel?.trim().split(/\s+/)).toEqual(['noopener'])
}

test.describe('site — marca e crédito', () => {
  test('logo do header tem nome acessível e link para a home', async ({ page }) => {
    await gotoSite(page, '/quem-somos/nossa-historia')

    const logoLink = page.getByRole('link', { name: 'Lar Anália Franco — página inicial' }).first()

    await expect(logoLink).toBeVisible()
    await expect(logoLink).toHaveAttribute('href', '/')
    await expect(logoLink.locator('img')).toBeVisible()
  })

  test('crédito da Softhing aparece na home com href e rel corretos', async ({ page }) => {
    await gotoSite(page, '/')

    await expectSofthingCredit(page, 'credito-site')
  })

  test('crédito da Softhing aparece numa página do CMS com href e rel corretos', async ({ page }) => {
    await gotoSite(page, '/quem-somos/nossa-historia')

    await expectSofthingCredit(page, 'credito-site')
  })
})

test.describe('painel — marca e crédito', () => {
  test.use({ storageState: { cookies: [], origins: [] } })

  test('login tem logo e título acessível "Painel administrativo"', async ({ page }) => {
    await page.goto('/login')

    await expect(page.getByRole('heading', { name: 'Painel administrativo' })).toBeVisible()
    await expect(page.getByRole('img', { name: 'Lar Anália Franco' })).toBeVisible()
  })

  test('crédito da Softhing aparece no login com href e rel corretos', async ({ page }) => {
    await page.goto('/login')

    await expectSofthingCredit(page, 'credito-painel')
  })
})
