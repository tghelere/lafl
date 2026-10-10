import { expect, test, type Page } from '@playwright/test'

import { bankTransfer, pix, pixKeyFormatted } from '../../../frontend-site/app/config/institution'
import { gotoSite, serverHtml } from '../../support/site'

/** Troca navigator.clipboard.writeText por um registro — Firefox não permite ler a área real. */
async function captureClipboard(page: Page): Promise<void> {
  await page.addInitScript(() => {
    ;(window as unknown as { __copied: string[] }).__copied = []
    Object.defineProperty(navigator, 'clipboard', {
      configurable: true,
      value: { writeText: async (text: string) => (window as unknown as { __copied: string[] }).__copied.push(text) },
    })
  })
}

const copied = (page: Page) => page.evaluate(() => (window as unknown as { __copied: string[] }).__copied)

test.describe('/doar — formas de doação', () => {
  test('o QR Code já vem desenhado em SVG no HTML do servidor', async () => {
    const html = await serverHtml('/doar')

    expect(html).toContain('data-testid="doe-por-pix"')
    expect(html).toMatch(/<svg[^>]*aria-label="QR Code do Pix[^"]*"[\s\S]*?<path fill="#000000"/)
    expect(html).toContain('fill="#ffffff"')
  })

  test('ordem: frase de abertura, cartões, frase do bazar; marcador não vaza', async () => {
    const html = await serverHtml('/doar')

    const abertura = html.indexOf('não processa doações em dinheiro')
    const cartoes = html.indexOf('data-testid="formas-de-doacao"')
    const bazar = html.indexOf('Doações de itens para o Bazar')

    expect(html.indexOf('<h1')).toBeLessThan(abertura)
    expect(abertura).toBeGreaterThan(-1)
    expect(abertura).toBeLessThan(cartoes)
    expect(cartoes).toBeLessThan(bazar)
    // O payload do Nuxt (<script>) carrega o conteúdo cru; o que importa é o corpo visível.
    expect(html.replace(/<script[\s\S]*?<\/script>/g, '')).not.toContain('formas_de_doacao')
  })

  test('a chave Pix aparece uma única vez; o CNPJ só repete na linha de transferência', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 })
    await gotoSite(page, '/doar')

    await expect(page.getByTestId('pix-chave')).toHaveCount(1)
    await expect(page.getByTestId('pix-chave')).toHaveText(pixKeyFormatted())
    // Uma vez no cartão Pix, uma no de transferência — e nenhuma no texto do CMS.
    for (const bloco of await page.locator('.page-content').all()) {
      await expect(bloco).not.toContainText('78.614.096')
    }
    expect(((await page.locator('main').textContent()) ?? '').split(pixKeyFormatted()).length - 1).toBe(2)
  })

  test('desktop: cartões lado a lado, mesma altura, títulos e rodapés alinhados', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 })
    await gotoSite(page, '/doar')

    const pixCard = page.getByTestId('doe-por-pix')
    const bankCard = page.getByTestId('doe-por-transferencia')
    const [a, b] = [(await pixCard.boundingBox())!, (await bankCard.boundingBox())!]

    expect(a.x).toBeLessThan(b.x)
    expect(Math.abs(a.y - b.y)).toBeLessThanOrEqual(1)
    expect(Math.abs(a.height - b.height)).toBeLessThanOrEqual(1)

    const y = async (card: typeof pixCard, selector: string) => (await card.locator(selector).boundingBox())!.y
    expect(Math.abs((await y(pixCard, 'h2')) - (await y(bankCard, 'h2')))).toBeLessThanOrEqual(1)
    expect(Math.abs((await y(pixCard, '.donate__footer')) - (await y(bankCard, '.donate__footer')))).toBeLessThanOrEqual(1)

    await expect(pixCard.locator('.donate__footer')).toHaveText('Recebedor: Lar Anália Franco de Londrina')
    await expect(bankCard.locator('.donate__footer')).toHaveText('Favorecido: Lar Anália Franco de Londrina')
    await expect(pixCard.locator('.pix__qr--wide svg')).toBeVisible()
    await expect(pixCard.getByTestId('pix-codigo')).toBeHidden()
  })

  test('cada botão copia o valor certo, com rótulo temporário e aria-live', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 })
    await captureClipboard(page)
    await gotoSite(page, '/doar')

    const status = page.getByTestId('formas-de-doacao').getByRole('status')
    const casos: [string, string, string][] = [
      ['copiar-codigo', pix.payload, 'Código copiado'],
      ['copiar-chave', pix.key, 'Chave copiada'],
      ['copiar-agency', bankTransfer.agency, 'Agência copiada'],
      ['copiar-account', bankTransfer.account, 'Conta copiada'],
      ['copiar-cnpj', bankTransfer.cnpj, 'CNPJ copiado'],
    ]
    const esperado: string[] = []

    for (const [id, valor, mensagem] of casos) {
      await page.getByTestId(id).click()
      esperado.push(valor)
      await expect(status).toHaveText(mensagem)
      expect(await copied(page)).toEqual(esperado)
    }

    // O rótulo volta sozinho (~2 s).
    await expect(page.getByTestId('copiar-codigo')).toHaveText('Copiar código Pix', { timeout: 5_000 })
    // Banco não tem botão de copiar.
    await expect(page.getByTestId('doe-por-transferencia').getByRole('button')).toHaveCount(3)
    await expect(page.getByTestId('transferencia-banco')).toHaveText('Banco do Brasil (001)')
  })

  test('sem clipboard, o código fica selecionado para cópia manual', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 })
    await page.addInitScript(() => Object.defineProperty(navigator, 'clipboard', { configurable: true, value: undefined }))
    await gotoSite(page, '/doar')

    await page.getByTestId('copiar-codigo').click()

    const field = page.getByLabel('Código Pix copia e cola')

    await expect(field).toBeFocused()
    expect(await field.evaluate((el: HTMLInputElement) => el.value.slice(el.selectionStart!, el.selectionEnd!))).toBe(
      pix.payload,
    )
  })

  test('celular: cartões empilhados com Pix primeiro, QR recolhido, botão em largura total', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 800 })
    await captureClipboard(page)
    await gotoSite(page, '/doar')

    const pixCard = page.getByTestId('doe-por-pix')
    const bankCard = page.getByTestId('doe-por-transferencia')
    const [a, b] = [(await pixCard.boundingBox())!, (await bankCard.boundingBox())!]

    expect(a.y + a.height).toBeLessThanOrEqual(b.y)
    expect(Math.abs(a.x - b.x)).toBeLessThanOrEqual(1)

    const details = pixCard.locator('details.pix__qr')

    await expect(details).toBeVisible()
    expect(await details.evaluate((el: HTMLDetailsElement) => el.open)).toBe(false)
    await expect(pixCard.locator('.pix__qr--wide').first()).toBeHidden()

    const button = (await pixCard.getByTestId('copiar-codigo').boundingBox())!
    const inner = a.width - 2 * 24 - 2
    expect(Math.abs(button.width - inner)).toBeLessThanOrEqual(1)

    await details.locator('summary').click()
    await expect(details.locator('svg')).toBeVisible()

    await pixCard.getByTestId('copiar-codigo').click()
    expect(await copied(page)).toEqual([pix.payload])

    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true)
  })
})
