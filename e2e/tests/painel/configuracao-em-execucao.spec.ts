import { expect, request, test } from '@playwright/test'

import { ADMIN_URL } from '../../support/env'

/**
 * Cobertura da tarefa 07b, Etapa 3, e da ADR 0015
 * (docs/decisoes/0015-painel-configurado-em-tempo-de-execucao.md): o painel lê o endereço da
 * API de `/config.js`, servido fora do bundle e reescrito no servidor a cada publicação.
 *
 * Os dois casos aqui existem porque o modo de falhar é SILENCIOSO nos dois sentidos:
 *
 * - `/config.js` faltando no pacote (404) não quebra nada visível: o painel cai no valor que
 *   o Vite gravou no bundle e passa a falar com a API de OUTRO ambiente. A tela pinta, o
 *   login pede senha, e o que ele edita é o conteúdo errado.
 * - o caminho contrário — `/config.js` presente mas ignorado pelo código — daria exatamente a
 *   mesma bateria verde, porque em desenvolvimento e aqui o valor de execução é vazio de
 *   propósito e o do build é o certo. Por isso o segundo teste TROCA o arquivo por um que
 *   aponta para outro lugar: é a única forma de provar que quem manda é ele.
 */

const OUTRO_AMBIENTE = 'http://localhost:9999'

test.describe('painel — configuração em tempo de execução', () => {
  test.use({ storageState: { cookies: [], origins: [] } })

  test('/config.js é servido e define window.__LAF_CONFIG__', async () => {
    const api = await request.newContext()
    const resposta = await api.get(`${ADMIN_URL}/config.js`)

    expect(resposta.status()).toBe(200)
    expect(await resposta.text()).toContain('__LAF_CONFIG__')

    await api.dispose()
  })

  test('o painel usa o endereço de /config.js, não o gravado no bundle', async ({ page }) => {
    await page.route('**/config.js', (route) =>
      route.fulfill({
        contentType: 'application/javascript',
        body: `window.__LAF_CONFIG__ = { apiUrl: '${OUTRO_AMBIENTE}', siteUrl: '', sessionIdleTimeoutMinutes: '' }`,
      }),
    )

    const chamadas: string[] = []

    await page.route(`${OUTRO_AMBIENTE}/**`, (route) => {
      chamadas.push(route.request().url())

      return route.abort()
    })

    await page.goto('/login')

    // O login chama /sanctum/csrf-cookie antes de qualquer POST (Sanctum em SPA mode), e é a
    // primeira requisição que sai pelo baseURL do axios.
    await page.getByLabel('E-mail').fill('quem-seja@example.org')
    await page.getByLabel('Senha').fill('irrelevante')
    await page.getByRole('button', { name: 'Entrar' }).click()

    await expect
      .poll(() => chamadas, { message: 'nenhuma requisição saiu para o endereço de /config.js' })
      .not.toHaveLength(0)

    expect(chamadas[0]).toContain('/sanctum/csrf-cookie')
  })
})
