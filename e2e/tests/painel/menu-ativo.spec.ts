import { type Page, expect, test } from '@playwright/test'

import { openContentPageByTitle, sidebar } from '../../support/admin'
import { readSubmissionRow } from '../../support/submissions'
import { storageStatePath } from '../../support/users'

/**
 * O item da navegação lateral tem de continuar aceso nas ROTAS FILHAS — detalhe de um
 * registro, edição de uma página, edição de um documento. Enquanto quem decidia isso era o
 * `.router-link-active` do vue-router (comparação de URL), abrir `/admin/paginas/{uuid}`
 * apagava "Páginas" do menu, e a pessoa perdia a referência de onde estava.
 *
 * Quem decide agora é `meta.section` da rota (ver frontend-admin/src/router/meta.ts). Os
 * testes abaixo olham as duas marcas ao mesmo tempo, porque as duas importam e por motivos
 * diferentes: a classe é o realce visual, e `aria-current="page"` é o que um leitor de tela
 * anuncia.
 */
test.use({ storageState: storageStatePath('super_admin') })

/** Os itens do menu que estão acesos agora, pelo rótulo. */
async function acesos(page: Page): Promise<string[]> {
  return sidebar(page)
    .locator('.app-sidebar__link--active')
    .evaluateAll((nodes) =>
      nodes.map((node) => {
        const copy = node.cloneNode(true) as HTMLElement
        copy.querySelector('.app-sidebar__badge')?.remove()

        // textContent, nunca innerText: o rótulo é lido como está no DOM, sem o
        // text-transform da folha de estilo entrar na comparação.
        return (copy.textContent ?? '').trim()
      }),
    )
}

/** Os itens do menu marcados com aria-current="page". */
async function anunciadosComoAtual(page: Page): Promise<string[]> {
  return sidebar(page)
    .locator('[aria-current="page"]')
    .evaluateAll((nodes) =>
      nodes.map((node) => {
        const copy = node.cloneNode(true) as HTMLElement
        copy.querySelector('.app-sidebar__badge')?.remove()

        return (copy.textContent ?? '').trim()
      }),
    )
}

async function expectMenuAceso(page: Page, label: string): Promise<void> {
  await expect.poll(() => acesos(page), `item aceso em ${page.url()}`).toEqual([label])
  await expect.poll(() => anunciadosComoAtual(page), `aria-current em ${page.url()}`).toEqual([label])
}

test('listagem de páginas e edição de uma página acendem o mesmo item', async ({ page }) => {
  await page.goto('/admin/paginas')
  await expectMenuAceso(page, 'Páginas')

  await openContentPageByTitle(page, 'Governança')
  await expect(page).toHaveURL(/\/admin\/paginas\/[0-9a-f-]{36}$/)
  await expectMenuAceso(page, 'Páginas')
})

test('listagem de formulário e detalhe de um registro acendem o mesmo item', async ({ page }) => {
  await page.goto('/admin/contact-messages')
  await expectMenuAceso(page, 'Mensagens de contato')

  await readSubmissionRow(page).getByRole('link').click()
  await expect(page).toHaveURL(/\/admin\/contact-messages\/[0-9a-f-]{36}$/)
  await expectMenuAceso(page, 'Mensagens de contato')
})

test('cada listagem de formulário acende só o seu item, não os outros quatro', async ({ page }) => {
  await page.goto('/admin/volunteer-applications')
  await expectMenuAceso(page, 'Voluntários')

  await page.goto('/admin/pickup-requests')
  await expectMenuAceso(page, 'Pedidos de coleta')
})

test('transparência continua acesa em "novo" e na edição de um documento', async ({ page }) => {
  await page.goto('/admin/transparencia')
  await expectMenuAceso(page, 'Documentos')

  await page.goto('/admin/transparencia/novo')
  await expectMenuAceso(page, 'Documentos')

  await page.goto('/admin/transparencia')
  await page.locator('.table tbody tr').first().getByRole('link').click()
  await expect(page).toHaveURL(/\/admin\/transparencia\/[0-9a-f-]{36}$/)
  await expectMenuAceso(page, 'Documentos')
})

test('usuários continua aceso em "novo" e na edição de uma conta', async ({ page }) => {
  await page.goto('/admin/usuarios')
  await expectMenuAceso(page, 'Usuários')

  await page.goto('/admin/usuarios/novo')
  await expectMenuAceso(page, 'Usuários')

  await page.goto('/admin/usuarios')
  await page.locator('.table tbody tr').first().getByRole('link').click()
  await expect(page).toHaveURL(/\/admin\/usuarios\/[0-9a-f-]{36}$/)
  await expectMenuAceso(page, 'Usuários')
})

test('o Início acende só na tela Início', async ({ page }) => {
  await page.goto('/admin')
  await expectMenuAceso(page, 'Pendências')
})

/**
 * /conta não tem item no menu (é autosserviço, fora do mapa de acesso) — nada deve acender, e
 * muito menos o Início, que é a armadilha de qualquer comparação por prefixo de URL.
 */
test('uma tela fora do menu não acende item nenhum', async ({ page }) => {
  await page.goto('/conta')
  await expect(page.getByRole('heading', { name: 'Minha conta' })).toBeVisible()

  await expect.poll(() => acesos(page)).toEqual([])
})
