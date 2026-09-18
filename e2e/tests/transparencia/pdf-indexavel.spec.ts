import { expect, test } from '@playwright/test'

import { API_URL, SITE_URL } from '../../support/env'
import { gotoSite } from '../../support/site'

/**
 * Cobertura da tarefa 06 (docs/tarefas/06-seo-e-pdfs-da-transparencia.md), etapa 3: o PDF de
 * transparência tem URL legível no domínio do site, abre no navegador em vez de baixar à
 * força, e o endereço antigo por uuid leva até ele.
 *
 * O acervo vem do TransparencyDocumentsSeeder — "Balanço patrimonial 2024" é o primeiro
 * documento dele, e o slug nasce do título (App\Support\Transparency\DocumentSlug).
 */
const TITULO = 'Balanço patrimonial 2024'
const CAMINHO = '/transparencia/documentos/2024/balanco-patrimonial-2024.pdf'

test.describe('PDF de transparência', () => {
  test('a URL legível serve o PDF inline, pelo domínio do site', async ({ request }) => {
    const response = await request.get(`${SITE_URL}${CAMINHO}`)

    expect(response.status()).toBe(200)
    expect(response.headers()['content-type']).toBe('application/pdf')
    expect(response.headers()['content-disposition']).toContain('inline')
    // Assinatura de PDF de verdade, não a página de erro do site devolvida com 200.
    expect((await response.body()).subarray(0, 4).toString()).toBe('%PDF')
  })

  test('a URL antiga por uuid responde 301 para a URL legível', async ({ request }) => {
    const listagem = await request.get(`${API_URL}/api/v1/public/transparency-documents?per_page=50`)
    const documentos = (await listagem.json()).data as Array<{ uuid: string; title: string; path: string }>
    const documento = documentos.find((item) => item.title === TITULO)

    expect(documento, `documento "${TITULO}" não está no acervo semeado`).toBeDefined()
    expect(documento!.path).toBe(CAMINHO)

    const response = await request.get(
      `${API_URL}/api/v1/public/transparency-documents/${documento!.uuid}/download`,
      { maxRedirects: 0 },
    )

    expect(response.status()).toBe(301)
    expect(response.headers()['location']).toBe(`${SITE_URL}${CAMINHO}`)
  })

  test('ano trocado na URL responde 301 para o ano correto', async ({ request }) => {
    const response = await request.get(
      `${SITE_URL}/transparencia/documentos/2019/balanco-patrimonial-2024.pdf`,
      { maxRedirects: 0 },
    )

    expect(response.status()).toBe(301)
    expect(response.headers()['location']).toBe(`${SITE_URL}${CAMINHO}`)
  })

  test('o botão da listagem aponta para a URL canônica e baixa o arquivo', async ({ page }) => {
    await gotoSite(page, '/transparencia/documentos?year=2024&type=balance')

    const link = page.getByRole('link', { name: 'Baixar PDF' }).first()
    await expect(link).toHaveAttribute('href', CAMINHO)
    // `download` é o que mantém o botão baixando, com a URL canônica sendo a mesma que abre
    // no navegador — duas URLs para o mesmo arquivo dividiriam o sinal de busca.
    await expect(link).toHaveAttribute('download', '')

    const download = page.waitForEvent('download')
    await link.click()
    expect((await download).suggestedFilename()).toContain('.pdf')
  })
})
