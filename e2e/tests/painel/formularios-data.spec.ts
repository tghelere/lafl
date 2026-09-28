import { expect, test } from '@playwright/test'

import {
  MARKER_SUBMISSION_LOCAL_DATE,
  MARKER_SUBMISSION_RECEIVED_LABEL,
  MARKER_SUBMISSION_SUBJECT,
  detailField,
  filterByDateRange,
  parseReceivedAt,
  receivedAtColumn,
  submissionRow,
  submissionTable,
} from '../../support/submissions'
import { storageStatePath } from '../../support/users'

/**
 * Coluna "Recebido em" das cinco listagens de formulário recebido, e o fuso dos filtros De/Até
 * (sessão 25, item 1).
 *
 * Por que isto não pode ser teste de componente com a API simulada: os dois defeitos possíveis
 * aqui só existem na pilha completa. O primeiro é de fuso — a coluna é montada pela API em
 * America/Sao_Paulo a partir de um `timestamptz` gravado em UTC por um servidor que roda nos
 * Estados Unidos; com a resposta simulada, o valor esperado viria do próprio teste e a conta
 * nunca seria feita. O segundo é o filtro De/Até, que é uma cláusula SQL contra o Postgres: a
 * versão antiga usava `whereDate` sobre a coluna UTC e devolveria o dia errado para tudo que
 * chega depois das 21h de Londrina, sem erro nenhum aparente.
 *
 * O registro-marco do seeder (ver E2eSeeder::MARKER_SUBMISSION_*) nasce exatamente nessa faixa:
 * 16/01/2026 01:30 UTC, que é 15/01/2026 22:30 em Londrina.
 */
test.describe('listagem de formulários — data de recebimento', () => {
  test.use({ storageState: storageStatePath('direcao') })

  test('mostra "Recebido em" no fuso de Londrina, não no UTC gravado', async ({ page }) => {
    await page.goto('/admin/contact-messages')
    await expect(page.getByRole('heading', { name: 'Mensagens de contato' })).toBeVisible()

    await expect(submissionTable(page).getByRole('columnheader', { name: 'Recebido em' })).toBeVisible()

    const linha = submissionRow(page, MARKER_SUBMISSION_SUBJECT)
    await expect(linha).toContainText(MARKER_SUBMISSION_RECEIVED_LABEL)
  })

  test('o detalhe mostra a mesma data que a listagem', async ({ page }) => {
    await page.goto('/admin/contact-messages')

    await submissionRow(page, MARKER_SUBMISSION_SUBJECT).getByRole('link').click()

    await expect(detailField(page, 'Recebido em')).toHaveText(MARKER_SUBMISSION_RECEIVED_LABEL)
  })

  test('ordena da mais recente para a mais antiga', async ({ page }) => {
    await page.goto('/admin/contact-messages')
    await expect(submissionTable(page)).toBeVisible()

    const instantes = (await receivedAtColumn(page)).map(parseReceivedAt)

    expect(instantes.length, 'a listagem de e2e está vazia — o seeder criou formulários?').toBeGreaterThan(1)

    for (let i = 1; i < instantes.length; i += 1) {
      expect(instantes[i], `linha ${i + 1} chegou depois da anterior`).toBeLessThanOrEqual(instantes[i - 1])
    }
  })

  /**
   * O caso que `whereDate` sobre a coluna UTC erraria: filtrar 15/01 a 15/01 tem de alcançar um
   * registro cujo timestamp UTC é do dia 16.
   */
  test('os filtros De/Até usam o dia de Londrina', async ({ page }) => {
    await page.goto('/admin/contact-messages')

    await filterByDateRange(page, MARKER_SUBMISSION_LOCAL_DATE, MARKER_SUBMISSION_LOCAL_DATE)

    await expect(submissionTable(page).locator('tbody tr')).toHaveCount(1)
    await expect(submissionRow(page, MARKER_SUBMISSION_SUBJECT)).toBeVisible()
  })

  test('as cinco listagens têm a coluna', async ({ page }) => {
    for (const recurso of [
      'program-applications',
      'pickup-requests',
      'volunteer-applications',
      'partnership-inquiries',
      'contact-messages',
    ]) {
      await page.goto(`/admin/${recurso}`)
      await expect(
        submissionTable(page).getByRole('columnheader', { name: 'Recebido em' }),
        `${recurso} não mostra "Recebido em"`,
      ).toBeVisible()
    }
  })
})
