import { createHash } from 'node:crypto'

import { type Locator, type Page, expect, test } from '@playwright/test'

import { acceptNextDialog, openContentPageByTitle, pageAs, unique } from '../../support/admin'
import { AdminApi, fetchPageContent } from '../../support/api'
import { editor, saveContentPage } from '../../support/editor'
import { ADMIN_URL, SITE_URL } from '../../support/env'
import { createPage, deletePage, solidPng } from '../../support/fixtures'
import { gotoSite } from '../../support/site'
import { storageStatePath } from '../../support/users'

/**
 * A biblioteca de imagens de ponta a ponta — o caminho que alguém da instituição percorre para
 * pôr uma foto numa página e, depois, trocá-la (ver docs/decisoes/0024-biblioteca-de-midia.md).
 *
 * O que só a pilha real prova, e por isso está aqui e não num teste de componente:
 * - a miniatura do painel vem de uma rota AUTENTICADA da API por `<img>`, e só carrega se o
 *   cookie de sessão atravessa de painel para API — com a API simulada, isso nunca falharia;
 * - o editor grava a forma canônica (`/midia/{uuid}`), e é o SITE, pelo proxy do Nitro, que a
 *   serve em outra largura — três servidores no caminho de uma imagem;
 * - substituir o arquivo muda o que o MESMO endereço entrega, sem editar a página.
 */

async function imageLoaded(image: Locator): Promise<void> {
  await expect(image).toBeVisible()
  await expect
    .poll(() => image.evaluate((node) => (node as HTMLImageElement).complete && (node as HTMLImageElement).naturalWidth), {
      message: 'a imagem não carregou (naturalWidth 0) — o endereço respondeu erro?',
    })
    .toBeGreaterThan(0)
}

async function sha256Of(page: Page, url: string): Promise<string> {
  const response = await page.request.get(url)
  expect(response.ok(), `GET ${url} respondeu ${response.status()}`).toBe(true)

  return createHash('sha256').update(await response.body()).digest('hex')
}

async function uploadThroughPanel(page: Page, alt: string, png: Buffer): Promise<string> {
  await page.goto('/admin/imagens')
  await page.getByRole('link', { name: 'Enviar imagem' }).click()

  await page.getByLabel('Arquivo (JPEG, PNG ou WebP, até 10 MB)').setInputFiles({
    name: 'IMG_0001 foto do celular.png',
    mimeType: 'image/png',
    buffer: png,
  })
  await page.getByLabel('Texto alternativo').fill(alt)
  await page.getByRole('radio', { name: 'Não' }).check()
  await page.getByRole('button', { name: 'Enviar', exact: true }).click()

  await expect(page.getByText('Imagem enviada.')).toBeVisible()
  await expect(page).toHaveURL(/\/admin\/imagens\/[0-9a-f-]{36}(\?|$)/)

  return new URL(page.url()).pathname.split('/').at(-1)!
}

