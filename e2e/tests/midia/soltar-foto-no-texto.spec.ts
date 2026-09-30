import { expect, test } from '@playwright/test'

import { openContentPageByTitle, unique } from '../../support/admin'
import { AdminApi, fetchPageContent } from '../../support/api'
import { dropFileOnto, editor, saveContentPage } from '../../support/editor'
import { createPage, deletePage, placeholderPdf, solidPng } from '../../support/fixtures'
import { storageStatePath } from '../../support/users'

/**
 * Arrastar uma foto do computador e soltar no corpo do texto (sessão 32): abre o envio já com a
 * foto escolhida e, enviada, ela entra onde foi solta — não onde estava o cursor de digitação.
 *
 * A declaração continua obrigatória: soltar a foto não é atalho em volta dela. Quem recusa o
 * envio sem resposta é a API, e a mensagem que aparece é a dela.
 */
test.describe('comunicacao', () => {
  test.use({ storageState: storageStatePath('comunicacao') })

  test('a foto solta entre dois parágrafos entra ali, com a mesma declaração obrigatória do envio', async ({ page }) => {
    const titulo = `Página com foto solta ${unique('e2e')}`
    const descricao = `Quadra poliesportiva vazia ${unique('e2e')}`

    const direcaoApi = await AdminApi.as('direcao')
    const pagina = await createPage(direcaoApi, {
      slug: unique('foto-solta').toLowerCase(),
      title: titulo,
      content: '<p>Primeiro parágrafo.</p><p>Segundo parágrafo.</p>',
    })
    let mediaId: string | null = null

    try {
      await openContentPageByTitle(page, titulo)
      const segundo = editor(page).getByText('Segundo parágrafo.')

      // Um arquivo que não é foto abre o envio com a explicação, em vez de sumir em silêncio.
      await dropFileOnto(segundo, { name: 'ata.pdf', mimeType: 'application/pdf', buffer: placeholderPdf })
      const seletor = page.locator('dialog.media-picker[open]')
      await expect(seletor.getByText('A imagem precisa ser JPEG, PNG ou WebP.')).toBeVisible()
      await seletor.getByRole('button', { name: 'Fechar' }).click()
      await expect(seletor).toBeHidden()

      // Solta no começo do segundo parágrafo, com o cursor de digitação no começo do primeiro.
      // (O clique sozinho não basta: o <p> ocupa a largura toda, e clicar no meio dele põe o
      // cursor no FIM do primeiro parágrafo — o mesmo ponto em que a foto vai cair.)
      await editor(page).getByText('Primeiro parágrafo.').click()
      await page.keyboard.press('Home')
      await dropFileOnto(segundo, { name: 'quadra.png', mimeType: 'image/png', buffer: solidPng(800, 500, [90, 60, 160]) })
      await expect(seletor.getByRole('img', { name: 'A foto escolhida' })).toBeVisible()
      await seletor.getByLabel('Descrição da foto').fill(descricao)

      // Sem responder à declaração, a API recusa, e nada entra no texto.
      await seletor.getByRole('button', { name: 'Enviar e pôr no texto' }).click()
      await expect(
        seletor.getByText('Informe se a imagem mostra alguém que hoje ainda é criança ou adolescente e que é ou foi atendido pela instituição.'),
      ).toBeVisible()
      await expect(editor(page).locator('figure')).toHaveCount(0)

      await seletor.getByRole('radio', { name: 'Não' }).check()
      await seletor.getByRole('button', { name: 'Enviar e pôr no texto' }).click()
      await expect(seletor).toBeHidden()
      await expect(editor(page).locator('figure img')).toHaveAttribute('alt', descricao)

      await saveContentPage(page)
      const conteudo = await fetchPageContent(direcaoApi, pagina.id)
      mediaId = conteudo.match(/\/midia\/([0-9a-f-]{36})/)?.[1] ?? null
      expect(conteudo).toBe(`<p>Primeiro parágrafo.</p><figure><img src="/midia/${mediaId}" alt="${descricao}" /></figure><p>Segundo parágrafo.</p>`)
    } finally {
      await deletePage(direcaoApi, pagina.id)

      if (mediaId) {
        await direcaoApi.delete(`/api/v1/media/${mediaId}`)
      }

      await direcaoApi.dispose()
    }
  })
})
