import { type Locator, type Page, expect, test } from '@playwright/test'

import { openContentPageByTitle, unique } from '../../support/admin'
import { AdminApi } from '../../support/api'
import { solidPng } from '../../support/fixtures'
import { gotoSite } from '../../support/site'
import { storageStatePath } from '../../support/users'

/**
 * Escolher a capa pelo painel (docs/decisoes/0025-imagens-da-pagina.md). A capa não aparece na
 * própria página: a de "Quem somos" é o destaque da página inicial. É esse acoplamento que o
 * teste percorre, porque é ele que a pessoa precisa enxergar na tela: mexer na capa de uma
 * página muda OUTRA página do site.
 *
 * Usa a "Quem Somos" semeada no banco de e2e (a home lê a capa pelo slug) e a deixa sem capa no
 * fim, como começou.
 */

async function imageLoaded(image: Locator): Promise<void> {
  await expect(image).toBeVisible()
  await expect
    .poll(() => image.evaluate((node) => (node as HTMLImageElement).complete && (node as HTMLImageElement).naturalWidth))
    .toBeGreaterThan(0)
}

async function uploadFromSection(page: Page, alt: string, destino: 'Fim da galeria' | 'Capa, no lugar da atual', cor: [number, number, number]): Promise<void> {
  const secao = page.locator('section.page-images')
  await secao.getByLabel('Arquivo (JPEG, PNG ou WebP, até 10 MB)', { exact: true }).setInputFiles({
    name: 'foto.png',
    mimeType: 'image/png',
    buffer: solidPng(900, 600, cor),
  })
  await secao.getByLabel('Texto alternativo').fill(alt)
  await secao.getByRole('radio', { name: 'Não' }).check()
  await secao.getByRole('radio', { name: destino }).check()
  await secao.getByRole('button', { name: /^Enviar para a/ }).click()
}

test.describe('comunicacao', () => {
  test.use({ storageState: storageStatePath('comunicacao') })

  test('vê onde a capa aparece, envia uma capa, troca por outra da biblioteca e tira — e a home acompanha', async ({ page }) => {
    const altEnviada = `Fachada da sede ao amanhecer ${unique('e2e')}`
    const altBiblioteca = `Fachada da sede vista da rua ${unique('e2e')}`
    const direcaoApi = await AdminApi.as('direcao')
    const destaque = page.getByRole('region', { name: 'Foto da sede' })
    const criadas: string[] = []

    try {
      await openContentPageByTitle(page, 'Quem Somos')
      const secao = page.locator('section.page-images')

      // O acoplamento com a home está escrito na tela de quem edita.
      await expect(secao.getByText('A capa desta página aparece em o destaque da página inicial.')).toBeVisible()
      await expect(secao.getByText('Esta página não tem capa.')).toBeVisible()

      // 1. Enviar uma foto nova direto para a capa.
      await uploadFromSection(page, altEnviada, 'Capa, no lugar da atual', [200, 90, 40])
      await expect(secao.getByText('Imagem enviada e posta na capa. Ela já aparece no site.')).toBeVisible()
      await imageLoaded(secao.getByRole('list', { name: 'Capa' }).locator('img'))

      await gotoSite(page, '/')
      await imageLoaded(destaque.locator('img'))
      await expect(destaque.locator('img')).toHaveAttribute('alt', altEnviada)

      // 2. Trocar por outra que já está na biblioteca (enviada antes, para a galeria).
      await openContentPageByTitle(page, 'Quem Somos')
      await uploadFromSection(page, altBiblioteca, 'Fim da galeria', [40, 90, 200])
      await expect(secao.getByText(/posta no fim da galeria/)).toBeVisible()

      await secao.getByRole('button', { name: 'Trocar a capa por imagem da biblioteca' }).click()
      const seletor = page.locator('dialog.media-picker[open]')
      await expect(seletor.getByRole('heading', { name: 'Escolher a capa' })).toBeVisible()
      await seletor.getByLabel('Buscar na biblioteca').fill(altBiblioteca)
      await seletor.getByRole('button', { name: 'Buscar' }).click()
      await seletor.getByRole('button', { name: altBiblioteca }).click()
      await expect(seletor).toBeHidden()
      await expect(secao.getByText('Capa trocada. O site já mostra a nova.')).toBeVisible()
      await expect(secao.getByRole('list', { name: 'Capa' })).toContainText(altBiblioteca)

      await gotoSite(page, '/')
      await expect(destaque.locator('img')).toHaveAttribute('alt', altBiblioteca)

      // 3. Tirar a capa: a home fica sem o destaque.
      await openContentPageByTitle(page, 'Quem Somos')
      const capa = secao.getByRole('list', { name: 'Capa' })
      await capa.getByRole('button', { name: 'Tirar da capa' }).click()
      await expect(secao.getByText('Esta página não tem capa.')).toBeVisible()

      await gotoSite(page, '/')
      await expect(page.getByRole('heading', { level: 1 })).toBeVisible()
      await expect(destaque).toHaveCount(0)

      // A foto da galeria sai também, para "Quem Somos" terminar como começou.
      await openContentPageByTitle(page, 'Quem Somos')
      await secao
        .getByRole('list', { name: 'Galeria' })
        .getByRole('listitem')
        .filter({ hasText: altBiblioteca })
        .getByRole('button', { name: 'Tirar da galeria' })
        .click()
      await expect(secao.getByText('A galeria está vazia.')).toBeVisible()
    } finally {
      for (const alt of [altEnviada, altBiblioteca]) {
        const resposta = await direcaoApi.get('/api/v1/media', { search: alt })
        for (const item of ((await resposta.json()) as { data: { id: string }[] }).data) {
          criadas.push(item.id)
        }
      }

      for (const id of criadas) {
        await direcaoApi.delete(`/api/v1/media/${id}`)
      }

      await direcaoApi.dispose()
    }
  })
})
