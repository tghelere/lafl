import { type Locator, expect, test } from '@playwright/test'

import { openContentPageByTitle, unique } from '../../support/admin'
import { AdminApi, fetchPageContent } from '../../support/api'
import { editor, saveContentPage } from '../../support/editor'
import { createPage, deletePage, solidPng } from '../../support/fixtures'
import { gotoSite } from '../../support/site'
import { storageStatePath } from '../../support/users'

/**
 * Enviar uma foto do computador sem sair da página que está sendo editada (sessão 32). Antes, o
 * seletor do editor só escolhia imagem já existente, e enviar exigia abrir a biblioteca numa aba
 * nova, voltar e buscar.
 *
 * Os dois seletores da tela de edição ganharam a aba "Enviar do computador": o do texto, que
 * manda a foto para a biblioteca e a põe no ponto do cursor, e o da capa, que a manda direto
 * para a capa. O que só a pilha real prova: o envio atravessa a API de verdade (recodificação,
 * derivadas), a miniatura autenticada carrega no editor, e o site mostra a foto depois de salvar.
 */

async function imageLoaded(image: Locator): Promise<void> {
  await expect(image).toBeVisible()
  await expect
    .poll(() => image.evaluate((node) => (node as HTMLImageElement).complete && (node as HTMLImageElement).naturalWidth))
    .toBeGreaterThan(0)
}

test.describe('comunicacao', () => {
  test.use({ storageState: storageStatePath('comunicacao') })

  test('envia a foto pelo seletor do editor, que a põe no texto; salvar a leva ao site', async ({ page }) => {
    const titulo = `Página com envio no editor ${unique('e2e')}`
    const slug = unique('envio-editor').toLowerCase()
    const descricao = `Mudas na horta ${unique('e2e')}`
    const legenda = 'Plantio de setembro'

    const direcaoApi = await AdminApi.as('direcao')
    const pagina = await createPage(direcaoApi, { slug, title: titulo, content: '<p>Texto antes da foto.</p>' })
    let mediaId: string | null = null

    try {
      await openContentPageByTitle(page, titulo)
      await editor(page).click()
      await page.keyboard.press('ControlOrMeta+End')
      await page.getByRole('toolbar', { name: 'Formatação do conteúdo' }).getByRole('button', { name: 'Imagem', exact: true }).click()

      const seletor = page.locator('dialog.media-picker[open]')
      await expect(seletor.getByRole('tab', { name: 'Enviar do computador' })).toHaveAttribute('aria-selected', 'true')
      await seletor.getByLabel('Escolher foto do computador').setInputFiles({
        name: 'IMG_2031.png',
        mimeType: 'image/png',
        buffer: solidPng(800, 500, [60, 150, 60]),
      })

      // Escolhida a foto, o foco vai direto para descrevê-la.
      await expect(seletor.getByLabel('Descrição da foto')).toBeFocused()
      await expect(seletor.getByRole('img', { name: 'A foto escolhida' })).toBeVisible()
      await seletor.getByLabel('Descrição da foto').fill(descricao)
      await seletor.getByLabel('Legenda (opcional)').fill(legenda)
      await seletor.getByRole('radio', { name: 'Não' }).check()
      await seletor.getByRole('button', { name: 'Enviar e pôr no texto' }).click()
      await expect(seletor).toBeHidden()

      // A foto já está no editor, pela rota autenticada da API — sem sair da página.
      await expect(page).toHaveURL(new RegExp(`/admin/paginas/${pagina.id}$`))
      const noEditor = editor(page).locator('figure img')
      await imageLoaded(noEditor)
      await expect(noEditor).toHaveAttribute('alt', descricao)

      await saveContentPage(page)
      const conteudo = await fetchPageContent(direcaoApi, pagina.id)
      mediaId = conteudo.match(/\/midia\/([0-9a-f-]{36})/)?.[1] ?? null
      expect(conteudo).toContain(`<figure><img src="/midia/${mediaId}" alt="${descricao}" /><figcaption>${legenda}</figcaption></figure>`)

      await gotoSite(page, `/${slug}`)
      const naPagina = page.locator('.page-content figure img')
      await imageLoaded(naPagina)
      await expect(naPagina).toHaveAttribute('alt', descricao)
    } finally {
      await deletePage(direcaoApi, pagina.id)

      if (mediaId) {
        await direcaoApi.delete(`/api/v1/media/${mediaId}`)
      }

      await direcaoApi.dispose()
    }
  })

  test('envia a foto pelo seletor da capa, que já a põe na capa', async ({ page }) => {
    const titulo = `Página com envio na capa ${unique('e2e')}`
    const descricao = `Fachada ao entardecer ${unique('e2e')}`

    const direcaoApi = await AdminApi.as('direcao')
    const pagina = await createPage(direcaoApi, { slug: unique('envio-capa').toLowerCase(), title: titulo, content: '<p>x</p>' })

    try {
      await openContentPageByTitle(page, titulo)
      const secao = page.locator('section.page-images')
      await secao.getByRole('button', { name: 'Escolher a capa na biblioteca' }).click()

      const seletor = page.locator('dialog.media-picker[open]')
      await expect(seletor.getByRole('heading', { name: 'Foto que representa esta página' })).toBeVisible()
      await seletor.getByLabel('Escolher foto do computador').setInputFiles({
        name: 'fachada.png',
        mimeType: 'image/png',
        buffer: solidPng(900, 600, [180, 100, 40]),
      })
      await seletor.getByLabel('Descrição da foto').fill(descricao)
      await seletor.getByRole('radio', { name: 'Não' }).check()
      await seletor.getByRole('button', { name: 'Enviar e usar nesta página' }).click()
      await expect(seletor).toBeHidden()

      await expect(secao.getByText(/Foto enviada e posta no lugar da anterior/)).toBeVisible()
      await imageLoaded(secao.getByRole('list', { name: 'Capa' }).locator('img'))
      await expect(secao.getByRole('list', { name: 'Capa' })).toContainText(descricao)
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
