import { type Page, expect, test } from '@playwright/test'

import { openContentPageByTitle } from '../../support/admin'
import { readSubmissionRow } from '../../support/submissions'
import { storageStatePath } from '../../support/users'

/**
 * O cabeçalho de tela do painel (ver frontend-admin/src/components/PageHeader.vue).
 *
 * Antes cada tela montava o seu: a edição de página com um flex próprio, a de documento e a
 * de usuário soltando o selo num `<span>` avulso ABAIXO do título (caía para a linha de
 * baixo), e as listagens pendurando o botão "Novo" dentro da barra de filtro. O que este
 * arquivo trava é o resultado: um cabeçalho por tela, e o selo no centro vertical do título.
 *
 * Tolerância de 1px, mesmo critério de tests/layout/alinhamento.spec.ts — arredondamento de
 * subpixel, não defeito (os defeitos desta família mediam 4px a 12px, ou uma linha inteira).
 */
const TOLERANCE_PX = 1

test.use({ storageState: storageStatePath('super_admin') })

async function centroVertical(page: Page, selector: string): Promise<number> {
  const box = await page.locator(selector).first().boundingBox()

  expect(box, `sem caixa para ${selector}`).not.toBeNull()

  return box!.y + box!.height / 2
}

/** O selo está na mesma linha do título e no mesmo centro vertical que ele. */
async function expectSeloCentradoNoTitulo(page: Page): Promise<void> {
  await expect(page.locator('.page-header .badge').first()).toBeVisible()

  const titulo = await centroVertical(page, '.page-header__title')
  const selo = await centroVertical(page, '.page-header .badge')

  expect(Math.abs(titulo - selo), `centro do título ${titulo} vs. centro do selo ${selo}`).toBeLessThanOrEqual(
    TOLERANCE_PX,
  )
}

const TELAS_SEM_SELO = [
  ['/admin', 'Início'],
  ['/admin/paginas', 'Páginas'],
  ['/admin/transparencia', 'Transparência'],
  ['/admin/usuarios', 'Usuários'],
  ['/admin/auditoria', 'Auditoria'],
  ['/conta', 'Minha conta'],
  ['/admin/volunteer-applications', 'Voluntários'],
  ['/admin/transparencia/novo', 'Novo documento'],
  ['/admin/usuarios/novo', 'Novo usuário'],
]

for (const [path, titulo] of TELAS_SEM_SELO) {
  test(`${path} tem um cabeçalho de tela só, com "${titulo}" no <h1>`, async ({ page }) => {
    await page.goto(path)

    await expect(page.locator('.page-header')).toHaveCount(1)
    await expect(page.locator('.page-header__title')).toHaveText(titulo!)

    // O h1 da tela é o do cabeçalho — nenhum outro título de nível 1 no conteúdo.
    await expect(page.getByRole('main').locator('h1')).toHaveCount(1)
  })
}

test('edição de página: selo Publicada centrado no título', async ({ page }) => {
  await openContentPageByTitle(page, 'Governança')

  await expect(page.locator('.page-header__title')).toHaveText('Governança')
  await expect(page.locator('.page-header .badge')).toHaveText('Publicada')
  await expectSeloCentradoNoTitulo(page)
})

test('edição de página em rascunho: selo Rascunho centrado no título', async ({ page }) => {
  await openContentPageByTitle(page, 'Missão, Visão e Valores')

  await expect(page.locator('.page-header .badge')).toHaveText('Rascunho')
  await expectSeloCentradoNoTitulo(page)
})

test('edição de documento de transparência: selo centrado no título', async ({ page }) => {
  await page.goto('/admin/transparencia')
  await page.locator('.table tbody tr').first().getByRole('link').click()
  await expect(page).toHaveURL(/\/admin\/transparencia\/[0-9a-f-]{36}$/)

  await expectSeloCentradoNoTitulo(page)
})

test('edição de usuário: selo Ativo centrado no título', async ({ page }) => {
  await page.goto('/admin/usuarios')
  await page.locator('.table tbody tr').first().getByRole('link').click()
  await expect(page).toHaveURL(/\/admin\/usuarios\/[0-9a-f-]{36}$/)

  await expect(page.locator('.page-header .badge')).toHaveText(/Ativo|Inativo/)
  await expectSeloCentradoNoTitulo(page)
})

test('detalhe de formulário: selo de status centrado no título', async ({ page }) => {
  await page.goto('/admin/contact-messages')
  await readSubmissionRow(page).getByRole('link').click()
  await expect(page).toHaveURL(/\/admin\/contact-messages\/[0-9a-f-]{36}$/)

  await expectSeloCentradoNoTitulo(page)
})

/**
 * As ações da tela (novo registro, abrir no site, desmarcar leitura) agora ficam no
 * cabeçalho. Em largura de desktop elas dividem a linha com o título — é isso que se mede:
 * mesma linha, não uma abaixo da outra.
 */
test('a ação "Novo documento" fica na mesma linha do título', async ({ page }) => {
  await page.goto('/admin/transparencia')

  const acao = page.locator('.page-header__actions').getByRole('link', { name: 'Novo documento' })
  await expect(acao).toBeVisible()

  const titulo = await centroVertical(page, '.page-header__title')
  const botao = await centroVertical(page, '.page-header__actions .btn')

  expect(Math.abs(titulo - botao), 'centro do título vs. centro do botão de ação').toBeLessThanOrEqual(TOLERANCE_PX)

  // A barra de filtro não é mais o lugar do botão "Novo" — era de onde ele saía desalinhado.
  await expect(page.locator('.filter-bar').getByRole('link')).toHaveCount(0)
})

test('a ação "Novo usuário" fica na mesma linha do título', async ({ page }) => {
  await page.goto('/admin/usuarios')

  await expect(page.locator('.page-header__actions').getByRole('link', { name: 'Novo usuário' })).toBeVisible()
  await expect(page.locator('.filter-bar').getByRole('link')).toHaveCount(0)
})
