import { expect, test } from '@playwright/test'

import { acceptNextDialog, unique } from '../../support/admin'
import { solidPng } from '../../support/fixtures'
import { gotoSite } from '../../support/site'
import { storageStatePath } from '../../support/users'

const GRADE = 'section[aria-label="Nossos parceiros"]'

/**
 * Cadastro de parceiros: criar com link e sem link no painel, ver os dois na página de
 * parceiros do site, e a grade sumir quando não sobra nenhum.
 *
 * Um teste só, com o fluxo inteiro, de propósito: o banco de e2e nasce sem parceiros e os dois
 * cartões dependem um do outro para a conferência de tamanho igual.
 */
test.describe('parceiros', () => {
  test.use({ storageState: storageStatePath('comunicacao') })

  test('cria com link e sem link, os dois aparecem na página, e sem parceiros a grade some', async ({ page }) => {
    const comLink = `Padaria ${unique('com-link')}`
    const semLink = `Mercado ${unique('sem-link')}`

    // Sem parceiro nenhum: o texto editável aparece e a grade, nem título nem mensagem, não.
    await gotoSite(page, '/como-ajudar/parceiros')
    await expect(page.getByRole('heading', { level: 1, name: 'Parceiros' })).toBeVisible()
    await expect(page.locator(GRADE)).toHaveCount(0)

    // Com link — logo larga.
    await page.goto('/admin/parceiros/novo')
    await page.getByLabel('Nome').fill(comLink)
    await page.getByLabel('Link (opcional)').fill('https://padaria.example.com.br/')
    await page.getByLabel('Logo (PNG, JPG ou WebP, até 10 MB)').setInputFiles({
      name: 'logo-larga.png',
      mimeType: 'image/png',
      buffer: solidPng(600, 200, [200, 60, 20]),
    })
    await page.getByRole('button', { name: 'Salvar' }).click()
    await expect(page.getByText('Parceiro cadastrado.')).toBeVisible()

    // Sem link — logo quadrada, para provar que a caixa tem proporção fixa.
    await page.goto('/admin/parceiros/novo')
    await page.getByLabel('Nome').fill(semLink)
    await page.getByLabel('Logo (PNG, JPG ou WebP, até 10 MB)').setInputFiles({
      name: 'logo-quadrada.png',
      mimeType: 'image/png',
      buffer: solidPng(300, 300, [20, 60, 200]),
    })
    await page.getByRole('button', { name: 'Salvar' }).click()
    await expect(page.getByText('Parceiro cadastrado.')).toBeVisible()

    await expect(page.getByRole('link', { name: comLink })).toBeVisible()
    await expect(page.getByRole('link', { name: semLink })).toBeVisible()

    // No site: os dois, na ordem do cadastro.
    await gotoSite(page, '/como-ajudar/parceiros')
    const grade = page.locator(GRADE)
    await expect(grade.locator('li')).toHaveCount(2)
    await expect(grade.locator('li').nth(0)).toContainText(comLink)
    await expect(grade.locator('li').nth(1)).toContainText(semLink)

    // Cartão com link: o cartão inteiro é o link, em nova aba, com noopener.
    const cartaoComLink = grade.locator('li').nth(0).locator('a')
    await expect(cartaoComLink).toHaveAttribute('href', 'https://padaria.example.com.br/')
    await expect(cartaoComLink).toHaveAttribute('target', '_blank')
    await expect(cartaoComLink).toHaveAttribute('rel', 'noopener')

    // Cartão sem link: não é link, nem tem aparência de clicável.
    const cartaoSemLink = grade.locator('li').nth(1)
    await expect(cartaoSemLink.locator('a')).toHaveCount(0)
    await expect(cartaoSemLink.locator('.parceiros__cartao')).toHaveCSS('cursor', 'auto')

    // Logo: alt é o nome, inteira visível (contain), carregada e com width/height no HTML.
    const logo = grade.locator('li').nth(1).locator('img')
    await expect(logo).toHaveAttribute('alt', semLink)
    await expect(logo).toHaveAttribute('width', '300')
    await expect(logo).toHaveAttribute('height', '300')
    await expect(logo).toHaveCSS('object-fit', 'contain')
    await expect.poll(() => logo.evaluate((img: HTMLImageElement) => img.complete && img.naturalWidth > 0)).toBe(true)

    // Cartões de tamanho igual, mesmo com logos de proporção diferente.
    const [primeiro, segundo] = await Promise.all([
      grade.locator('li').nth(0).locator('.parceiros__logo').boundingBox(),
      grade.locator('li').nth(1).locator('.parceiros__logo').boundingBox(),
    ])
    expect(primeiro!.width).toBeCloseTo(segundo!.width, 0)
    expect(primeiro!.height).toBeCloseTo(segundo!.height, 0)

    // O foco do teclado chega ao cartão com link e fica visível.
    await cartaoComLink.focus()
    await expect(cartaoComLink).toBeFocused()
    expect(await cartaoComLink.evaluate((el) => getComputedStyle(el).outlineStyle)).not.toBe('none')

    // Excluir os dois: a grade some de novo, sem nenhuma mensagem de lista vazia.
    for (const nome of [comLink, semLink]) {
      await page.goto('/admin/parceiros')
      await page.getByRole('link', { name: nome }).click()
      const aceitou = acceptNextDialog(page)
      await page.getByRole('button', { name: 'Excluir' }).click()
      await aceitou
      await expect(page.getByText('Parceiro excluído.')).toBeVisible()
    }

    await gotoSite(page, '/como-ajudar/parceiros')
    await expect(page.getByRole('heading', { level: 1, name: 'Parceiros' })).toBeVisible()
    await expect(page.locator(GRADE)).toHaveCount(0)
  })

  test('link inválido é recusado pela API e mostrado no campo', async ({ page }) => {
    await page.goto('/admin/parceiros/novo')
    await page.getByLabel('Nome').fill(`Invalido ${unique('url')}`)
    await page.getByLabel('Link (opcional)').fill('javascript:alert(1)')
    await page.getByLabel('Logo (PNG, JPG ou WebP, até 10 MB)').setInputFiles({
      name: 'logo.png',
      mimeType: 'image/png',
      buffer: solidPng(200, 100, [10, 10, 10]),
    })
    await page.getByRole('button', { name: 'Salvar' }).click()

    await expect(page.getByText('Informe um endereço válido, começando com http:// ou https://.')).toBeVisible()
  })
})
