import { expect, test } from '@playwright/test'

import { acceptNextDialog, unique } from '../../support/admin'
import { gotoSite } from '../../support/site'
import { storageStatePath } from '../../support/users'

/**
 * PDF mínimo de uma página em branco, o mesmo formato que os seeders usam — nenhum arquivo
 * real da instituição entra no repositório (CLAUDE.md, regra 10). O upload precisa ser um PDF
 * de verdade porque a validação da API é por mimetype, não por extensão.
 */
const PDF_DE_MENTIRA = Buffer.from(
  '%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n' +
    '2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n' +
    '3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << >> >>\nendobj\n' +
    'trailer\n<< /Size 4 /Root 1 0 R >>\n%%EOF\n',
)

test.describe('financeiro', () => {
  test.use({ storageState: storageStatePath('financeiro') })

  test('sobe um PDF, publica e o documento aparece no site; despublica e some', async ({ page }) => {
    const titulo = `Balanço patrimonial ${unique('e2e')}`

    await page.goto('/admin/transparencia/novo')
    await page.getByLabel('Título').fill(titulo)
    await page.getByLabel('Ano').fill('2025')
    await page.getByLabel('Tipo').selectOption('balance')
    await page.getByLabel('Arquivo (PDF, até 20 MB)').setInputFiles({
      name: 'balanco-e2e.pdf',
      mimeType: 'application/pdf',
      buffer: PDF_DE_MENTIRA,
    })
    await page.getByRole('button', { name: 'Salvar' }).click()

    await expect(page.getByText('Documento cadastrado.')).toBeVisible()

    // Nasce como rascunho (a caixa "Publicar imediatamente" ficou desmarcada) — publicar é um
    // segundo ato, deliberado.
    await page.getByLabel('Ano').fill('2025')
    await page.getByRole('button', { name: 'Filtrar' }).click()
    await page.getByRole('link', { name: titulo }).click()
    await expect(page.getByText('Rascunho', { exact: true })).toBeVisible()

    await gotoSite(page, '/transparencia/documentos')
    await expect(page.getByRole('heading', { name: titulo })).toHaveCount(0)

    await page.goBack()
    await page.getByRole('button', { name: 'Publicar' }).click()
    await expect(page.getByText('Publicado', { exact: true })).toBeVisible()

    await gotoSite(page, '/transparencia/documentos')
    await expect(page.getByRole('heading', { name: titulo })).toBeVisible()

    await page.goBack()
    await page.getByRole('button', { name: 'Despublicar' }).click()
    await expect(page.getByText('Rascunho', { exact: true })).toBeVisible()

    await gotoSite(page, '/transparencia/documentos')
    await expect(page.getByRole('heading', { name: titulo })).toHaveCount(0)
  })

  test('o filtro por ano alcança documento que não está na primeira página', async ({ page }) => {
    // Documento plantado pelo E2eSeeder com `updated_at` de cinco anos atrás: a listagem
    // ordena por updated_at decrescente, então ele é sempre o último do acervo — e com mais de
    // 15 documentos, sempre fora da primeira página.
    const titulo = 'Prestação de contas do convênio — CEI Anália Franco 2019'

    await page.goto('/admin/transparencia')
    await expect(page.getByRole('heading', { name: 'Transparência' })).toBeVisible()
    await expect(page.getByRole('link', { name: titulo })).toHaveCount(0)

    await page.getByLabel('Ano').fill('2019')
    await page.getByRole('button', { name: 'Filtrar' }).click()

    await expect(page.getByRole('link', { name: titulo })).toBeVisible()
    await expect(page).toHaveURL(/year=2019/)
  })

  test('excluir documento pede confirmação e remove da listagem', async ({ page }) => {
    const titulo = `Edital ${unique('e2e')}`

    await page.goto('/admin/transparencia/novo')
    await page.getByLabel('Título').fill(titulo)
    await page.getByLabel('Ano').fill('2025')
    await page.getByLabel('Tipo').selectOption('notice')
    await page.getByLabel('Arquivo (PDF, até 20 MB)').setInputFiles({
      name: 'edital-e2e.pdf',
      mimeType: 'application/pdf',
      buffer: PDF_DE_MENTIRA,
    })
    await page.getByRole('button', { name: 'Salvar' }).click()
    await expect(page.getByText('Documento cadastrado.')).toBeVisible()

    await page.getByLabel('Ano').fill('2025')
    await page.getByRole('button', { name: 'Filtrar' }).click()
    await page.getByRole('link', { name: titulo }).click()

    const dialogo = acceptNextDialog(page)
    await page.getByRole('button', { name: 'Excluir' }).click()
    await dialogo

    await expect(page.getByText('Documento excluído.')).toBeVisible()
    await expect(page.getByRole('link', { name: titulo })).toHaveCount(0)
  })
})
