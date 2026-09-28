import { type Page, expect } from '@playwright/test'

/**
 * Espelho de backend/database/seeders/E2eSeeder.php — mudar uma constante lá sem mudar aqui
 * quebra os testes de listagem de formulário.
 *
 * O par instante/rótulo é a asserção central da coluna "Recebido em": o registro nasce em
 * 16/01/2026 01:30 UTC e a tela tem de dizer 15/01/2026 22:30, porque quem lê o painel está em
 * America/Sao_Paulo (ver App\Support\InstitutionalTime). Nenhum teste recalcula esse fuso em
 * TypeScript — o valor esperado é fixo, escrito à mão, justamente para não repetir a conta que
 * está sendo conferida.
 */
export const MARKER_SUBMISSION_SUBJECT = 'Marco de listagem — recebido em horário fixo'

export const MARKER_SUBMISSION_RECEIVED_LABEL = '15/01/2026 22:30'

/** Dia local (não o do UTC) em que MARKER_SUBMISSION_RECEIVED_LABEL cai — para os filtros. */
export const MARKER_SUBMISSION_LOCAL_DATE = '2026-01-15'

/** A tabela da listagem, pelo seu papel — a tela tem só uma (ver SubmissionListView.vue). */
export function submissionTable(page: Page) {
  return page.getByRole('table')
}

/** A linha da listagem que contém o texto dado (nome, assunto, empresa). */
export function submissionRow(page: Page, text: string) {
  return submissionTable(page).getByRole('row').filter({ hasText: text })
}

/**
 * Conteúdo da coluna "Recebido em" de cada linha, na ordem em que a tela mostra. Descobre o
 * índice da coluna pelo cabeçalho em vez de fixar um número: as colunas anteriores mudam de
 * um recurso para outro (ver src/config/submissionResources.ts).
 */
export async function receivedAtColumn(page: Page): Promise<string[]> {
  // allTextContents, não allInnerTexts: o cabeçalho da tabela é `text-transform: uppercase`
  // (ver components.css), e innerText devolve o texto COMO RENDERIZADO — "RECEBIDO EM". O
  // conteúdo do DOM é que tem a grafia real.
  const headers = await submissionTable(page).locator('thead th').allTextContents()
  const index = headers.findIndex((header) => header.trim() === 'Recebido em')

  expect(index, 'a listagem não tem coluna "Recebido em"').toBeGreaterThan(-1)

  return submissionTable(page)
    .locator('tbody tr')
    .locator(`td:nth-child(${index + 1})`)
    .allTextContents()
    .then((cells) => cells.map((cell) => cell.trim()))
}

/**
 * dd/mm/aaaa HH:mm -> milissegundos, só para comparar a ORDEM das linhas. Não é conversão de
 * fuso: trata o texto como um instante abstrato, que é tudo o que uma comparação de ordem
 * precisa.
 */
export function parseReceivedAt(label: string): number {
  const match = /^(\d{2})\/(\d{2})\/(\d{4}) (\d{2}):(\d{2})$/.exec(label)

  if (!match) {
    throw new Error(`"${label}" não está no formato dd/mm/aaaa HH:mm`)
  }

  const [, day, month, year, hour, minute] = match

  return Date.UTC(Number(year), Number(month) - 1, Number(day), Number(hour), Number(minute))
}

/** Aplica os filtros De/Até da listagem e espera a tabela recarregar. */
export async function filterByDateRange(page: Page, from: string, to: string): Promise<void> {
  await page.getByLabel('De', { exact: true }).fill(from)
  await page.getByLabel('Até', { exact: true }).fill(to)
  await page.getByRole('button', { name: 'Filtrar' }).click()
  await expect(page).toHaveURL(new RegExp(`from=${from}`))
}
