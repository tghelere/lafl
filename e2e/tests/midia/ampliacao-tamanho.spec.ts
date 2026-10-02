import { type Page, expect, test } from '@playwright/test'

import { unique } from '../../support/admin'
import { AdminApi } from '../../support/api'
import { type PageWithPhotos, createPageWithPhotos, removePageWithPhotos } from '../../support/fixtures'
import { gotoSite } from '../../support/site'

/**
 * A imagem aberta nunca é menor do que aparecia na página (sessão 36).
 *
 * O caso que motivou: duas imagens grandes numa página (como /transparencia), e a ampliação
 * mostrava cada uma menor, espremida no meio da tela. A página tem duas fotos de galeria em
 * duas colunas largas — uma paisagem e uma retrato, que é a que mais sofre com o limite de
 * altura — e uma figura no texto, que é a imagem que vem do editor do painel.
 */
const suffix = unique('e2e')
const slug = `ampliacao-tamanho-${suffix}`.toLowerCase()
const galeria = [
  { alt: `Paisagem larga ${suffix}`, width: 1600, height: 1000, color: [200, 60, 60] as [number, number, number] },
  { alt: `Retrato alto ${suffix}`, width: 1000, height: 1400, color: [60, 160, 60] as [number, number, number] },
]
// Arquivo pequeno: menor que a largura em que aparece na página em tela larga.
const noTexto = { alt: `Figura do texto ${suffix}`, caption: 'Legenda da figura', width: 700, height: 500, color: [180, 140, 40] as [number, number, number] }

let api: AdminApi
let criada: PageWithPhotos

test.beforeAll(async () => {
  api = await AdminApi.as('direcao')
  criada = await createPageWithPhotos(api, { slug, title: `Página ${suffix}`, gallery: galeria, inText: noTexto })
})

test.afterAll(async () => {
  await removePageWithPhotos(api, criada)
  await api.dispose()
})

const alvos = [galeria[0].alt, galeria[1].alt, noTexto.alt]

for (const viewport of [
  { width: 1920, height: 1080 },
  { width: 1280, height: 800 },
  { width: 375, height: 667 },
]) {
  test.describe(`${viewport.width}px`, () => {
    test.use({ viewport })

    for (const alt of alvos) {
      test(`"${alt.replace(` ${suffix}`, '')}" aberta não é menor que na página`, async ({ page }) => {
        await gotoSite(page, `/${slug}`)

        const link = page.getByRole('link', { name: `Ampliar imagem: ${alt}` })
        await link.scrollIntoViewIfNeeded()
        await expect.poll(() => larguraRenderizada(link.locator('img'))).toBeGreaterThan(0)
        const naPagina = await larguraRenderizada(link.locator('img'))

        await link.click()

        const dialogo = page.getByRole('dialog', { name: 'Imagem ampliada' })
        const aberta = dialogo.locator('.ampliacao__imagem')
        await expect(aberta).toBeVisible()
        await expect.poll(() => aberta.evaluate((n) => (n as HTMLImageElement).complete && (n as HTMLImageElement).naturalWidth > 0)).toBe(true)

        // A regra: largura renderizada aberta >= largura renderizada na página.
        const noDialogo = await larguraRenderizada(aberta)
        expect(noDialogo, `aberta ${noDialogo}px, na página ${naPagina}px`).toBeGreaterThanOrEqual(naPagina - 0.5)

        // Sem moldura grande: o diálogo é a tela inteira e a imagem não passa da largura dele.
        const caixa = (await dialogo.boundingBox())!
        expect(caixa).toEqual({ x: 0, y: 0, width: viewport.width, height: viewport.height })
        const imagem = (await aberta.boundingBox())!
        expect(imagem.x).toBeGreaterThanOrEqual(0)
        expect(imagem.x + imagem.width).toBeLessThanOrEqual(viewport.width + 0.5)
        expect(await dialogo.evaluate((n) => n.scrollWidth <= n.clientWidth)).toBe(true)

        // Mantém a proporção: nada de imagem esticada ou cortada.
        const natural = await aberta.evaluate((n) => ({ w: (n as HTMLImageElement).naturalWidth, h: (n as HTMLImageElement).naturalHeight }))
        expect(imagem.width / imagem.height).toBeCloseTo(natural.w / natural.h, 1)

        // Esc fecha e o foco volta ao link da imagem clicada.
        await page.keyboard.press('Escape')
        await expect(dialogo).toBeHidden()
        await expect(link).toBeFocused()
      })
    }
  })
}

test.describe('1280px — ocupa a tela quando cabe mais', () => {
  test.use({ viewport: { width: 1280, height: 800 } })

  test('a paisagem cresce até a largura da tela, sem cortar', async ({ page }) => {
    await gotoSite(page, `/${slug}`)
    await page.getByRole('link', { name: `Ampliar imagem: ${galeria[0].alt}` }).click()

    const aberta = page.getByRole('dialog', { name: 'Imagem ampliada' }).locator('.ampliacao__imagem')
    await expect(aberta).toBeVisible()
    const palco = (await page.locator('.ampliacao__palco').boundingBox())!
    const imagem = (await aberta.boundingBox())!

    // Contida no palco (nada cortado) e encostada em um dos dois limites.
    expect(imagem.width).toBeLessThanOrEqual(palco.width + 0.5)
    expect(imagem.height).toBeLessThanOrEqual(palco.height + 0.5)
    expect(Math.min(palco.width - imagem.width, palco.height - imagem.height)).toBeLessThan(2)
  })

  test('clicar fora e o botão Fechar também fecham, com o foco na imagem', async ({ page }) => {
    await gotoSite(page, `/${slug}`)
    const link = page.getByRole('link', { name: `Ampliar imagem: ${galeria[0].alt}` })
    const dialogo = page.getByRole('dialog', { name: 'Imagem ampliada' })

    await link.click()
    await expect(dialogo).toBeVisible()
    await dialogo.getByRole('button', { name: 'Fechar' }).click()
    await expect(dialogo).toBeHidden()
    await expect(link).toBeFocused()

    await link.click()
    await expect(dialogo).toBeVisible()
    await page.mouse.click(4, 400)
    await expect(dialogo).toBeHidden()
    await expect(link).toBeFocused()
  })
})

async function larguraRenderizada(imagem: ReturnType<Page['locator']>): Promise<number> {
  return imagem.evaluate((n) => n.getBoundingClientRect().width)
}
