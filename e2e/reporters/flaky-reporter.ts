import type { FullResult, Reporter, TestCase, TestResult } from '@playwright/test/reporter'

/**
 * Teste que falhou e passou na repetição é informação, não ruído: significa que alguma coisa
 * na bateria ou na pilha é sensível a tempo, e isso precisa aparecer no lugar onde as pessoas
 * olham (a saída do job), não só enterrado no relatório HTML.
 *
 * O `list` padrão do Playwright já conta os instáveis numa linha de resumo; o que falta é
 * dizer QUAIS foram e por que falharam da primeira vez. É só isso que este reporter
 * acrescenta — ele nunca muda o resultado da execução.
 */
export default class FlakyReporter implements Reporter {
  private readonly firstFailures = new Map<string, string>()

  private readonly flaky: TestCase[] = []

  onTestEnd(test: TestCase, result: TestResult): void {
    const id = test.id

    if (result.retry === 0 && (result.status === 'failed' || result.status === 'timedOut')) {
      const message = result.error?.message ?? result.status
      this.firstFailures.set(id, message.split('\n')[0]?.trim() ?? message)
    }

    if (test.outcome() === 'flaky' && !this.flaky.includes(test)) {
      this.flaky.push(test)
    }
  }

  onEnd(result: FullResult): void {
    if (this.flaky.length === 0) {
      return
    }

    const lines = [
      '',
      `INSTÁVEIS — ${this.flaky.length} teste(s) falharam e só passaram na repetição.`,
      'Não são sucesso: cada um abaixo é um defeito de tempo na bateria ou na pilha, e fica aqui até ser resolvido.',
      '',
    ]

    for (const test of this.flaky) {
      lines.push(`  • ${test.titlePath().filter(Boolean).join(' › ')}`)
      lines.push(`    1ª execução: ${this.firstFailures.get(test.id) ?? '(motivo não capturado)'}`)
    }

    lines.push('')
    lines.push(`Resultado final da execução: ${result.status}.`)

    process.stdout.write(`${lines.join('\n')}\n`)
  }
}
