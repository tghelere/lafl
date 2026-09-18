import { expect, test } from '@playwright/test'

import { SITE_URL } from '../../support/env'
import { serverHtml } from '../../support/site'

/**
 * Cobertura da tarefa 06 (docs/tarefas/06-seo-e-pdfs-da-transparencia.md), etapa 4: a página
 * de documentos anuncia o recorte que está mostrando (ano, tipo, página) e não deixa duas
 * URLs diferentes se apresentarem como a mesma coisa.
 */
function titulo(html: string): string {
  return html.match(/<title[^>]*>([\s\S]*?)<\/title>/)![1]!
}

function canonical(html: string): string | null {
  return html.match(/<link[^>]*rel="canonical"[^>]*href="([^"]*)"/i)?.[1] ?? null
}

function descricao(html: string): string | null {
  return html.match(/<meta[^>]*name="description"[^>]*content="([^"]*)"/i)?.[1] ?? null
}

test.describe('/transparencia/documentos', () => {
  test('sem filtro, o título e o canônico são os do acervo inteiro', async () => {
    const html = await serverHtml('/transparencia/documentos')

    expect(titulo(html)).toBe('Documentos — Transparência — Lar Anália Franco')
    expect(canonical(html)).toBe(`${SITE_URL}/transparencia/documentos`)
  })

  test('com filtro, título, descrição e canônico refletem o recorte', async () => {
    const html = await serverHtml('/transparencia/documentos?year=2024&type=balance')

    expect(titulo(html)).toBe('Balanços de 2024 — Transparência — Lar Anália Franco')
    expect(descricao(html)).toContain('Balanços do Lar Anália Franco referentes a 2024')
    expect(canonical(html)).toBe(`${SITE_URL}/transparencia/documentos?year=2024&type=balance`)
  })

  test('parâmetro que a API ignora não vira endereço canônico próprio', async () => {
    // A API ignora tipo desconhecido e devolve o acervo inteiro. Sem esta regra, cada
    // ?type=qualquer-coisa seria uma URL nova com o mesmo conteúdo.
    const html = await serverHtml('/transparencia/documentos?type=nao-existe')

    expect(canonical(html)).toBe(`${SITE_URL}/transparencia/documentos`)
  })

  test('page=1 não entra no canônico; page=2 entra', async () => {
    const primeira = await serverHtml('/transparencia/documentos?page=1')
    expect(canonical(primeira)).toBe(`${SITE_URL}/transparencia/documentos`)

    const segunda = await serverHtml('/transparencia/documentos?page=2')
    expect(canonical(segunda)).toBe(`${SITE_URL}/transparencia/documentos?page=2`)
    expect(titulo(segunda)).toContain('página 2')
  })

  test('os links de paginação são âncoras de verdade e levam à página seguinte', async ({ page }) => {
    // O E2eSeeder semeia 21 documentos e a listagem pública mostra 20 — a segunda página
    // existe justamente para este caso.
    await page.goto(`${SITE_URL}/transparencia/documentos`)

    const proxima = page.getByRole('link', { name: 'Próxima →' })
    await expect(proxima).toHaveAttribute('href', '/transparencia/documentos?page=2')
    await expect(proxima).toHaveAttribute('rel', 'next')

    await proxima.click()
    await expect(page).toHaveURL(`${SITE_URL}/transparencia/documentos?page=2`)
    await expect(page.getByText('Página 2 de 2')).toBeVisible()
    await expect(page.getByRole('link', { name: '← Anterior' })).toHaveAttribute('rel', 'prev')
  })

  test('o filtro continua funcionando sem JavaScript, pela própria URL', async () => {
    const html = await serverHtml('/transparencia/documentos?type=bylaws')

    // O servidor já entrega o recorte aplicado: nada aqui depende do navegador.
    expect(html).toContain('Estatuto social')
    expect(html).not.toContain('Edital de chamamento público 2024')
  })
})
