import { expect, test } from '@playwright/test'

import { SITE_SEM_API_URL } from '../../support/env'

/**
 * A home com a API fora do ar (ver playwright.config.ts, projeto `site-sem-api`).
 *
 * O caso "com dados" já está coberto na bateria normal (numeros-calculados.spec.ts e
 * marca.spec.ts, contra a API de pé) — o que falta, e só é alcançável aqui, é o caminho em que
 * `useInstitutionFacts` falha: a home precisa continuar respondendo 200 (ela não entra na
 * regra do 503, ver docs/decisoes/0019-falha-da-api-responde-503-nao-404.md) e mostrar uma
 * frase alternativa completa no lugar da fundação e do tempo de bazar — nunca uma linha de
 * registro sem número, que é o que sobraria se o `v-if` fosse removido sem substituto.
 */
test.describe('home sem API', () => {
  test('responde 200 e mostra a frase alternativa completa, sem número', async ({ page }) => {
    const resposta = await page.goto(SITE_SEM_API_URL)

    expect(resposta?.status()).toBe(200)

    // Só as duas linhas fixas (turmas e repasse do convênio, sem depender da API) continuam
    // como LedgerLine. As outras duas (fundação e bazar) não renderizam sem os números que
    // precisam — teria sobrado uma linha truncada (valor ou rótulo sem data) — e dão lugar à
    // frase alternativa abaixo.
    await expect(page.locator('.ledger__item')).toHaveCount(2)
    await expect(page.locator('.ledger__fallback')).toBeVisible()

    await expect(
      page.getByText(
        'A fundação da associação e o tempo de funcionamento do Bazar Beneficente estão temporariamente indisponíveis',
      ),
    ).toBeVisible()

    // O resto da home continua de pé — conteúdo fixo no .vue, não depende de
    // useInstitutionFacts.
    await expect(page.getByRole('heading', { name: 'Educação Infantil', level: 2 })).toBeVisible()
  })
})
