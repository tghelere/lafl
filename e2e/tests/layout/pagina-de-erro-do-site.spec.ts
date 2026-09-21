import { expect, test } from '@playwright/test'

import { SITE_URL } from '../../support/env'
import { gotoSite } from '../../support/site'

/**
 * A página de erro do site público (frontend-site/app/error.vue), que substituiu a tela
 * padrão do Nuxt — aquela preta, em inglês, com a pilha de chamadas.
 *
 * **O que fica de fora, e por quê.** A outra metade do desenho é o 503: falha ou timeout da
 * API responde 503 com `Cache-Control: no-store`, e só ausência real de conteúdo responde 404
 * (ver frontend-site/app/utils/apiPageError.ts). Essa metade não é exercitável aqui — o
 * `webServer` desta bateria sobe a API e a mantém de pé, e a chamada que precisaria falhar
 * acontece no SSR, fora do alcance do `page.route()`. Ela foi conferida à mão contra o build
 * de produção na sessão 22, subindo o Nitro apontado para uma porta fechada; está registrada
 * em docs/relatorio-sessao-22.md. O que este arquivo tranca é o caminho que a bateria alcança
 * de verdade: o 404.
 */
test.describe('página de erro do site público', () => {
  test('endereço que não existe responde 404 e mostra a tela do site, não a do Nuxt', async ({ page }) => {
    const resposta = await page.goto(`${SITE_URL}/pagina-que-nao-existe`)

    expect(resposta?.status()).toBe(404)

    await expect(page.getByRole('heading', { name: 'Página não encontrada', level: 1 })).toBeVisible()
    await expect(page.getByText('Erro 404')).toBeVisible()

    // Dentro do layout do site: é o que separa esta tela da página padrão do Nuxt, que não
    // tem cabeçalho nem rodapé.
    await expect(page.getByRole('banner')).toBeVisible()
    await expect(page.getByRole('contentinfo')).toBeVisible()

    // Não indexável — página de erro não é conteúdo.
    await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', /noindex/)
  })

  test('o botão de voltar leva para a home', async ({ page }) => {
    await gotoSite(page, '/pagina-que-nao-existe')

    await page.getByRole('link', { name: 'Voltar para o início' }).click()

    await expect(page).toHaveURL(`${SITE_URL}/`)
    // Saiu mesmo do estado de erro, não só trocou o endereço na barra.
    await expect(page.getByText('Erro 404')).toHaveCount(0)
  })

  test('o 404 oferece as seções principais, e elas abrem', async ({ page }) => {
    await gotoSite(page, '/pagina-que-nao-existe')

    const secoes = page.getByRole('navigation', { name: 'Ou vá direto para uma seção' })

    // A lista sai de app/config/navigation.ts, a mesma fonte do cabeçalho e do rodapé.
    await expect(secoes.getByRole('link')).toHaveCount(5)

    await secoes.getByRole('link', { name: 'Transparência' }).click()

    await expect(page).toHaveURL(`${SITE_URL}/transparencia`)
    await expect(page.getByText('Erro 404')).toHaveCount(0)
  })

  test('página em rascunho no CMS responde 404, não 503', async ({ page }) => {
    // O contrapositivo do 503: rascunho é ausência REAL de conteúdo público — a API responde
    // 404 e o site precisa repetir esse 404. Se um dia isto virar 503, o par de regras se
    // inverteu e o buscador passa a insistir num endereço que não vai existir.
    const resposta = await page.goto(`${SITE_URL}/quem-somos/missao-visao-valores`)

    expect(resposta?.status()).toBe(404)
    await expect(page.getByText('Erro 404')).toBeVisible()
  })
})
