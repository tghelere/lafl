import { expect, test } from '@playwright/test'

import { openContentPageByTitle, unique } from '../../support/admin'
import { AdminApi } from '../../support/api'
import { editor, saveContentPage } from '../../support/editor'
import { createPage, deletePage } from '../../support/fixtures'
import { addPhotoFromSection, pageImagesSection } from '../../support/pageImages'
import { storageStatePath } from '../../support/users'

/**
 * A tela de edição de página tem duas regras de salvamento, lado a lado (sessão 32): o texto só
 * vai para o site com Salvar; as fotos da seção de baixo vão na hora. A tela precisa deixar isso
 * visível — e, principalmente, não pode dizer "não salvo" por causa de uma foto que já está no ar.
 */
test.describe('comunicacao', () => {
  test.use({ storageState: storageStatePath('comunicacao') })

  test('o texto avisa quando falta Salvar; foto adicionada na seção não deixa nada por salvar', async ({ page }) => {
    const titulo = `Página com dois salvamentos ${unique('e2e')}`
    const descricao = `Refeitório arrumado ${unique('e2e')}`
    const direcaoApi = await AdminApi.as('direcao')
    const pagina = await createPage(direcaoApi, { slug: unique('dois-salvamentos').toLowerCase(), title: titulo, content: '<p>Texto.</p>' })

    try {
      await openContentPageByTitle(page, titulo)
      const estado = page.getByRole('status').filter({ hasText: /salv/ })
      await expect(estado).toHaveText('Tudo salvo')
      await expect(pageImagesSection(page).getByText('Aqui tudo vai para o site na hora')).toBeVisible()

      // A foto vai para o site na hora: nada fica por salvar no texto.
      await addPhotoFromSection(page, { alt: descricao, color: [120, 80, 30] })
      await expect(pageImagesSection(page).getByText(/Foto enviada e posta no fim da galeria/)).toBeVisible()
      await expect(estado).toHaveText('Tudo salvo')

      // Mexer no texto é outra história: o aviso aparece no topo do formulário e junto do Salvar.
      await editor(page).click()
      await page.keyboard.press('ControlOrMeta+End')
      await page.keyboard.type(' Mais uma frase.')
      await expect(estado).toHaveText('Alterações não salvas')
      await expect(page.getByText('Há alterações no texto ainda não salvas.')).toBeVisible()
      await expect(pageImagesSection(page).getByText(/O texto tem alterações não salvas/)).toBeVisible()

      await saveContentPage(page)
      await expect(estado).toHaveText('Tudo salvo')
      await expect(page.getByText('Há alterações no texto ainda não salvas.')).toHaveCount(0)
    } finally {
      const resposta = await direcaoApi.get('/api/v1/media', { search: descricao })
      await deletePage(direcaoApi, pagina.id)

      for (const item of ((await resposta.json()) as { data: { id: string }[] }).data) {
        await direcaoApi.delete(`/api/v1/media/${item.id}`)
      }

      await direcaoApi.dispose()
    }
  })
})
