import { expect, test } from '@playwright/test'

import { pageAs, sidebar } from '../../support/admin'
import {
  MARKER_SUBMISSION_SUBJECT,
  readSubmissionRow,
  submissionRow,
  submissionTable,
} from '../../support/submissions'
import { ROLE_USERS, storageStatePath } from '../../support/users'

/**
 * Tela de Auditoria e o histórico de acessos do detalhe (sessão 25, item 3).
 *
 * Por que na pilha real: a linha que a tela mostra só existe porque OUTRA pessoa, em OUTRA sessão,
 * abriu um registro — e o que liga as duas coisas é uma escrita em `activity_log` feita pelo
 * `show()` da API, com autor e IP tirados da requisição. Nada disso é observável sem duas sessões
 * de verdade contra a mesma API. O corte de acesso também não: ele vem do mapa `access` que o
 * backend calcula por Policy, e provar que `direcao` não entra exige a resposta real.
 */
const RECURSO = '/admin/contact-messages'

test.describe('auditoria — super_admin', () => {
  test.use({ storageState: storageStatePath('super_admin') })

  test('registra o acesso de outra pessoa, com autor, ação, IP e link para o registro', async ({ page, browser }) => {
    // A direção abre um registro conhecido. É ele que tem de aparecer na auditoria.
    const direcao = await pageAs(browser, 'direcao')
    await direcao.goto(RECURSO)
    await submissionRow(direcao, MARKER_SUBMISSION_SUBJECT).getByRole('link').click()
    await expect(direcao.getByRole('heading', { name: 'Mensagens de contato' })).toBeVisible()
    await direcao.context().close()

    await page.goto('/admin/auditoria')
    await expect(page.getByRole('heading', { name: 'Auditoria' })).toBeVisible()

    // `exact`, senão "IP" casa também com "Tipo de formulário".
    for (const coluna of ['Data e hora', 'Usuário', 'Ação', 'Tipo de formulário', 'Registro', 'IP']) {
      await expect(submissionTable(page).getByRole('columnheader', { name: coluna, exact: true })).toBeVisible()
    }

    const linha = submissionTable(page).getByRole('row')
      .filter({ hasText: ROLE_USERS.direcao.name })
      .filter({ hasText: 'Detalhe acessado' })
      .first()

    await expect(linha).toContainText('Mensagem de contato')
    await expect(linha).toContainText(/\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}/)
    // IP de loopback da pilha de testes — o que importa é que a coluna não está vazia.
    await expect(linha).toContainText(/\d+\.\d+\.\d+\.\d+|::1/)

    await linha.getByRole('link', { name: 'Abrir registro' }).click()
    await expect(page.getByRole('heading', { name: 'Mensagens de contato' })).toBeVisible()
    await expect(page.getByText(MARKER_SUBMISSION_SUBJECT)).toBeVisible()
  })

  test('filtra por usuário e por tipo de formulário', async ({ page, browser }) => {
    const atendimento = await pageAs(browser, 'atendimento')
    await atendimento.goto(RECURSO)
    // Um registro JÁ LIDO: abrir um não lido consumiria o estoque que
    // tests/painel/formularios-leitura.spec.ts observa (o seeder deixa dois por tipo), e
    // aquele arquivo passava a falhar conforme a ORDEM da execução. A auditoria registra o
    // acesso do mesmo jeito — é isso que este teste precisa, não o estado de leitura.
    await readSubmissionRow(atendimento).getByRole('link').click()
    await expect(atendimento.getByRole('button', { name: /Marcar como não lido|Atualizar atendimento/ }).first()).toBeVisible()
    await atendimento.context().close()

    await page.goto('/admin/auditoria')

    await page.getByLabel('Usuário').selectOption({ label: ROLE_USERS.atendimento.name })
    await page.getByRole('button', { name: 'Filtrar' }).click()
    await expect(page).toHaveURL(/user=/)

    const linhas = submissionTable(page).locator('tbody tr')
    await expect(linhas).not.toHaveCount(0)
    await expect(linhas.filter({ hasText: ROLE_USERS.direcao.name })).toHaveCount(0)

    await page.getByRole('button', { name: 'Limpar' }).click()
    await page.getByLabel('Tipo de formulário').selectOption('pickup_request')
    await page.getByRole('button', { name: 'Filtrar' }).click()
    await expect(page).toHaveURL(/type=pickup_request/)

    // Ninguém abriu pedido de coleta nesta execução: o recorte é honesto e vem vazio.
    await expect(
      submissionTable(page).locator('tbody tr').filter({ hasText: 'Mensagem de contato' }),
    ).toHaveCount(0)
  })

  test('o detalhe traz o histórico de acessos recolhido', async ({ page }) => {
    await page.goto(RECURSO)
    await submissionRow(page, MARKER_SUBMISSION_SUBJECT).getByRole('link').click()

    const historico = page.getByRole('group').filter({ hasText: 'Histórico de acessos' })
    await expect(historico).toBeVisible()

    // Recolhido: o conteúdo só aparece depois do clique — e só então a chamada de rede acontece.
    await expect(historico.getByRole('list')).toHaveCount(0)

    await page.getByText('Histórico de acessos').click()
    await expect(historico.getByRole('listitem').first()).toContainText('Detalhe acessado')
  })
})

test.describe('auditoria — quem não é super_admin', () => {
  test.use({ storageState: storageStatePath('direcao') })

  test('a direção não vê o item no menu e não entra pela URL', async ({ page }) => {
    await page.goto('/admin')

    await expect(sidebar(page).getByRole('link', { name: 'Auditoria' })).toHaveCount(0)

    await page.goto('/admin/auditoria')
    await expect(page.getByRole('alert')).toHaveText('Você não tem permissão para acessar este recurso.')

    // Dentro do layout, com a URL preservada — mesmo desenho das outras telas sem acesso.
    await expect(sidebar(page)).toBeVisible()
    await expect(page).toHaveURL(/\/admin\/auditoria$/)
  })

  test('o detalhe traz a nota discreta, e não o histórico de acessos', async ({ page }) => {
    await page.goto(RECURSO)
    await submissionRow(page, MARKER_SUBMISSION_SUBJECT).getByRole('link').click()

    await expect(page.getByText('Os acessos a este registro ficam na auditoria do sistema.')).toBeVisible()
    await expect(page.getByText('Histórico de acessos')).toHaveCount(0)
  })

  /**
   * A frase saiu do menu na sessão 25: ela ocupava espaço permanente para dizer uma vez o que a
   * nota do rodapé do registro diz no lugar certo.
   */
  test('o menu não tem mais o aviso sobre registro de acesso', async ({ page }) => {
    await page.goto('/admin')

    await expect(sidebar(page)).not.toContainText('Todo acesso ao detalhe')
    await expect(page.locator('.app-sidebar')).not.toContainText('Todo acesso ao detalhe')
  })
})
