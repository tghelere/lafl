import { expect, test } from '@playwright/test'

import { SITE_SEM_API_URL } from '../../support/env'

/**
 * A outra metade de docs/decisoes/0019-falha-da-api-responde-503-nao-404.md, que
 * tests/layout/pagina-de-erro-do-site.spec.ts (bateria normal) não alcança: com a API fora do
 * ar (ver playwright.config.ts, projeto `site-sem-api`), toda página que lê o CMS responde 503
 * com `Cache-Control: no-store`, e a tela é a de app/error.vue — sem os links de seção (eles
 * apontam para páginas que também dependem da API), com telefone e WhatsApp da sede no lugar,
 * lidos de app/config/institution.ts.
 */
const PAGINAS = [
  { path: '/quem-somos', descricao: 'página do CMS' },
  { path: '/transparencia/documentos', descricao: 'listagem que consulta a API' },
]

test.describe('páginas que dependem da API, com a API fora do ar', () => {
  for (const { path, descricao } of PAGINAS) {
    test(`${descricao} (${path}) responde 503 com Cache-Control: no-store`, async ({ page }) => {
      const resposta = await page.goto(`${SITE_SEM_API_URL}${path}`)

      expect(resposta?.status()).toBe(503)
      expect(resposta?.headers()['cache-control']).toBe('no-store')

      await expect(page.getByRole('heading', { name: 'Instabilidade momentânea', level: 1 })).toBeVisible()
      await expect(
        page.getByText('O site está com uma instabilidade momentânea. Tente novamente em alguns minutos.'),
      ).toBeVisible()

      // Sem links de seção: no 503 todos apontariam para páginas que também dependem da API.
      await expect(page.getByRole('navigation', { name: 'Ou vá direto para uma seção' })).toHaveCount(0)

      // Telefone e WhatsApp da sede, no lugar — sempre visíveis, nunca lidos da API.
      const contato = page.locator('.erro__contato')
      await expect(contato.getByRole('link', { name: '(43) 3325-8060' })).toHaveAttribute(
        'href',
        'tel:+554333258060',
      )
      const whatsapp = contato.getByRole('link', { name: 'WhatsApp' })
      await expect(whatsapp).toHaveAttribute('href', /^https:\/\/wa\.me\/5543999500183\?text=/)
      await expect(whatsapp).toHaveAttribute('target', '_blank')
    })
  }

  test('a tela de instabilidade cabe em 375px e em 1280px, sem estouro horizontal', async ({ page }) => {
    await page.goto(`${SITE_SEM_API_URL}/quem-somos`)
    await expect(page.getByRole('heading', { name: 'Instabilidade momentânea', level: 1 })).toBeVisible()

    for (const width of [375, 1280]) {
      await page.setViewportSize({ width, height: 800 })

      const larguraDoDocumento = await page.evaluate(() => document.documentElement.scrollWidth)
      expect(larguraDoDocumento, `${width}px: rolagem horizontal na tela de instabilidade`).toBeLessThanOrEqual(
        width,
      )

      await expect(page.locator('.erro__contato')).toBeVisible()
    }
  })
})
