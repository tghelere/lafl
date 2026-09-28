import { type Page, expect, test } from '@playwright/test'

import { sidebar } from '../../support/admin'
import { ROLE_USERS, storageStatePath } from '../../support/users'

/**
 * O painel em tela estreita (ver frontend-admin/src/composables/useNavDrawer.ts e as media
 * queries de src/assets/css/components.css).
 *
 * A bateria roda em Firefox de verdade porque é a única forma de provar o que aqui importa:
 * que a gaveta some da ordem de tabulação quando fechada, que o foco não escapa dela quando
 * aberta e que a página não ganha rolagem horizontal. Nada disso aparece num teste de
 * componente — e a media query nem sequer existe lá.
 */
test.use({ storageState: storageStatePath('super_admin') })

/** Telas estreitas usadas na bateria: um celular pequeno e um celular comum. */
const CELULAR = { width: 390, height: 844 }

function menuButton(page: Page) {
  return page.getByRole('button', { name: 'Menu', exact: true })
}

async function abrirGaveta(page: Page): Promise<void> {
  await menuButton(page).click()
  await expect(sidebar(page)).toBeVisible()
}

test.describe('gaveta de navegação abaixo de 1024px', () => {
  test.use({ viewport: CELULAR })

  test('a navegação começa fora da tela e abre pelo botão da barra superior', async ({ page }) => {
    await page.goto('/admin')
    await expect(page.getByRole('heading', { name: 'Início' })).toBeVisible()

    // `visibility: hidden` na gaveta fechada, e não só um deslocamento: fosse só transform, o
    // Tab continuaria andando pelos links do menu invisível.
    await expect(sidebar(page)).toBeHidden()
    await expect(menuButton(page)).toBeVisible()
    await expect(menuButton(page)).toHaveAttribute('aria-expanded', 'false')

    await abrirGaveta(page)

    await expect(menuButton(page)).toHaveAttribute('aria-expanded', 'true')
    await expect(sidebar(page).getByRole('link', { name: /Páginas/ })).toBeVisible()
  })

  test('a barra superior continua com o usuário e o Sair', async ({ page }) => {
    await page.goto('/admin')

    await expect(page.getByRole('link', { name: ROLE_USERS.super_admin.name })).toBeVisible()
    await expect(page.getByRole('button', { name: 'Sair', exact: true })).toBeVisible()
  })

  test('a gaveta fecha ao navegar', async ({ page }) => {
    await page.goto('/admin')
    await abrirGaveta(page)

    await sidebar(page).getByRole('link', { name: /Usuários/ }).click()

    await expect(page).toHaveURL(/\/admin\/usuarios$/)
    await expect(sidebar(page)).toBeHidden()
  })

  test('a gaveta fecha ao clicar fora', async ({ page }) => {
    await page.goto('/admin')
    await abrirGaveta(page)

    // O cortinado, e não um ponto qualquer da tela: é ele que cobre o conteúdo e recebe o
    // clique de fora — clicar "no conteúdo" atrás dele é impossível de propósito.
    await page.locator('.app-scrim').click({ position: { x: 340, y: 600 } })

    await expect(sidebar(page)).toBeHidden()
    await expect(page).toHaveURL(/\/admin$/)
  })

  test('Esc fecha a gaveta e devolve o foco ao botão que a abriu', async ({ page }) => {
    await page.goto('/admin')
    await abrirGaveta(page)

    await page.keyboard.press('Escape')

    await expect(sidebar(page)).toBeHidden()
    await expect(menuButton(page)).toBeFocused()
  })

  test('o foco não sai da gaveta enquanto ela está aberta', async ({ page }) => {
    await page.goto('/admin')
    await abrirGaveta(page)

    // Ida e volta: 25 tabulações cobrem com folga os itens do menu de um super_admin, então o
    // laço passa pelo fim da lista mais de uma vez — é exatamente ali que um foco sem prisão
    // escaparia para o conteúdo atrás do cortinado.
    const escaparam: string[] = []

    for (const tecla of ['Tab', 'Shift+Tab']) {
      for (let i = 0; i < 25; i++) {
        await page.keyboard.press(tecla)

        const dentro = await page.evaluate(
          () => document.querySelector('.app-sidebar')?.contains(document.activeElement) ?? false,
        )

        if (!dentro) {
          escaparam.push(
            `${tecla} #${i}: ${await page.evaluate(() => document.activeElement?.textContent?.trim().slice(0, 40) ?? '')}`,
          )
        }
      }
    }

    expect(escaparam, 'o foco saiu da gaveta aberta').toEqual([])
  })

  test('a gaveta aberta trava a rolagem do conteúdo de trás', async ({ page }) => {
    await page.goto('/admin')
    await abrirGaveta(page)

    await expect
      .poll(() => page.evaluate(() => getComputedStyle(document.body).overflow))
      .toBe('hidden')

    await page.keyboard.press('Escape')

    await expect.poll(() => page.evaluate(() => getComputedStyle(document.body).overflow)).not.toBe('hidden')
  })
})

test.describe('a partir de 1024px a navegação volta a ser coluna fixa', () => {
  test.use({ viewport: { width: 1024, height: 900 } })

  test('não há botão Menu, e o menu está sempre visível', async ({ page }) => {
    await page.goto('/admin')
    await expect(page.getByRole('heading', { name: 'Início' })).toBeVisible()

    await expect(sidebar(page)).toBeVisible()
    await expect(menuButton(page)).toHaveCount(0)
    await expect(page.locator('.app-scrim')).toHaveCount(0)

    const posicao = await page.locator('.app-sidebar').evaluate((el) => getComputedStyle(el).position)
    expect(posicao, 'a navegação lateral não deveria estar fora do fluxo em 1024px').toBe('static')
  })
})
