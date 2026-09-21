import { expect, test } from '@playwright/test'

import { AdminApi, fetchPageContent, markerValue } from '../../support/api'
import { acceptNextDialog, unique } from '../../support/admin'
import { replaceEditorText, saveContentPage } from '../../support/editor'
import { createPage, deletePage, placeholderPdf } from '../../support/fixtures'
import { gotoSite, serverHtml } from '../../support/site'
import { storageStatePath } from '../../support/users'

/**
 * A contagem que /transparencia publica, lida do HTML que o servidor entregou.
 *
 * Por expressão regular sobre a frase, e não por um seletor: o texto daquela página é editável
 * pelo painel e pode ser reescrito a qualquer momento — o que não pode mudar é que ali saia um
 * número, e não "cerca de 70". Se a frase mudar de forma a não casar, o teste falha dizendo
 * isso, que é o aviso certo.
 */
async function contagemDoAcervo(): Promise<number> {
  const html = await serverHtml('/transparencia')
  const match = html.match(/hoje com ([\d.]+) documentos?/)

  expect(match, '/transparencia precisa publicar a contagem do acervo').not.toBeNull()

  return Number(match![1]!.replace(/\./g, ''))
}

/**
 * O caso "com dados" da fundação e do tempo de bazar na home — o contraponto do caso "sem
 * dados" em e2e/tests/site-sem-api/home-sem-api.spec.ts, que só é alcançável numa segunda
 * instância do site com a API fora do ar. Aqui a API está de pé, como em toda a bateria
 * normal.
 */
test.describe('home — fundação e tempo de bazar', () => {
  test('com a API de pé, a home mostra as quatro linhas de registro, não a frase alternativa', async ({ page }) => {
    await gotoSite(page, '/')

    await expect(page.locator('.ledger__fallback')).toHaveCount(0)

    const itens = page.locator('.ledger__item')
    await expect(itens).toHaveCount(4)

    await expect(page.getByText('Fundação da associação')).toBeVisible()
    await expect(page.getByText('Bazar beneficente em funcionamento desde')).toBeVisible()
  })
})

test.describe('comunicacao', () => {
  test.use({ storageState: storageStatePath('comunicacao') })

  test('marcador escrito no editor vira número no site', async ({ page }) => {
    const comunicacao = await AdminApi.as('comunicacao')
    const superAdmin = await AdminApi.as('super_admin')
    const slug = unique('e2e-marcador')
    const pagina = await createPage(superAdmin, {
      slug,
      title: `Página de marcador ${slug}`,
      content: '<p>Nenhum número ainda.</p>',
    })

    try {
      const idadeDoBazar = await markerValue(comunicacao, 'idade_bazar')

      await page.goto(`/admin/paginas/${pagina.id}`)

      // O editor mostra o marcador e o valor de hoje — é daqui que quem escreve o copia.
      await expect(page.getByText('{{idade_bazar}}')).toBeVisible()
      await expect(page.getByText(`hoje: ${idadeDoBazar}`)).toBeVisible()

      await replaceEditorText(page, 'O bazar funciona há {{idade_bazar}}.')
      await saveContentPage(page)

      // O painel continua com o marcador CRU. Se recebesse o valor resolvido, este salvamento
      // teria gravado "58 anos" em texto fixo e o cálculo morreria em silêncio.
      expect(await fetchPageContent(comunicacao, pagina.id)).toContain('{{idade_bazar}}')

      const html = await serverHtml(`/${slug}`)
      expect(html).toContain(`O bazar funciona há ${idadeDoBazar}.`)
      expect(html).not.toContain('{{idade_bazar}}')
    } finally {
      await deletePage(superAdmin, pagina.id)
      await comunicacao.dispose()
      await superAdmin.dispose()
    }
  })

  test('marcador inventado é recusado ao salvar, com a lista dos válidos', async ({ page }) => {
    const superAdmin = await AdminApi.as('super_admin')
    const slug = unique('e2e-marcador-invalido')
    const pagina = await createPage(superAdmin, {
      slug,
      title: `Página de marcador inválido ${slug}`,
      content: '<p>Conteúdo que não pode ser substituído.</p>',
    })

    try {
      await page.goto(`/admin/paginas/${pagina.id}`)
      await replaceEditorText(page, 'A casa tem {{idade_da_casa}}.')

      await page.getByRole('button', { name: 'Salvar' }).click()

      await expect(page.getByText('Corrija os campos indicados antes de salvar.')).toBeVisible()
      // No erro do campo, não no texto do editor — os dois trazem `{{idade_da_casa}}`.
      await expect(page.locator('.field__error').first()).toContainText('{{idade_da_casa}}')

      // Nada foi gravado: a página segue com o texto de antes.
      expect(await fetchPageContent(superAdmin, pagina.id)).toContain(
        'Conteúdo que não pode ser substituído.',
      )
    } finally {
      await deletePage(superAdmin, pagina.id)
      await superAdmin.dispose()
    }
  })
})

test.describe('financeiro', () => {
  test.use({ storageState: storageStatePath('financeiro') })

  test('publicar um documento aumenta a contagem em /transparencia', async ({ page }) => {
    const antes = await contagemDoAcervo()
    const titulo = `Balanço contado ${unique('e2e')}`

    await page.goto('/admin/transparencia/novo')
    await page.getByLabel('Título').fill(titulo)
    await page.getByLabel('Ano').fill('2025')
    await page.getByLabel('Tipo').selectOption('balance')
    await page.getByLabel('Arquivo (PDF, até 20 MB)').setInputFiles({
      name: 'balanco-contado-e2e.pdf',
      mimeType: 'application/pdf',
      buffer: placeholderPdf,
    })
    await page.getByRole('button', { name: 'Salvar' }).click()
    await expect(page.getByText('Documento cadastrado.')).toBeVisible()

    // Nasce rascunho: até publicar, não entra na conta.
    await page.getByLabel('Ano').fill('2025')
    await page.getByRole('button', { name: 'Filtrar' }).click()
    await page.getByRole('link', { name: titulo }).click()
    await expect(page.getByText('Rascunho', { exact: true })).toBeVisible()

    expect(await contagemDoAcervo()).toBe(antes)

    await page.getByRole('button', { name: 'Publicar' }).click()
    await expect(page.getByText('Publicado', { exact: true })).toBeVisible()

    // Sem espera artificial: a página de transparência fica em cache por dez minutos no
    // backend, mas o marcador é resolvido DEPOIS desse cache (ver
    // App\Http\Controllers\Api\V1\Public\PageController) — a contagem nova vale já aqui.
    expect(await contagemDoAcervo()).toBe(antes + 1)

    const dialogo = acceptNextDialog(page)
    await page.getByRole('button', { name: 'Excluir' }).click()
    await dialogo
    await expect(page.getByText('Documento excluído.')).toBeVisible()

    expect(await contagemDoAcervo()).toBe(antes)
  })
})
