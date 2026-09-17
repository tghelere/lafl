import { expect, test } from '@playwright/test'

import { AdminApi, fetchPageContent } from '../../support/api'
import { breadcrumb, openContentPageByTitle, unique } from '../../support/admin'
import { editor, replaceEditorText, saveContentPage } from '../../support/editor'
import { createPage, deletePage } from '../../support/fixtures'
import { serverHtml } from '../../support/site'
import { storageStatePath } from '../../support/users'

test.describe('comunicacao', () => {
  test.use({ storageState: storageStatePath('comunicacao') })

  /**
   * A regressão mais cara de todas: abrir uma página e salvar sem mexer em nada não pode
   * mudar o conteúdo publicado. Quem escreve no painel salva por reflexo, e uma ida e volta
   * que perde `class="btn"` ou o `target`/`rel` de um link externo reescreveria silenciosamente
   * páginas que ninguém pediu para mudar.
   *
   * A comparação é pelo endpoint administrativo, dos dois lados — o que o editor mostra na
   * tela já passou pelo Tiptap, então comparar pela interface compararia a normalização do
   * editor com ela mesma.
   */
  const PAGINAS_DE_IDA_E_VOLTA = [
    {
      descricao: 'com botão (class="btn")',
      titulo: 'Página de teste com botão',
      // O que não pode se perder na ida e volta. Sem isto a comparação "antes === depois"
      // passaria igual se o editor tivesse apagado os dois lados.
      precisaConter: ['class="btn btn--primary"', 'href="/contraturno/inscricao"'],
    },
    {
      descricao: 'com link externo',
      titulo: 'Página de teste com link externo',
      precisaConter: ['href="https://wa.me/5543999500183"', 'target="_blank"', 'rel="noopener noreferrer"'],
    },
  ] as const

  for (const { descricao, titulo, precisaConter } of PAGINAS_DE_IDA_E_VOLTA) {
    test(`abrir e salvar sem alterar não muda o conteúdo da página ${descricao}`, async ({ page }) => {
      const api = await AdminApi.as('comunicacao')

      await openContentPageByTitle(page, titulo)
      const uuid = page.url().split('/').at(-1)!

      const antes = await fetchPageContent(api, uuid)
      for (const trecho of precisaConter) {
        expect(antes, 'o conteúdo de partida precisa ter o que o teste diz proteger').toContain(trecho)
      }

      await expect(editor(page)).not.toBeEmpty()
      await saveContentPage(page)

      const depois = await fetchPageContent(api, uuid)
      await api.dispose()

      expect(depois).toBe(antes)
    })
  }

  test('editar um parágrafo aparece no site público na requisição seguinte', async ({ page }) => {
    const api = await AdminApi.as('comunicacao')
    const superAdmin = await AdminApi.as('super_admin')
    const slug = unique('e2e-edicao')
    const pagina = await createPage(superAdmin, {
      slug,
      title: `Página de edição ${slug}`,
      content: '<p>Texto original que ninguém leu ainda.</p>',
    })

    try {
      const novoTexto = `Texto trocado pela bateria em ${new Date().toISOString()}.`

      await expect.poll(async () => (await serverHtml(`/${slug}`)).includes('Texto original que ninguém leu ainda.')).toBe(true)

      await page.goto(`/admin/paginas/${pagina.id}`)
      await replaceEditorText(page, novoTexto)
      await saveContentPage(page)

      // Sem espera artificial: o backend invalida o cache de 10 minutos da página ao salvar
      // (App\Actions\Content\SavePage), e o site renderiza no servidor a cada requisição.
      const html = await serverHtml(`/${slug}`)
      expect(html).toContain(novoTexto)
      expect(html).not.toContain('Texto original que ninguém leu ainda.')

      expect(await fetchPageContent(api, pagina.id)).toContain(novoTexto)
    } finally {
      await deletePage(superAdmin, pagina.id)
      await api.dispose()
      await superAdmin.dispose()
    }
  })

  test('busca por título ignora maiúsculas e minúsculas', async ({ page }) => {
    await page.goto('/admin/paginas')

    // No PostgreSQL o LIKE é sensível a caixa — foi um bug real, que só apareceu quando a
    // suíte deixou de rodar em SQLite (ver CLAUDE.md, "Armadilhas conhecidas"). Aqui a busca
    // roda contra o banco de verdade, pela tela de verdade.
    await page.getByLabel('Título').fill('BAZAR BENEFICENTE')
    await page.getByRole('button', { name: 'Filtrar' }).click()

    await expect(page.getByRole('link', { name: 'Bazar Beneficente', exact: true })).toBeVisible()

    await page.getByLabel('Título').fill('bazar beneficente')
    await page.getByRole('button', { name: 'Filtrar' }).click()

    await expect(page.getByRole('link', { name: 'Bazar Beneficente', exact: true })).toBeVisible()
  })

  test('sair da página com alteração não salva pede confirmação', async ({ page }) => {
    const superAdmin = await AdminApi.as('super_admin')
    const slug = unique('e2e-saida')
    const pagina = await createPage(superAdmin, {
      slug,
      title: `Página de saída ${slug}`,
      content: '<p>Conteúdo que vai ser mexido e não salvo.</p>',
    })

    try {
      await page.goto(`/admin/paginas/${pagina.id}`)
      await replaceEditorText(page, 'Uma alteração que ninguém vai salvar.')

      // Recusar o aviso mantém a pessoa onde estava, com o texto ainda ali.
      const recusa = new Promise<string>((resolve) => {
        page.once('dialog', (dialog) => {
          const mensagem = dialog.message()
          void dialog.dismiss().then(() => resolve(mensagem))
        })
      })
      await breadcrumb(page).getByRole('link', { name: 'Páginas' }).click()

      expect(await recusa).toBe('Há alterações não salvas nesta página. Sair mesmo assim?')
      await expect(page).toHaveURL(new RegExp(`/admin/paginas/${pagina.id}$`))
      await expect(editor(page)).toContainText('Uma alteração que ninguém vai salvar.')

      // Aceitar sai de verdade, e a alteração é descartada.
      page.once('dialog', (dialog) => void dialog.accept())
      await breadcrumb(page).getByRole('link', { name: 'Páginas' }).click()

      await expect(page).toHaveURL(/\/admin\/paginas$/)
      expect(await fetchPageContent(superAdmin, pagina.id)).toContain('Conteúdo que vai ser mexido e não salvo.')
    } finally {
      await deletePage(superAdmin, pagina.id)
      await superAdmin.dispose()
    }
  })
})
