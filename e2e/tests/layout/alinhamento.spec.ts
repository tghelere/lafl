import { type Page, expect, test } from '@playwright/test'

import { gotoSite } from '../../support/site'
import { storageStatePath } from '../../support/users'

/**
 * `li + li` global (base.css do site) e alturas de controle sem token (`.btn`, input, select
 * do painel) já derrubaram o centro vertical do menu do header, do breadcrumb e a altura dos
 * campos ao lado de botão — ver docs/tarefas/03-alinhamento-visual.md. Tolerância de 1px: é
 * arredondamento de subpixel entre navegador e SO, não o defeito que este arquivo existe para
 * pegar (esse é sempre maior — 4px a 12px nos casos encontrados).
 */
const TOLERANCE_PX = 1

async function verticalCenters(page: Page, selector: string): Promise<number[]> {
  return page.$$eval(selector, (elements) =>
    elements.map((element) => {
      const rect = element.getBoundingClientRect()

      return rect.top + rect.height / 2
    }),
  )
}

async function heights(page: Page, selector: string): Promise<number[]> {
  return page.$$eval(selector, (elements) => elements.map((element) => element.getBoundingClientRect().height))
}

function expectWithinTolerance(values: number[], label: string): void {
  expect(values.length, `${label}: nenhum elemento encontrado`).toBeGreaterThan(1)

  const min = Math.min(...values)
  const max = Math.max(...values)

  expect(max - min, `${label}: ${JSON.stringify(values)}`).toBeLessThanOrEqual(TOLERANCE_PX)
}

test.describe('site — alinhamento', () => {
  test('itens do menu do header ficam no mesmo centro vertical', async ({ page }) => {
    await gotoSite(page, '/')
    await expect(page.locator('.site-header__nav > ul > li').first()).toBeVisible()

    expectWithinTolerance(
      await verticalCenters(page, '.site-header__nav > ul > li'),
      'centro vertical dos itens do menu do header',
    )
  })

  test('itens do breadcrumb e da navegação de seção ficam no mesmo centro vertical', async ({ page }) => {
    await gotoSite(page, '/quem-somos/nossa-historia')
    await expect(page.locator('.breadcrumb li').first()).toBeVisible()

    expectWithinTolerance(await verticalCenters(page, '.breadcrumb li'), 'centro vertical dos itens do breadcrumb')
    expectWithinTolerance(
      await verticalCenters(page, '.section-nav li'),
      'centro vertical dos itens da navegação de seção',
    )
  })

  test('barra de filtro de /transparencia/documentos: input, select e botão têm a mesma altura', async ({ page }) => {
    await gotoSite(page, '/transparencia/documentos')
    await expect(page.locator('.doc-filter .btn')).toBeVisible()

    expectWithinTolerance(
      await heights(page, '.doc-filter input, .doc-filter select, .doc-filter .btn'),
      'altura dos controles da barra de filtro de documentos',
    )
  })
})

test.describe('painel — alinhamento', () => {
  test.use({ storageState: storageStatePath('super_admin') })

  test('barra de filtro de usuários: input, select e botões têm a mesma altura', async ({ page }) => {
    await page.goto('/admin/usuarios')
    await expect(page.locator('.filter-bar .btn').first()).toBeVisible()

    expectWithinTolerance(
      await heights(page, '.filter-bar input, .filter-bar select, .filter-bar .btn'),
      'altura dos controles da barra de filtro de usuários',
    )
  })

  test('barra de filtro de transparência: input, select e botões têm a mesma altura', async ({ page }) => {
    await page.goto('/admin/transparencia')
    await expect(page.locator('.filter-bar .btn').first()).toBeVisible()

    expectWithinTolerance(
      await heights(page, '.filter-bar input, .filter-bar select, .filter-bar .btn'),
      'altura dos controles da barra de filtro de transparência',
    )
  })
})
