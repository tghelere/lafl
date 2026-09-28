import { expect, test } from '@playwright/test'

import { pageAs } from '../../support/admin'
import { detailField, detailStatusBadge, submissionRow, submissionTable } from '../../support/submissions'
import { storageStatePath } from '../../support/users'

/**
 * Leitura compartilhada pela equipe (sessão 25, item 2 — ver
 * docs/decisoes/0021-leitura-separada-do-status-de-atendimento.md).
 *
 * Fluxo principal: a listagem mostra o que ninguém abriu, abrir marca como lido, o contador do
 * menu cai, "Marcar como não lido" desfaz tudo.
 *
 * Por que aqui e não em teste de componente: nada disto acontece no painel. Quem marca como lido
 * é o `show()` da API, como efeito de abrir a tela; o contador vem de um segundo endpoint que
 * conta linhas no Postgres; e a leitura é COMPARTILHADA, o que só se prova com duas sessões
 * diferentes vendo o mesmo registro. Com a API simulada, os três seriam afirmações do próprio
 * teste.
 */
const RECURSO = '/admin/contact-messages'

/** O contador que a navegação lateral mostra ao lado de "Mensagens de contato" — 0 se não houver. */
async function contadorDoMenu(page: import('@playwright/test').Page): Promise<number> {
  const link = page.getByRole('navigation', { name: 'Navegação principal' })
    .getByRole('link', { name: /Mensagens de contato/ })

  await expect(link).toBeVisible()

  const texto = await link.textContent()
  const numero = /(\d+)/.exec(texto ?? '')

  return numero ? Number(numero[1]) : 0
}

/** Primeira linha não lida da listagem, pelo indicador que só ela tem. */
function primeiraLinhaNaoLida(page: import('@playwright/test').Page) {
  return submissionTable(page).locator('tbody tr.table__row--unread').first()
}

test.describe('formulários — lido e não lido', () => {
  test.use({ storageState: storageStatePath('direcao') })

  test('abrir um não lido marca como lido, e o contador do menu cai', async ({ page }) => {
    await page.goto(RECURSO)
    await expect(submissionTable(page)).toBeVisible()

    const antes = await contadorDoMenu(page)
    expect(antes, 'o seeder não deixou nenhuma mensagem não lida').toBeGreaterThan(0)

    const linha = primeiraLinhaNaoLida(page)
    await expect(linha.getByRole('img', { name: 'Não lido' })).toBeVisible()

    const assunto = (await linha.locator('td').nth(2).textContent())?.trim() ?? ''
    await linha.getByRole('link').click()

    // No detalhe o registro já nasce lido — abrir É a leitura, não existe botão para isso.
    await expect(page.getByRole('button', { name: 'Marcar como não lido' })).toBeVisible()
    await expect(detailField(page, 'Lido por')).toContainText('Dora Direção')

    await expect.poll(() => contadorDoMenu(page)).toBe(antes - 1)

    // De volta na listagem: a linha perdeu o negrito e o indicador.
    await page.goto(RECURSO)
    const linhaDepois = submissionRow(page, assunto)
    await expect(linhaDepois).not.toHaveClass(/table__row--unread/)
    await expect(linhaDepois.getByRole('img', { name: 'Não lido' })).toHaveCount(0)

    // E "Marcar como não lido" desfaz: contador volta a subir e o indicador reaparece.
    await linhaDepois.getByRole('link').click()
    await page.getByRole('button', { name: 'Marcar como não lido' }).click()

    await expect(page.getByRole('button', { name: 'Marcar como não lido' })).toHaveCount(0)
    await expect.poll(() => contadorDoMenu(page)).toBe(antes)

    await page.goto(RECURSO)
    await expect(submissionRow(page, assunto)).toHaveClass(/table__row--unread/)
  })

  test('o filtro Leitura separa lidos de não lidos', async ({ page }) => {
    await page.goto(RECURSO)

    await page.getByLabel('Leitura').selectOption('unread')
    await page.getByRole('button', { name: 'Filtrar' }).click()
    await expect(page).toHaveURL(/read=unread/)

    await expect(submissionTable(page).locator('tbody tr')).not.toHaveCount(0)
    await expect(submissionTable(page).locator('tbody tr:not(.table__row--unread)')).toHaveCount(0)

    await page.getByLabel('Leitura').selectOption('read')
    await page.getByRole('button', { name: 'Filtrar' }).click()
    await expect(page).toHaveURL(/read=read/)

    await expect(submissionTable(page).locator('tbody tr')).not.toHaveCount(0)
    await expect(submissionTable(page).locator('tbody tr.table__row--unread')).toHaveCount(0)
  })

  /**
   * O ponto da decisão: a leitura é da EQUIPE. Se fosse por usuário, `atendimento` abriria a
   * mesma mensagem e ela ainda estaria não lida para ele.
   */
  test('o que a direção abriu aparece como lido para o atendimento', async ({ page, browser }) => {
    await page.goto(RECURSO)
    await expect(submissionTable(page)).toBeVisible()

    const linha = primeiraLinhaNaoLida(page)
    const assunto = (await linha.locator('td').nth(2).textContent())?.trim() ?? ''
    await linha.getByRole('link').click()
    await expect(page.getByRole('button', { name: 'Marcar como não lido' })).toBeVisible()

    const outraPessoa = await pageAs(browser, 'atendimento')
    await outraPessoa.goto(RECURSO)

    const mesmaLinha = submissionRow(outraPessoa, assunto)
    await expect(mesmaLinha).not.toHaveClass(/table__row--unread/)

    await mesmaLinha.getByRole('link').click()
    // E mostra quem leu primeiro, não quem está lendo agora.
    await expect(detailField(outraPessoa, 'Lido por')).toContainText('Dora Direção')

    await outraPessoa.context().close()
  })

  test('a tela Início mostra os não lidos e leva à listagem já filtrada', async ({ page }) => {
    await page.goto('/admin')
    await expect(page.getByRole('heading', { name: 'Início' })).toBeVisible()

    const card = page.getByRole('main').getByRole('link', { name: /Mensagens de contato/ })
    await expect(card).toContainText('não lidos')

    const doCard = Number(/(\d+)/.exec((await card.textContent()) ?? '')?.[1] ?? '-1')
    expect(doCard, 'o card não mostra número').toBeGreaterThanOrEqual(0)

    // O card e o menu leem a mesma store — não podem divergir.
    expect(await contadorDoMenu(page)).toBe(doCard)

    await card.click()
    await expect(page).toHaveURL(/read=unread/)
  })

  test('status de atendimento e leitura são independentes', async ({ page }) => {
    await page.goto(RECURSO)

    const linha = primeiraLinhaNaoLida(page)
    await linha.getByRole('link').click()

    await page.getByLabel('Status').selectOption('done')
    await page.getByRole('button', { name: 'Salvar' }).click()
    await expect(page.getByText('Atendimento atualizado.')).toBeVisible()

    // Concluir não desmarca a leitura, e desmarcar a leitura não muda o status.
    await expect(detailStatusBadge(page)).toHaveText('Concluído')
    await page.getByRole('button', { name: 'Marcar como não lido' }).click()
    await expect(page.getByRole('button', { name: 'Marcar como não lido' })).toHaveCount(0)
    await expect(detailStatusBadge(page)).toHaveText('Concluído')
  })
})
