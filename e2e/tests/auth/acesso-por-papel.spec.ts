import { expect, test } from '@playwright/test'

import {
  DASHBOARD_EMPTY_NOTICE,
  dashboardCardTitles,
  openContentPageByTitle,
  pageAs,
  sidebar,
  sidebarLinkNames,
} from '../../support/admin'
import { storageStatePath } from '../../support/users'

/**
 * O que cada papel enxerga do painel. A fonte disso é sempre o mapa `access` que a API calcula
 * via Policy (App\Http\Resources\UserResource::accessMap) — nenhuma tela decide por papel. Os
 * testes aqui olham só para o resultado visível, que é o que quebraria se alguém trocasse o
 * mapa por uma checagem de papel no front.
 */

test.describe('financeiro', () => {
  test.use({ storageState: storageStatePath('financeiro') })

  test('vê no menu apenas Transparência, além do Início', async ({ page }) => {
    await page.goto('/admin')

    await expect.poll(() => sidebarLinkNames(page)).toEqual(['Pendências', 'Documentos'])
    await expect(sidebar(page)).toContainText('Transparência')
    await expect(sidebar(page)).not.toContainText('Páginas')
  })

  test('não recebe card de formulário no Início', async ({ page }) => {
    expect(await dashboardCardTitles(page)).toEqual([])
    await expect(page.getByText(DASHBOARD_EMPTY_NOTICE)).toBeVisible()
  })
})

test.describe('comunicacao', () => {
  test.use({ storageState: storageStatePath('comunicacao') })

  test('vê no menu apenas Páginas, além do Início', async ({ page }) => {
    await page.goto('/admin')

    await expect.poll(() => sidebarLinkNames(page)).toEqual(['Pendências', 'Páginas'])
    await expect(sidebar(page)).toContainText('Conteúdo')
    await expect(sidebar(page)).not.toContainText('Transparência')
  })

  test('abrir pela URL um recurso de outro papel mostra acesso negado dentro do layout', async ({ page }) => {
    await page.goto('/admin/transparencia')

    await expect(page.getByRole('alert')).toHaveText('Você não tem permissão para acessar este recurso.')

    // Dentro do layout: o menu continua ali e a URL não muda — quem chegou por um link
    // guardado precisa entender onde está, não ser jogado para outro lugar.
    await expect(sidebar(page)).toBeVisible()
    await expect(page).toHaveURL(/\/admin\/transparencia$/)
  })

  test('não vê opção de criar nem de excluir página', async ({ page }) => {
    await page.goto('/admin/paginas')
    await expect(page.getByRole('heading', { name: 'Páginas' })).toBeVisible()

    // Criar e excluir página mexem na estrutura do site e são de `direcao` (ver
    // App\Policies\PagePolicy) — a tela de conteúdo não oferece nem um nem outro.
    await expect(page.getByRole('link', { name: /nova página/i })).toHaveCount(0)
    await expect(page.getByRole('button', { name: /excluir/i })).toHaveCount(0)

    await openContentPageByTitle(page, 'Bazar Beneficente')
    await expect(page.getByRole('button', { name: /excluir/i })).toHaveCount(0)
  })
})

test('usuário com financeiro + contraturno vê os dois blocos e a soma dos cards', async ({ browser }) => {
  const somenteFinanceiro = await pageAs(browser, 'financeiro')
  const somenteContraturno = await pageAs(browser, 'contraturno')
  const ambos = await pageAs(browser, 'financeiro_contraturno')

  const cardsFinanceiro = await dashboardCardTitles(somenteFinanceiro)
  const cardsContraturno = await dashboardCardTitles(somenteContraturno)
  const cardsAmbos = await dashboardCardTitles(ambos)

  // Guarda contra asserção vazia: se um dia o contraturno deixar de ter cards, a igualdade
  // abaixo passaria sem provar nada.
  expect(cardsContraturno.length).toBeGreaterThan(0)
  expect(cardsAmbos).toEqual([...new Set([...cardsFinanceiro, ...cardsContraturno])])

  await expect(sidebar(ambos)).toContainText('Contraturno')
  await expect(sidebar(ambos)).toContainText('Transparência')
  await expect.poll(() => sidebarLinkNames(ambos)).toEqual([
    'Pendências',
    'Avisos do contraturno',
    'Propostas de apoio',
    'Documentos',
  ])

  await somenteFinanceiro.context().close()
  await somenteContraturno.context().close()
  await ambos.context().close()
})
