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

  test('painel suspenso do header: texto do link alinha com o texto do rótulo do gatilho, com espaçamento entre itens', async ({
    page,
  }) => {
    await gotoSite(page, '/')

    const trigger = page.getByRole('button', { name: 'Quem somos' })
    await trigger.hover()

    const panel = page.locator('#panel-quem-somos')
    await expect(panel).toBeVisible()

    const labelBox = await trigger.locator('.site-header__label').boundingBox()
    const linkTextBoxes = await panel.locator('.site-header__panel-link span').all()
    const firstLinkTextBox = await linkTextBoxes[0]!.boundingBox()

    expectWithinTolerance(
      [labelBox!.x, firstLinkTextBox!.x],
      'início do texto do rótulo do gatilho vs. início do texto do primeiro link do painel',
    )

    const itemBoxes = await panel.locator('> li').all()
    expect(itemBoxes.length, 'painel de Quem somos: nenhum item encontrado').toBeGreaterThan(1)

    const rects = await Promise.all(itemBoxes.map((li) => li.boundingBox()))
    for (let i = 1; i < rects.length; i++) {
      const gap = rects[i]!.y - (rects[i - 1]!.y + rects[i - 1]!.height)

      expect(gap, `espaçamento entre os itens ${i - 1} e ${i} do painel de Quem somos`).toBeGreaterThan(4)
    }
  })

  /**
   * A variante --right (clamp de borda, §6 de docs/design/navegacao.md) nunca dispara com o
   * conteúdo atual da navegação em nenhuma largura de desktop real (1024px a 1920px
   * verificado) — os painéis são estreitos demais e os itens ficam longe o bastante da borda
   * direita. Força a classe via DOM para testar a fórmula da variante mesmo sem conseguir
   * reproduzir o clamp organicamente; é a mesma fórmula (borda + padding do <ul> + padding
   * do link) espelhada, ver comentário em components.css.
   */
  test('painel suspenso do header: variante --right (clamp de borda) também alinha a borda interna com o gatilho', async ({
    page,
  }) => {
    await gotoSite(page, '/')

    const trigger = page.getByRole('button', { name: 'Quem somos' })
    await trigger.hover()

    const panel = page.locator('#panel-quem-somos')
    await expect(panel).toBeVisible()
    await panel.evaluate((el) => el.classList.add('site-header__panel--right'))

    const item = page.locator('.site-header__item', { has: trigger })
    const itemBox = await item.boundingBox()
    const panelBox = await panel.boundingBox()

    // Borda direita interna do gatilho (caixa menos o padding-right de 12px) contra a borda
    // direita interna do painel (caixa menos borda de 1px + padding do <ul> de 8px + padding
    // do link de 12px = 21px).
    const triggerInnerRight = itemBox!.x + itemBox!.width - 12
    const panelInnerRight = panelBox!.x + panelBox!.width - 21

    expectWithinTolerance(
      [triggerInnerRight, panelInnerRight],
      'borda interna direita do gatilho vs. borda interna direita do painel (--right)',
    )
  })
})

test.describe('site — alinhamento (gaveta mobile, 375px)', () => {
  test('CTA Doar da gaveta mobile mantém o padding lateral e o texto centralizado do .btn', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 800 })
    await gotoSite(page, '/')

    await page.getByRole('button', { name: 'Menu' }).click()

    const cta = page.locator('.mobile-nav__cta')
    await expect(cta).toBeVisible()

    const [paddingLeft, paddingRight] = await cta.evaluate((el) => {
      const style = getComputedStyle(el)

      return [Number.parseFloat(style.paddingLeft), Number.parseFloat(style.paddingRight)]
    })

    // Antes da correção, `.mobile-nav a` (mais específico que `.btn`) zerava o padding lateral.
    expect(paddingLeft, 'padding lateral esquerdo do CTA Doar na gaveta').toBeGreaterThan(16)
    expectWithinTolerance([paddingLeft, paddingRight], 'padding lateral esquerdo vs. direito do CTA Doar na gaveta')

    const ctaBox = await cta.boundingBox()
    const textBox = await cta.evaluate((el) => {
      const range = document.createRange()
      range.selectNodeContents(el.lastChild!)
      const rect = range.getBoundingClientRect()

      return { left: rect.left, right: rect.right }
    })

    const leftGap = textBox.left - ctaBox!.x
    const rightGap = ctaBox!.x + ctaBox!.width - textBox.right

    // Antes da correção, `display: block` (de `.mobile-nav a`) vencia o `inline-flex` +
    // `justify-content: center` do `.btn`, empurrando o texto "Doar" para a esquerda.
    expectWithinTolerance([leftGap, rightGap], 'espaço à esquerda vs. à direita do texto "Doar" no CTA da gaveta')
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
