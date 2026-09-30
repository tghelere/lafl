import { type Page, expect, test } from '@playwright/test'

import { openContentPageByTitle, unique } from '../../support/admin'
import { AdminApi } from '../../support/api'
import { createPage, deletePage } from '../../support/fixtures'
import { addPhotoFromSection } from '../../support/pageImages'
import { gotoSite } from '../../support/site'
import { storageStatePath } from '../../support/users'

/**
 * Reordenar a galeria pelo painel, só pelo teclado: foco no botão, Enter, e o foco precisa
 * continuar no mesmo botão da mesma foto depois que a lista se refaz — senão quem navega por
 * teclado volta para o topo da página a cada movimento. A ordem nova é conferida no site.
 */

async function sendToGallery(page: Page, alt: string, cor: [number, number, number]): Promise<void> {
  const secao = page.locator('section.page-images')
  await addPhotoFromSection(page, { alt, color: cor, width: 500, height: 300 })
  await expect(secao.getByRole('list', { name: 'Galeria' })).toContainText(alt)
}

async function siteOrder(page: Page, slug: string): Promise<string[]> {
  await gotoSite(page, `/${slug}`)

  return page
    .getByRole('region', { name: 'Fotos da página' })
    .locator('img')
    .evaluateAll((images) => images.map((image) => image.getAttribute('alt') ?? ''))
}

test.describe('comunicacao', () => {
  test.use({ storageState: storageStatePath('comunicacao') })

  test('move pelo teclado, o foco acompanha a foto, e o site mostra a ordem nova', async ({ page }) => {
    const titulo = `Página com ordem ${unique('e2e')}`
    const slug = unique('ordem').toLowerCase()
    const [a, b, c] = ['Primeira', 'Segunda', 'Terceira'].map((nome) => `${nome} foto ${unique('e2e')}`) as [string, string, string]
    const direcaoApi = await AdminApi.as('direcao')
    const pagina = await createPage(direcaoApi, { slug, title: titulo, content: '<p>Texto.</p>' })

    try {
      await openContentPageByTitle(page, titulo)
      await sendToGallery(page, a, [200, 40, 40])
      await sendToGallery(page, b, [40, 200, 40])
      await sendToGallery(page, c, [40, 40, 200])

      expect(await siteOrder(page, slug)).toEqual([a, b, c])

      await openContentPageByTitle(page, titulo)
      const secao = page.locator('section.page-images')

      // Nas pontas, o sentido impossível vem desligado.
      await expect(secao.getByRole('button', { name: `Mover para cima: ${a}` })).toBeDisabled()
      await expect(secao.getByRole('button', { name: `Mover para baixo: ${c}` })).toBeDisabled()

      // Enter duas vezes no mesmo botão: a primeira foto desce até o fim.
      await secao.getByRole('button', { name: `Mover para baixo: ${a}` }).focus()
      await page.keyboard.press('Enter')
      await expect(secao.getByText(/^\s*Imagem movida para a posição 2 de 3\.\s*$/)).toBeAttached()
      await expect(secao.getByRole('button', { name: `Mover para baixo: ${a}` })).toBeFocused()

      await page.keyboard.press('Enter')
      await expect(secao.getByText(/^\s*Imagem movida para a posição 3 de 3\.\s*$/)).toBeAttached()
      // Chegou ao fim: "descer" desligou, e o foco foi para "subir", da mesma foto.
      await expect(secao.getByRole('button', { name: `Mover para cima: ${a}` })).toBeFocused()

      await expect(secao.getByRole('group', { name: 'Ordem na galeria: posição 3 de 3' })).toBeVisible()
      expect(await siteOrder(page, slug)).toEqual([b, c, a])

      // E sobe uma, de volta ao meio.
      await openContentPageByTitle(page, titulo)
      await secao.getByRole('button', { name: `Mover para cima: ${a}` }).focus()
      await page.keyboard.press('Enter')
      await expect(secao.getByText(/^\s*Imagem movida para a posição 2 de 3\.\s*$/)).toBeAttached()
      expect(await siteOrder(page, slug)).toEqual([b, a, c])
    } finally {
      await deletePage(direcaoApi, pagina.id)

      for (const alt of [a, b, c]) {
        const resposta = await direcaoApi.get('/api/v1/media', { search: alt })
        for (const item of ((await resposta.json()) as { data: { id: string }[] }).data) {
          await direcaoApi.delete(`/api/v1/media/${item.id}`)
        }
      }

      await direcaoApi.dispose()
    }
  })

  test.describe('em 360px', () => {
    test.use({ viewport: { width: 360, height: 740 } })

    test('os botões de mover têm alvo de 44px e cabem na tela', async ({ page }) => {
      const titulo = `Ordem estreita ${unique('e2e')}`
      const altos = ['Um', 'Dois'].map((nome) => `${nome} ${unique('e2e')}`)
      const direcaoApi = await AdminApi.as('direcao')
      const pagina = await createPage(direcaoApi, { slug: unique('ordem-estreita').toLowerCase(), title: titulo, content: '<p>x</p>' })

      try {
        await openContentPageByTitle(page, titulo)
        await sendToGallery(page, altos[0]!, [90, 90, 90])
        await sendToGallery(page, altos[1]!, [160, 160, 160])

        for (const nome of [`Mover para baixo: ${altos[0]}`, `Mover para cima: ${altos[1]}`]) {
          const botao = page.getByRole('button', { name: nome })
          await botao.scrollIntoViewIfNeeded()
          expect((await botao.boundingBox())!.height).toBeGreaterThanOrEqual(44)
        }

        await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true)
      } finally {
        await deletePage(direcaoApi, pagina.id)

        for (const alt of altos) {
          const resposta = await direcaoApi.get('/api/v1/media', { search: alt })
          for (const item of ((await resposta.json()) as { data: { id: string }[] }).data) {
            await direcaoApi.delete(`/api/v1/media/${item.id}`)
          }
        }

        await direcaoApi.dispose()
      }
    })
  })
})
