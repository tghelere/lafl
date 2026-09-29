import { expect, test } from '@playwright/test'

import { AdminApi } from '../../support/api'
import { storageStatePath } from '../../support/users'

/**
 * A biblioteca sem nenhuma imagem é um estado normal (todo ambiente novo começa assim), e não
 * uma falha. Na sessão 28, `/admin/imagens` mostrava "Não foi possível carregar as imagens"
 * com a biblioteca vazia. A causa era o banco de desenvolvimento sem a tabela `media` (500 da
 * API, ver README, "Depois de puxar código novo"), mas a tela também não tinha estado vazio
 * próprio. Este spec trava a distinção: vazio convida a enviar, e erro fica para falha real.
 *
 * O nome põe este arquivo antes de `biblioteca.spec.ts` na ordem da bateria (um worker, ordem
 * alfabética), então a biblioteca ainda está vazia quando ele roda. Se algum spec anterior
 * passar a enviar imagem, o `beforeAll` limpa o que não estiver em uso. O banco de e2e é
 * descartável e recriado a cada execução.
 */
test.describe('biblioteca vazia', () => {
  test.use({ storageState: storageStatePath('comunicacao') })

  test.beforeAll(async () => {
    const direcaoApi = await AdminApi.as('direcao')

    try {
      const response = await direcaoApi.get('/api/v1/media', { per_page: 60 })
      const { data } = (await response.json()) as { data: { id: string }[] }

      for (const item of data) {
        await direcaoApi.delete(`/api/v1/media/${item.id}`)
      }

      const depois = (await (await direcaoApi.get('/api/v1/media')).json()) as { meta: { total: number } }
      expect(depois.meta.total, 'a biblioteca precisa estar vazia; sobrou imagem em uso por alguma página').toBe(0)
    } finally {
      await direcaoApi.dispose()
    }
  })

  test('convida a enviar a primeira imagem, sem mensagem de erro e sem busca', async ({ page }) => {
    await page.goto('/admin/imagens')

    await expect(page.getByRole('heading', { name: 'A biblioteca ainda não tem imagens' })).toBeVisible()
    await expect(page.getByText('Não foi possível carregar')).toHaveCount(0)
    await expect(page.getByRole('search')).toHaveCount(0)

    await page.getByRole('link', { name: 'Enviar a primeira imagem' }).click()
    await expect(page).toHaveURL(/\/admin\/imagens\/nova$/)
  })

  test('busca sem resultado não vira convite', async ({ page }) => {
    await page.goto('/admin/imagens?search=nada-assim')

    await expect(page.getByText('Nenhuma imagem encontrada para essa busca.')).toBeVisible()
    await expect(page.getByRole('heading', { name: 'A biblioteca ainda não tem imagens' })).toHaveCount(0)
    await expect(page.getByRole('search')).toBeVisible()
  })

  test('falha da API continua sendo erro, não biblioteca vazia', async ({ page }) => {
    // A única resposta simulada deste spec: uma falha de servidor não se produz sob demanda
    // na pilha real. O que está sob teste é a tela não confundir as duas coisas.
    await page.route(/\/api\/v1\/media(\?|$)/, (route) =>
      route.fulfill({ status: 500, contentType: 'application/json', body: '{"message":"Server Error"}' }),
    )
    await page.goto('/admin/imagens')

    await expect(page.getByRole('alert').filter({ hasText: 'Não foi possível carregar as imagens' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'A biblioteca ainda não tem imagens' })).toHaveCount(0)
  })
})
