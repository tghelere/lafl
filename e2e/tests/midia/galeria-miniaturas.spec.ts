import { expect, test } from '@playwright/test'

import { unique } from '../../support/admin'
import { AdminApi } from '../../support/api'
import { type PageWithPhotos, createPageWithPhotos, removePageWithPhotos } from '../../support/fixtures'
import { gotoSite } from '../../support/site'

/**
 * Miniaturas da galeria: todas na mesma proporção (4:3) e no mesmo tamanho, seja a foto em
 * retrato, paisagem ou quadrada. A ampliação, ao contrário, mostra a foto inteira na proporção
 * real. Pilha real: o recorte é CSS sobre as derivadas que a API serve, e a ampliação lê a
 * proporção real do link.
 */

const suffix = unique('e2e')
const slug = `miniaturas-${suffix}`.toLowerCase()
const galeria = [
  { alt: `Paisagem larga ${suffix}`, width: 1600, height: 900, color: [200, 60, 60] as [number, number, number] },
  { alt: `Retrato alto ${suffix}`, caption: 'Foto em pé, com legenda', width: 900, height: 1500, color: [60, 160, 60] as [number, number, number] },
  { alt: `Quadrada ${suffix}`, width: 1000, height: 1000, color: [60, 60, 200] as [number, number, number] },
  { alt: `Paisagem comum ${suffix}`, width: 1200, height: 800, color: [180, 140, 40] as [number, number, number] },
]

let api: AdminApi
let criada: PageWithPhotos

test.beforeAll(async () => {
  api = await AdminApi.as('direcao')
  criada = await createPageWithPhotos(api, { slug, title: `Miniaturas ${suffix}`, gallery: galeria })
})

test.afterAll(async () => {
  await removePageWithPhotos(api, criada)
  await api.dispose()
})

for (const viewport of [
  { name: 'desktop', width: 1280, height: 900 },
  { name: 'celular', width: 390, height: 800 },
]) {
  test(`miniaturas com a mesma proporção e tamanho (${viewport.name})`, async ({ page }) => {
    await page.setViewportSize({ width: viewport.width, height: viewport.height })
    await gotoSite(page, `/${slug}`)

    const imagens = page.getByRole('region', { name: 'Fotos da página' }).locator('img')
    await expect(imagens).toHaveCount(galeria.length)

    for (const img of await imagens.all()) {
      await img.scrollIntoViewIfNeeded()
      await expect(img).toHaveJSProperty('complete', true)
    }

    const caixas = await imagens.evaluateAll((els) =>
      els.map((el) => {
        const { width, height } = el.getBoundingClientRect()
        const estilo = getComputedStyle(el)

        return { width, height, fit: estilo.objectFit, position: estilo.objectPosition }
      }),
    )

    for (const caixa of caixas) {
      expect(caixa.fit).toBe('cover')
      expect(caixa.position).toBe('50% 50%')
      expect(caixa.width / caixa.height).toBeCloseTo(4 / 3, 1)
      expect(caixa.height).toBeCloseTo(caixas[0]!.height, 0)
      expect(caixa.width).toBeCloseTo(caixas[0]!.width, 0)
    }

    // width/height do <img> são os do recorte, para não deslocar o layout.
    expect(await imagens.evaluateAll((els) => els.map((el) => `${el.getAttribute('width')}x${el.getAttribute('height')}`))).toEqual(
      galeria.map(() => '800x600'),
    )
  })
}

test('a ampliação mostra a foto inteira, na proporção real', async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 900 })
  await gotoSite(page, `/${slug}`)

  // A foto em pé: a miniatura é 4:3, mas a ampliada mantém 900:1500.
  await page.getByRole('link', { name: `Ampliar imagem: ${galeria[1].alt}` }).click()
  const dialogo = page.getByRole('dialog', { name: 'Imagem ampliada' })
  const ampliada = dialogo.locator('img.ampliacao__imagem')
  await expect(ampliada).toBeVisible()
  await expect(ampliada).toHaveJSProperty('complete', true)

  const caixa = await ampliada.boundingBox()
  expect(caixa!.width / caixa!.height).toBeCloseTo(900 / 1500, 1)
  await expect(ampliada).toHaveCSS('object-fit', 'contain')
})
