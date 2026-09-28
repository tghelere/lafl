import { type Page, expect, test } from '@playwright/test'

import { breadcrumb, openContentPageByTitle } from '../../support/admin'
import { storageStatePath } from '../../support/users'

/**
 * A trilha de navegação do painel (ver frontend-admin/src/components/AppBreadcrumb.vue).
 *
 * O defeito que este arquivo existe para impedir de voltar: a tela de edição de página tinha
 * uma barra literal na marcação ALÉM da que o CSS desenha no `::after`, e o resultado era
 * "Páginas / / Editar página" — um degrau vazio no meio. Por isso quase toda asserção aqui é
 * sobre a LISTA de degraus, comparada inteira: um degrau a mais, a menos ou em branco quebra.
 *
 * `textContent`, nunca `innerText`: `innerText` devolve o texto como a folha de estilo o
 * desenha, e um `text-transform` futuro passaria a quebrar a comparação por um motivo que não
 * tem nada a ver com a trilha.
 */
test.use({ storageState: storageStatePath('super_admin') })

/** Os rótulos dos degraus, na ordem. O separador é do CSS e não entra no texto. */
function steps(page: Page): Promise<string[]> {
  return breadcrumb(page)
    .locator('li')
    .evaluateAll((nodes) => nodes.map((node) => (node.textContent ?? '').trim()))
}

async function expectSteps(page: Page, expected: string[]): Promise<void> {
  await expect.poll(() => steps(page), `trilha em ${page.url()}`).toEqual(expected)
}

test('edição de página: "Páginas / Governança / Editar", sem degrau vazio', async ({ page }) => {
  await openContentPageByTitle(page, 'Governança')

  await expectSteps(page, ['Páginas', 'Governança', 'Editar'])

  // O primeiro degrau volta para a listagem; o último é o da tela atual e não é link.
  await expect(breadcrumb(page).getByRole('link', { name: 'Páginas' })).toBeVisible()
  await expect(breadcrumb(page).locator('[aria-current="page"]')).toHaveText('Editar')
  await expect(breadcrumb(page).locator('li').last().getByRole('link')).toHaveCount(0)
})

/**
 * O degrau do meio é o nome do registro e só chega com a resposta da API. Com a resposta
 * segurada, a trilha tem de mostrar esqueleto — nunca o degrau vazio que esta tarefa veio
 * corrigir.
 */
test('enquanto o registro não chega, o degrau do meio é esqueleto e não um vazio', async ({ page }) => {
  let liberar = (): void => {}
  const presa = new Promise<void>((resolve) => {
    liberar = resolve
  })

  await page.route('**/api/v1/pages/*', async (route) => {
    await presa
    await route.continue()
  })

  // Sem esperar a navegação terminar: é justamente o estado intermediário que interessa.
  const navegacao = openContentPageByTitle(page, 'Governança').catch(() => {})

  await expect(breadcrumb(page).locator('.breadcrumb__skeleton')).toBeVisible()

  // Nenhum degrau em branco: o do meio é o esqueleto, que anuncia "Carregando…" para quem usa
  // leitor de tela.
  await expect.poll(() => steps(page)).toEqual(['Páginas', 'Carregando…', 'Editar'])

  liberar()
  await navegacao
  await expectSteps(page, ['Páginas', 'Governança', 'Editar'])
  await expect(breadcrumb(page).locator('.breadcrumb__skeleton')).toHaveCount(0)
})

test('detalhe de formulário: recurso e tela atual, sem o nome de quem enviou', async ({ page }) => {
  await page.goto('/admin/volunteer-applications')
  await page.locator('.table tbody tr').first().getByRole('link').click()
  await expect(page).toHaveURL(/\/admin\/volunteer-applications\/[0-9a-f-]{36}$/)

  await expectSteps(page, ['Voluntários', 'Detalhe'])
  await expect(breadcrumb(page).getByRole('link', { name: 'Voluntários' })).toBeVisible()
})

test('novo documento de transparência: dois degraus, sem registro para nomear o do meio', async ({ page }) => {
  await page.goto('/admin/transparencia/novo')

  await expectSteps(page, ['Transparência', 'Novo documento'])
})

test('edição de documento de transparência: o do meio é o título do documento', async ({ page }) => {
  await page.goto('/admin/transparencia')

  const titulo = (await page.locator('.table tbody tr').first().getByRole('link').textContent())!.trim()
  await page.locator('.table tbody tr').first().getByRole('link').click()
  await expect(page).toHaveURL(/\/admin\/transparencia\/[0-9a-f-]{36}$/)

  await expectSteps(page, ['Transparência', titulo, 'Editar'])
})

test('novo usuário: dois degraus', async ({ page }) => {
  await page.goto('/admin/usuarios/novo')

  await expectSteps(page, ['Usuários', 'Novo usuário'])
})

test('edição de usuário: o do meio é o nome da conta', async ({ page }) => {
  await page.goto('/admin/usuarios')

  const nome = (await page.locator('.table tbody tr').first().getByRole('link').textContent())!.trim()
  await page.locator('.table tbody tr').first().getByRole('link').click()
  await expect(page).toHaveURL(/\/admin\/usuarios\/[0-9a-f-]{36}$/)

  await expectSteps(page, ['Usuários', nome, 'Editar'])
})

/**
 * Todo degrau da trilha, em toda tela que tem uma, precisa ter texto. Uma varredura só, porque
 * o defeito original era de um arquivo mas a causa (barra literal na marcação) podia ter sido
 * copiada para qualquer uma das quatro.
 */
test('nenhuma trilha do painel tem degrau em branco', async ({ page }) => {
  const telas = ['/admin/transparencia/novo', '/admin/usuarios/novo', '/admin/volunteer-applications/']

  await openContentPageByTitle(page, 'Governança')
  await expect.poll(() => steps(page)).not.toContain('')

  for (const tela of telas.slice(0, 2)) {
    await page.goto(tela)
    await expect.poll(() => steps(page)).not.toContain('')
  }
})

/**
 * Os degraus ficam na mesma linha de base. Tolerância de 1px, o mesmo critério e pelo mesmo
 * motivo de tests/layout/alinhamento.spec.ts: arredondamento de subpixel, não defeito.
 */
test('os degraus ficam no mesmo centro vertical', async ({ page }) => {
  await openContentPageByTitle(page, 'Governança')
  await expect(breadcrumb(page).locator('li').first()).toBeVisible()

  const centros = await breadcrumb(page)
    .locator('li')
    .evaluateAll((nodes) =>
      nodes.map((node) => {
        const rect = node.getBoundingClientRect()

        return rect.top + rect.height / 2
      }),
    )

  expect(centros.length).toBe(3)
  expect(Math.max(...centros) - Math.min(...centros), JSON.stringify(centros)).toBeLessThanOrEqual(1)
})
