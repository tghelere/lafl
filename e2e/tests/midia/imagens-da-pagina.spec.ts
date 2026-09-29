import { type Locator, expect, test } from '@playwright/test'

import { openContentPageByTitle, unique } from '../../support/admin'
import { AdminApi } from '../../support/api'
import { createPage, deletePage, solidPng } from '../../support/fixtures'
import { gotoSite } from '../../support/site'
import { storageStatePath } from '../../support/users'

/**
 * "Imagens desta página" de ponta a ponta (docs/decisoes/0025-imagens-da-pagina.md): a pessoa
 * abre a página no painel, envia uma foto para a galeria, corrige o texto alternativo, troca o
 * arquivo e tira a foto da galeria — e confere cada passo no site, sem salvar a página.
 *
 * O que só a pilha real prova: a mesma imagem sai por três servidores (a miniatura autenticada
 * no painel, a API pública com a galeria dentro do cache de dez minutos, e o proxy /midia do
 * site), e cada ação do painel precisa esquecer o cache certo para o site mudar na hora.
 */

async function imageLoaded(image: Locator): Promise<void> {
  await expect(image).toBeVisible()
  await expect
    .poll(() => image.evaluate((node) => (node as HTMLImageElement).complete && (node as HTMLImageElement).naturalWidth))
    .toBeGreaterThan(0)
}

test.describe('comunicacao', () => {
  test.use({ storageState: storageStatePath('comunicacao') })

  test('envia para a galeria, corrige o texto, troca o arquivo e tira da galeria, vendo cada passo no site', async ({ page }) => {
    const titulo = `Página com galeria ${unique('e2e')}`
    const slug = unique('galeria').toLowerCase()
    const alt = `Salão do bazar arrumado ${unique('e2e')}`
    const altCorrigido = `${alt}, visto da entrada`

    const direcaoApi = await AdminApi.as('direcao')
    const pagina = await createPage(direcaoApi, { slug, title: titulo, content: '<p>Texto da página.</p>' })
    let mediaId: string | null = null

    try {
      await openContentPageByTitle(page, titulo)
      const secao = page.locator('section.page-images')
      await expect(secao.getByRole('heading', { name: 'Imagens desta página' })).toBeVisible()
      await expect(secao.getByText('A galeria está vazia.')).toBeVisible()

      // 1. Enviar para a galeria, pela própria página.
      await secao.getByLabel('Arquivo (JPEG, PNG ou WebP, até 10 MB)', { exact: true }).setInputFiles({
        name: 'foto.png',
        mimeType: 'image/png',
        buffer: solidPng(800, 500, [30, 140, 60]),
      })
      await secao.getByLabel('Texto alternativo').fill(alt)
      await secao.getByLabel('Legenda (opcional)').fill('Setembro de 2026')
      await secao.getByRole('radio', { name: 'Não' }).check()
      await secao.getByRole('button', { name: 'Enviar para a galeria' }).click()

      await expect(secao.getByText(/Imagem enviada e posta no fim da galeria/)).toBeVisible()
      const galeria = secao.getByRole('list', { name: 'Galeria' })
      const item = galeria.getByRole('listitem').filter({ hasText: alt })
      await imageLoaded(item.locator('img'))

      // Entrou também na biblioteca: o mesmo depósito, pela outra porta.
      const biblioteca = await direcaoApi.get('/api/v1/media', { search: alt })
      mediaId = ((await biblioteca.json()) as { data: { id: string }[] }).data[0]!.id

      await gotoSite(page, `/${slug}`)
      const noSite = page.getByRole('region', { name: 'Fotos da página' }).locator('img')
      await imageLoaded(noSite)
      await expect(noSite).toHaveAttribute('alt', alt)
      await expect(noSite).toHaveAttribute('srcset', `/midia/${mediaId}/400.webp 400w, /midia/${mediaId}/640.webp 640w`)
      await expect(page.getByRole('region', { name: 'Fotos da página' }).locator('figcaption')).toHaveText('Setembro de 2026')

      // 2. Corrigir o texto alternativo no mesmo lugar.
      await openContentPageByTitle(page, titulo)
      await item.getByRole('button', { name: 'Editar texto' }).click()
      await item.getByLabel('Texto alternativo').fill(altCorrigido)
      await item.getByRole('button', { name: 'Salvar texto' }).click()
      await expect(secao.getByText('Texto da imagem salvo.')).toBeVisible()

      await gotoSite(page, `/${slug}`)
      await expect(noSite).toHaveAttribute('alt', altCorrigido)

      // 3. Substituir o arquivo por um maior: o site ganha a largura nova sem editar a página.
      await openContentPageByTitle(page, titulo)
      const itemCorrigido = galeria.getByRole('listitem').filter({ hasText: altCorrigido })
      await itemCorrigido.getByLabel('Substituir arquivo (JPEG, PNG ou WebP, até 10 MB)').setInputFiles({
        name: 'maior.png',
        mimeType: 'image/png',
        buffer: solidPng(1300, 800, [200, 120, 20]),
      })
      await itemCorrigido.getByRole('button', { name: 'Substituir', exact: true }).click()
      await expect(secao.getByText(/Arquivo substituído/)).toBeVisible()

      await gotoSite(page, `/${slug}`)
      await imageLoaded(noSite)
      await expect(noSite).toHaveAttribute('srcset', new RegExp(`/midia/${mediaId}/1280\\.webp 1280w`))
      await expect(noSite).toHaveAttribute('width', '1300')

      // 4. Tirar da galeria: some do site e continua na biblioteca.
      await openContentPageByTitle(page, titulo)
      await galeria.getByRole('listitem').filter({ hasText: altCorrigido }).getByRole('button', { name: 'Tirar da galeria' }).click()
      await expect(secao.getByText('A galeria está vazia.')).toBeVisible()

      await gotoSite(page, `/${slug}`)
      await expect(page.getByRole('heading', { name: titulo })).toBeVisible()
      await expect(page.getByRole('region', { name: 'Fotos da página' })).toHaveCount(0)
      expect((await direcaoApi.get(`/api/v1/media/${mediaId}`)).ok()).toBe(true)
    } finally {
      await deletePage(direcaoApi, pagina.id)

      if (mediaId) {
        await direcaoApi.delete(`/api/v1/media/${mediaId}`)
      }

      await direcaoApi.dispose()
    }
  })

  test.describe('em 360px', () => {
    test.use({ viewport: { width: 360, height: 740 } })

    test('a seção cabe na tela, e os botões têm alvo de 44px', async ({ page }) => {
      const titulo = `Página estreita com galeria ${unique('e2e')}`
      const direcaoApi = await AdminApi.as('direcao')
      const pagina = await createPage(direcaoApi, { slug: unique('estreita-galeria').toLowerCase(), title: titulo, content: '<p>x</p>' })

      try {
        await openContentPageByTitle(page, titulo)
        const envio = page.getByRole('button', { name: 'Enviar para a galeria' })
        await envio.scrollIntoViewIfNeeded()
        expect((await envio.boundingBox())!.height).toBeGreaterThanOrEqual(44)
        await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true)
      } finally {
        await deletePage(direcaoApi, pagina.id)
        await direcaoApi.dispose()
      }
    })
  })
})