test.describe('comunicacao', () => {
  test.use({ storageState: storageStatePath('comunicacao') })

  test('sobe, insere na página, vê no site, substitui e vê a troca; excluir em uso é recusado', async ({ page, browser }) => {
    const alt = `Fachada vista do jardim ${unique('e2e')}`
    const legenda = 'A sede, numa manhã de setembro'
    const titulo = `Página com foto ${unique('e2e')}`
    const slug = unique('com-foto').toLowerCase()

    const direcaoApi = await AdminApi.as('direcao')
    const comunicacaoApi = await AdminApi.as('comunicacao')
    const pagina = await createPage(direcaoApi, { slug, title: titulo, content: '<p>Texto antes da imagem.</p>' })
    let mediaId: string | null = null

    try {
      // 1. Subir. 800px de largura: derivadas de 400 e 640, nada maior.
      mediaId = await uploadThroughPanel(page, alt, solidPng(800, 500, [200, 40, 40]))
      await imageLoaded(page.locator('.media-detail__image'))
      await expect(page.getByText('larguras geradas: 400, 640')).toBeVisible()

      // `comunicacao` substitui mas não exclui: o botão nem aparece (quem barra é a Policy).
      await expect(page.getByRole('button', { name: 'Excluir imagem' })).toHaveCount(0)

      // 2. Inserir na página pelo editor, escolhendo da biblioteca.
      await openContentPageByTitle(page, titulo)
      await editor(page).click()
      await page.keyboard.press('ControlOrMeta+End')
      await page.getByRole('toolbar', { name: 'Formatação do conteúdo' }).getByRole('button', { name: 'Imagem', exact: true }).click()

      const seletor = page.locator('dialog.media-picker')
      await expect(seletor.getByRole('heading', { name: 'Inserir imagem' })).toBeVisible()
      await seletor.getByLabel('Buscar na biblioteca').fill(alt)
      await seletor.getByRole('button', { name: 'Buscar' }).click()
      await seletor.getByRole('button', { name: alt }).click()

      // O alternativo vem da biblioteca, pronto para ajustar a este texto.
      await expect(seletor.getByLabel('Texto alternativo')).toHaveValue(alt)
      await seletor.getByLabel('Legenda (opcional)').fill(legenda)
      await seletor.getByRole('button', { name: 'Inserir no texto' }).click()
      await expect(seletor).toBeHidden()

      await imageLoaded(editor(page).locator('figure img'))
      await saveContentPage(page)

      // O que ficou gravado é a forma canônica — sem largura, sem host.
      expect(await fetchPageContent(comunicacaoApi, pagina.id)).toContain(
        `<figure><img src="/midia/${mediaId}" alt="${alt}" /><figcaption>${legenda}</figcaption></figure>`,
      )

      // 3. Ver no site, servida pelo domínio do site na largura certa.
      await gotoSite(page, `/${slug}`)
      const naPagina = page.locator('.page-content figure img')
      await imageLoaded(naPagina)
      await expect(naPagina).toHaveAttribute('src', `/midia/${mediaId}/640.webp`)
      await expect(naPagina).toHaveAttribute('alt', alt)
      await expect(naPagina).toHaveAttribute('srcset', `/midia/${mediaId}/400.webp 400w, /midia/${mediaId}/640.webp 640w`)
      await expect(page.locator('.page-content figcaption')).toHaveText(legenda)

      const enderecoFixo = `${SITE_URL}/midia/${mediaId}/640.webp`
      const antes = await sha256Of(page, enderecoFixo)

      // 4. Substituir o arquivo por um maior e de outra cor.
      await page.goto(`${ADMIN_URL}/admin/imagens/${mediaId}`)
      await expect(page.getByRole('heading', { name: 'Onde é usada' })).toBeVisible()
      await expect(page.getByRole('link', { name: titulo })).toBeVisible()
      await page.getByLabel('Novo arquivo (JPEG, PNG ou WebP, até 10 MB)').setInputFiles({
        name: 'outra.png',
        mimeType: 'image/png',
        buffer: solidPng(1200, 700, [40, 40, 200]),
      })
      await page.getByRole('button', { name: 'Substituir arquivo' }).click()
      await expect(page.getByText(/Arquivo substituído/)).toBeVisible()
      await expect(page.getByText('larguras geradas: 400, 640, 960')).toBeVisible()

      // 5. A troca aparece no site sem editar a página: o mesmo endereço entrega outro
      // arquivo, e o srcset ganhou a largura nova.
      expect(await sha256Of(page, enderecoFixo), 'o mesmo endereço continuou entregando o arquivo antigo').not.toBe(antes)

      await gotoSite(page, `/${slug}`)
      await imageLoaded(naPagina)
      await expect(naPagina).toHaveAttribute('src', `/midia/${mediaId}/960.webp`)
      await expect(naPagina).toHaveAttribute('srcset', new RegExp(`/midia/${mediaId}/960\\.webp 960w`))
      await expect(naPagina).toHaveAttribute('width', '1200')

      // 6. Tentar excluir em uso — `direcao`, que tem o botão.
      const direcao = await pageAs(browser, 'direcao')
      await direcao.goto(`${ADMIN_URL}/admin/imagens/${mediaId}`)
      const confirmacao = acceptNextDialog(direcao)
      await direcao.getByRole('button', { name: 'Excluir imagem' }).click()
      await confirmacao

      await expect(
        direcao.getByText(`Esta imagem está em uso na página ${titulo} (/${slug}). Tire-a da página antes de excluir.`),
      ).toBeVisible()
      await direcao.reload()
      await expect(direcao.getByRole('heading', { name: 'Editar imagem' })).toBeVisible()
      await direcao.context().close()
    } finally {
      await deletePage(direcaoApi, pagina.id)

      if (mediaId) {
        await direcaoApi.delete(`/api/v1/media/${mediaId}`)
      }

      await direcaoApi.dispose()
      await comunicacaoApi.dispose()
    }
  })

  test('foto declarada como de criança ou adolescente atendido é recusada pela API', async ({ page }) => {
    await page.goto('/admin/imagens/nova')
    await page.getByLabel('Arquivo (JPEG, PNG ou WebP, até 10 MB)').setInputFiles({
      name: 'x.png',
      mimeType: 'image/png',
      buffer: solidPng(300, 200, [10, 120, 10]),
    })
    await page.getByLabel('Texto alternativo').fill('Crianças no pátio')
    await page.getByRole('radio', { name: 'Sim, mostra' }).check()
    await page.getByRole('button', { name: 'Enviar', exact: true }).click()

    await expect(page.getByText(/Fotos de crianças e adolescentes atendidos ainda não podem ser cadastradas/)).toBeVisible()
    await expect(page).toHaveURL(/\/admin\/imagens\/nova$/)
  })

  test.describe('em 360px', () => {
    test.use({ viewport: { width: 360, height: 740 } })

    test('a biblioteca e o seletor do editor cabem na tela, sem rolagem lateral', async ({ page }) => {
      const api = await AdminApi.as('comunicacao')
      const titulo = `Página estreita ${unique('e2e')}`
      const direcaoApi = await AdminApi.as('direcao')
      const pagina = await createPage(direcaoApi, { slug: unique('estreita').toLowerCase(), title: titulo, content: '<p>x</p>' })

      try {
        await page.goto('/admin/imagens')
        await expect(page.getByRole('heading', { name: 'Imagens' })).toBeVisible()
        await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true)

        await openContentPageByTitle(page, titulo)
        const barra = page.getByRole('toolbar', { name: 'Formatação do conteúdo' })
        const botao = barra.getByRole('button', { name: 'Imagem', exact: true })
        await botao.scrollIntoViewIfNeeded()
        expect((await botao.boundingBox())!.height).toBeGreaterThanOrEqual(44)
        await botao.click()

        const seletor = page.locator('dialog.media-picker')
        await expect(seletor).toBeVisible()
        const caixa = (await seletor.boundingBox())!
        expect(caixa.x).toBeGreaterThanOrEqual(0)
        expect(caixa.x + caixa.width).toBeLessThanOrEqual(360)
        expect((await seletor.getByRole('button', { name: 'Fechar' }).boundingBox())!.height).toBeGreaterThanOrEqual(44)
      } finally {
        await deletePage(direcaoApi, pagina.id)
        await direcaoApi.dispose()
        await api.dispose()
      }
    })
  })
})
