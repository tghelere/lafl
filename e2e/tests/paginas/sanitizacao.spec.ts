import { expect, test } from '@playwright/test'

import { AdminApi, fetchPageContent } from '../../support/api'
import { unique } from '../../support/admin'
import { pasteHtmlIntoEditor, saveContentPage } from '../../support/editor'
import { createPage, deletePage } from '../../support/fixtures'
import { serverHtml, serverPageContent } from '../../support/site'
import { SITE_URL } from '../../support/env'
import { storageStatePath } from '../../support/users'

/**
 * O site renderiza `pages.content` com `v-html` — o que ficar gravado ali é executado no
 * navegador de quem visita. A allowlist que impede isso vive no backend, aplicada em toda
 * escrita (App\Support\Html\ContentSanitizer, chamado por App\Actions\Content\SavePage), nunca
 * só no editor: o editor é conveniência, o backend é a garantia.
 *
 * Por isso os dois testes abaixo. O primeiro é o caminho da pessoa (colar no editor). O
 * segundo manda o mesmo HTML direto para a API, sem passar pelo editor — é o único dos dois
 * que continua vermelho se alguém remover a sanitização do SavePage, e portanto o que
 * realmente protege a regra.
 */
const HTML_HOSTIL = [
  '<p>Texto legítimo antes.</p>',
  '<script>window.__e2eSanitizacaoFalhou = true</script>',
  '<p><a href="javascript:window.__e2eSanitizacaoFalhou = true">clique aqui</a></p>',
  '<p>Texto legítimo depois.</p>',
].join('')

function esperaConteudoLimpo(conteudo: string): void {
  expect(conteudo).not.toContain('<script')
  expect(conteudo).not.toContain('javascript:')
  expect(conteudo).not.toContain('__e2eSanitizacaoFalhou')
}

/**
 * O documento inteiro não pode conter a sentinela em lugar nenhum — nem no HTML renderizado,
 * nem no payload do SSR que o Nuxt embute para hidratar a página.
 */
async function esperaDocumentoSemSentinela(path: string): Promise<void> {
  expect(await serverHtml(path)).not.toContain('__e2eSanitizacaoFalhou')
}

test.describe('comunicacao', () => {
  test.use({ storageState: storageStatePath('comunicacao') })

  test('colar conteúdo com script e link javascript: não chega ao site público', async ({ page }) => {
    const superAdmin = await AdminApi.as('super_admin')
    const slug = unique('e2e-colagem')
    const pagina = await createPage(superAdmin, {
      slug,
      title: `Página de colagem ${slug}`,
      content: '<p>Conteúdo inicial.</p>',
    })

    try {
      await page.goto(`/admin/paginas/${pagina.id}`)
      await pasteHtmlIntoEditor(page, HTML_HOSTIL)
      await saveContentPage(page)

      esperaConteudoLimpo(await fetchPageContent(superAdmin, pagina.id))
      esperaConteudoLimpo(await serverPageContent(`/${slug}`))
      await esperaDocumentoSemSentinela(`/${slug}`)
    } finally {
      await deletePage(superAdmin, pagina.id)
      await superAdmin.dispose()
    }
  })

  test('script e link javascript: enviados direto à API também não chegam ao site público', async ({ page }) => {
    const superAdmin = await AdminApi.as('super_admin')
    const comunicacao = await AdminApi.as('comunicacao')
    const slug = unique('e2e-api-hostil')
    const pagina = await createPage(superAdmin, {
      slug,
      title: `Página hostil ${slug}`,
      content: '<p>Conteúdo inicial.</p>',
    })

    try {
      // Sem passar pelo editor: é o cenário de um cliente qualquer falando com a API
      // (curl, script, versão antiga do painel). Quem tem de barrar aqui é a allowlist do
      // backend, e mais nada.
      const resposta = await comunicacao.put(`/api/v1/pages/${pagina.id}`, {
        slug,
        title: `Página hostil ${slug}`,
        content: HTML_HOSTIL,
        meta_title: null,
        meta_description: null,
        status: 'published',
      })
      expect(resposta.status()).toBe(200)

      const gravado = await fetchPageContent(superAdmin, pagina.id)
      esperaConteudoLimpo(gravado)
      // O texto legítimo em volta sobrevive: sanitizar não é apagar a página inteira.
      expect(gravado).toContain('Texto legítimo antes.')
      expect(gravado).toContain('Texto legítimo depois.')

      const conteudoPublicado = await serverPageContent(`/${slug}`)
      esperaConteudoLimpo(conteudoPublicado)
      expect(conteudoPublicado).toContain('Texto legítimo depois.')
      await esperaDocumentoSemSentinela(`/${slug}`)

      // E confirmado do lado do navegador: com a página aberta de verdade, nada executou.
      await page.goto(`${SITE_URL}/${slug}`)
      await expect(page.getByText('Texto legítimo depois.')).toBeVisible()
      expect(await page.evaluate(() => (window as unknown as Record<string, unknown>).__e2eSanitizacaoFalhou)).toBeUndefined()
    } finally {
      await deletePage(superAdmin, pagina.id)
      await superAdmin.dispose()
      await comunicacao.dispose()
    }
  })
})
