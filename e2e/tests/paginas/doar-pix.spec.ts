import { expect, test } from '@playwright/test'

import { pix, pixKeyFormatted } from '../../../frontend-site/app/config/institution'
import { gotoSite, serverHtml, serverPageContent } from '../../support/site'

/** Troca navigator.clipboard.writeText por um registro — Firefox não permite ler a área real. */
async function captureClipboard(page: import('@playwright/test').Page): Promise<void> {
  await page.addInitScript(() => {
    ;(window as unknown as { __copied: string[] }).__copied = []
    Object.defineProperty(navigator, 'clipboard', {
      configurable: true,
      value: { writeText: async (text: string) => (window as unknown as { __copied: string[] }).__copied.push(text) },
    })
  })
}

const copied = (page: import('@playwright/test').Page) =>
  page.evaluate(() => (window as unknown as { __copied: string[] }).__copied)

test.describe('/doar — Doe por Pix', () => {
  test('o QR Code já vem desenhado em SVG no HTML do servidor', async () => {
    const html = await serverHtml('/doar')

    expect(html).toContain('data-testid="doe-por-pix"')
    expect(html).toMatch(/<svg[^>]*aria-label="QR Code do Pix[^"]*"[\s\S]*?<path fill="#000000"/)
    expect(html).toContain('fill="#ffffff"')
  })

  test('o card vem logo abaixo do título, antes do texto do CMS, que não repete a chave', async () => {
    const html = await serverHtml('/doar')

    expect(html.indexOf('<h1')).toBeLessThan(html.indexOf('data-testid="doe-por-pix"'))
    expect(html.indexOf('data-testid="doe-por-pix"')).toBeLessThan(html.indexOf('class="page-content"'))

    const text = await serverPageContent('/doar')

    expect(text).not.toContain('78.614.096')
    expect(text).toContain('não recebe nem guarda dados de pagamento')
  })

  test('desktop: QR visível e os botões copiam o código e a chave certos', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 })
    await captureClipboard(page)
    await gotoSite(page, '/doar')

    const section = page.getByTestId('doe-por-pix')

    await expect(section.getByRole('heading', { name: 'Doe por Pix' })).toBeVisible()
    await expect(section.locator('.pix__qr--wide svg')).toBeVisible()
    await expect(section.getByTestId('pix-chave')).toHaveText(pixKeyFormatted())

    await section.getByRole('button', { name: 'Copiar código' }).click()
    await expect(section.getByRole('button', { name: 'Código copiado' })).toBeVisible()
    await expect(section.getByRole('status')).toHaveText('Código copiado')
    expect(await copied(page)).toEqual([pix.payload])

    await section.getByRole('button', { name: 'Copiar chave' }).click()
    await expect(section.getByRole('button', { name: 'Chave copiada' })).toBeVisible()
    expect(await copied(page)).toEqual([pix.payload, pix.key])

    // O rótulo volta sozinho (~2 s).
    await expect(section.getByRole('button', { name: 'Copiar código' })).toBeVisible({ timeout: 5_000 })
  })

  test('sem clipboard, o código fica selecionado para cópia manual', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 })
    await page.addInitScript(() => Object.defineProperty(navigator, 'clipboard', { configurable: true, value: undefined }))
    await gotoSite(page, '/doar')

    await page.getByRole('button', { name: 'Copiar código' }).click()

    const field = page.getByLabel('Código Pix copia e cola')

    await expect(field).toBeFocused()
    expect(await field.evaluate((el: HTMLInputElement) => el.value.slice(el.selectionStart!, el.selectionEnd!))).toBe(
      pix.payload,
    )
  })

  test('celular: QR recolhido e botão de copiar código vem antes da chave', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 800 })
    await captureClipboard(page)
    await gotoSite(page, '/doar')

    const section = page.getByTestId('doe-por-pix')
    const details = section.locator('details.pix__qr')

    await expect(details).toBeVisible()
    expect(await details.evaluate((el: HTMLDetailsElement) => el.open)).toBe(false)
    await expect(section.locator('.pix__qr--wide')).toBeHidden()

    const code = await section.getByRole('button', { name: 'Copiar código Pix' }).boundingBox()
    const key = await section.getByRole('button', { name: 'Copiar', exact: true }).boundingBox()

    expect(code!.y).toBeLessThan(key!.y)

    // Largura total: ocupa o interior do cartão (descontados borda e padding).
    const card = await section.boundingBox()
    const inner = card!.width - 2 * 24 - 2
    expect(Math.abs(code!.width - inner)).toBeLessThanOrEqual(1)

    await details.locator('summary').click()
    await expect(details.locator('svg')).toBeVisible()

    await section.getByRole('button', { name: 'Copiar código Pix' }).click()
    expect(await copied(page)).toEqual([pix.payload])

    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true)
  })
})
