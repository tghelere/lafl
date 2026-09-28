import { type Page, expect, test } from '@playwright/test'

import { openContentPageByTitle } from '../../support/admin'
import { storageStatePath } from '../../support/users'

/**
 * Ícones do painel (ver frontend-admin/src/config/icons.ts e src/components/AppIcon.vue).
 *
 * Três coisas que este arquivo trava, e que um teste de componente não alcançaria:
 *
 * 1. Que o ícone CHEGA à tela. Os ícones vêm um a um do `lucide-vue-next` (import nomeado, não
 *    `import *`, para não levar mil desenhos ao bundle) e passam por um mapa de recurso →
 *    ícone. Um nome fora do mapa não quebra nada: o `v-if` some com o ícone em silêncio, e a
 *    tela fica só com o rótulo — exatamente o que ninguém nota numa conferência rápida.
 * 2. Que o MESMO recurso tem o MESMO desenho nos dois lugares em que aparece (navegação
 *    lateral e card da tela Início). É a razão de o mapa existir; duas listas separadas
 *    ensinariam dois símbolos para a mesma coisa.
 * 3. Que todo ícone é `aria-hidden`. No painel o ícone nunca é a informação — vem sempre ao
 *    lado do rótulo escrito. Um ícone anunciado faria o leitor de tela dizer a mesma coisa
 *    duas vezes, e é por isso que os testes abaixo conferem também o nome acessível do botão:
 *    ele tem de continuar sendo só o rótulo.
 */
test.use({ storageState: storageStatePath('super_admin') })

/**
 * Para cada elemento que casa com o seletor: o seu texto e o nome do ícone que ele contém
 * (vazio quando não contém nenhum).
 *
 * O nome do desenho sai da classe que o próprio lucide escreve no `<svg>` (`lucide` +
 * `lucide-mail-icon` + `lucide-mail`). É o que permite comparar "é o mesmo ícone" entre dois
 * pontos da tela sem depender de cor, tamanho ou posição — e sem fixar no teste a geometria do
 * desenho, que é do pacote e muda numa atualização dele.
 *
 * Leitura instantânea, sem espera embutida: o painel é uma SPA e a tela só existe depois de
 * `/auth/user` responder. Use sempre dentro de `expect.poll`, nunca solto.
 */
function iconesDe(page: Page, selector: string): Promise<{ texto: string; icone: string }[]> {
  return page.locator(selector).evaluateAll((nodes) =>
    nodes.map((node) => {
      const svg = node.querySelector('svg.icon')
      const classes = svg ? [...svg.classList] : []

      return {
        // textContent, nunca innerText: vários destes elementos têm text-transform na folha de
        // estilo (selo, rótulo de seção), e o teste compara o texto como está no DOM.
        texto: (node.textContent ?? '').replace(/\s+/g, ' ').trim(),
        icone: classes.find((name) => name.startsWith('lucide-') && !name.endsWith('-icon')) ?? '',
      }
    }),
  )
}

/** Todos os elementos do seletor têm ícone — a lista devolvida é a dos que NÃO têm. */
async function expectTodosComIcone(page: Page, selector: string, minimo = 1): Promise<void> {
  await expect
    .poll(async () => (await iconesDe(page, selector)).length, `nenhum elemento em ${selector}`)
    .toBeGreaterThanOrEqual(minimo)

  const semIcone = (await iconesDe(page, selector)).filter((item) => item.icone === '')

  expect(semIcone.map((item) => item.texto), `sem ícone em ${selector} (${page.url()})`).toEqual([])
}

test('todo item da navegação lateral tem ícone', async ({ page }) => {
  await page.goto('/admin')

  // super_admin enxerga as nove entradas do menu (cinco formulários, páginas, documentos,
  // usuários, auditoria) mais o Início.
  await expectTodosComIcone(page, '.app-sidebar__link', 10)
})

test('todo card da tela Início tem ícone', async ({ page }) => {
  await page.goto('/admin')
  await expect(page.getByRole('heading', { name: 'Início' })).toBeVisible()

  await expectTodosComIcone(page, '.summary-card__label', 5)
})

/**
 * Cada link do seletor pelo RECURSO que ele aponta (o primeiro segmento depois de /admin/) e
 * pelo ícone que carrega. O pareamento é pelo endereço, e não pelo rótulo: o card do Início e
 * o item do menu escrevem o mesmo recurso com palavras diferentes ("Avisos de interesse no
 * contraturno" no card, "Avisos do contraturno" no menu), e comparar texto faria o teste
 * quebrar a cada ajuste de redação.
 */
function iconePorRecurso(page: Page, selector: string): Promise<Record<string, string>> {
  return page.locator(selector).evaluateAll((nodes) => {
    const porRecurso: Record<string, string> = {}

    for (const node of nodes) {
      const href = node.getAttribute('href') ?? ''
      const recurso = new URL(href, window.location.origin).pathname.replace(/^\/admin\/?/, '').split('/')[0] ?? ''
      const svg = node.querySelector('svg.icon')
      const classes = svg ? [...svg.classList] : []

      if (recurso !== '') {
        porRecurso[recurso] = classes.find((name) => name.startsWith('lucide-') && !name.endsWith('-icon')) ?? ''
      }
    }

    return porRecurso
  })
}

test('o ícone de um recurso é o mesmo no menu e no card do Início', async ({ page }) => {
  await page.goto('/admin')
  await expect(page.getByRole('heading', { name: 'Início' })).toBeVisible()

  await expect.poll(async () => Object.keys(await iconePorRecurso(page, '.summary-card')).length).toBeGreaterThanOrEqual(5)

  const menu = await iconePorRecurso(page, '.app-sidebar__link')
  const cards = await iconePorRecurso(page, '.summary-card')

  for (const [recurso, icone] of Object.entries(cards)) {
    expect(icone, `o card de ${recurso} está sem ícone`).not.toBe('')
    expect(menu[recurso], `${recurso} não tem item de menu`).toBeDefined()
    expect(menu[recurso], `ícone de ${recurso}: menu vs. card do Início`).toBe(icone)
  }
})

/**
 * Botão com ícone, tela a tela. Cada entrada é [caminho, rótulo] — o rótulo é o nome acessível
 * do botão, e as duas asserções (existe o ícone, o nome continua sendo só o rótulo) andam
 * juntas de propósito: é o par que prova que o ícone entrou sem virar texto anunciado.
 */
const BOTOES = [
  ['/admin', 'Sair'],
  ['/admin/usuarios', 'Filtrar'],
  ['/admin/usuarios', 'Limpar'],
  ['/admin/usuarios', 'Novo usuário'],
  ['/admin/transparencia', 'Novo documento'],
  ['/admin/auditoria', 'Filtrar'],
  ['/admin/volunteer-applications', 'Filtrar'],
]

for (const [caminho, rotulo] of BOTOES) {
  test(`o botão "${rotulo}" de ${caminho} tem ícone, e o nome acessível continua só o rótulo`, async ({ page }) => {
    await page.goto(caminho!)

    const botao = page.getByRole('button', { name: rotulo!, exact: true }).or(
      page.getByRole('link', { name: rotulo!, exact: true }),
    )

    await expect(botao).toHaveCount(1)
    await expect(botao.locator('svg.icon')).toHaveCount(1)
  })
}

test('os botões da edição de página têm ícone', async ({ page }) => {
  await openContentPageByTitle(page, 'Governança')

  await expect(page.getByRole('button', { name: 'Salvar', exact: true }).locator('svg.icon')).toHaveCount(1)
  await expect(page.getByRole('link', { name: 'Abrir no site', exact: true }).locator('svg.icon')).toHaveCount(1)
})

test('todo selo de estado tem ícone', async ({ page }) => {
  await page.goto('/admin/usuarios')
  await expectTodosComIcone(page, '.badge')

  await page.goto('/admin/volunteer-applications')
  await expectTodosComIcone(page, '.badge')

  await page.goto('/admin/paginas')
  await expectTodosComIcone(page, '.badge')
})

const TELAS = [
  '/admin',
  '/admin/paginas',
  '/admin/volunteer-applications',
  '/admin/transparencia',
  '/admin/usuarios',
  '/admin/auditoria',
]

for (const tela of TELAS) {
  test(`nenhum ícone de ${tela} é anunciado pelo leitor de tela`, async ({ page }) => {
    await page.goto(tela)
    await expect(page.locator('.page-header__title')).toBeVisible()
    await expect.poll(() => page.locator('svg.icon').count(), `${tela}: nenhum ícone na tela`).toBeGreaterThan(0)

    const expostos = await page.locator('svg.icon').evaluateAll((nodes) =>
      nodes
        .filter((node) => node.getAttribute('aria-hidden') !== 'true' || node.getAttribute('focusable') !== 'false')
        .map((node) => node.getAttribute('class') ?? ''),
    )

    expect(expostos, `${tela}: ícone sem aria-hidden/focusable`).toEqual([])
  })
}

/**
 * 18px é o tamanho único do painel (ver AppIcon.vue). Medido na caixa renderizada, não no
 * atributo: `.icon` também fixa largura e altura em CSS, e é o resultado das duas coisas
 * juntas que importa — um SVG dentro de flex apertado encolhe sem `flex: none`.
 *
 * Só o que está DESENHADO entra na conta. O botão Fechar da gaveta de navegação existe na
 * marcação em qualquer largura e é escondido por CSS acima de 64rem (ver components.css); o
 * ícone dele mede 0×0 nessa faixa, o que não é ícone fora de tamanho, é ícone que não está na
 * tela.
 */
test('todo ícone é desenhado em 18px', async ({ page }) => {
  await page.goto('/admin/volunteer-applications')
  await expect(page.locator('.page-header__title')).toBeVisible()

  const foraDoTamanho = await page.locator('svg.icon').evaluateAll((nodes) =>
    nodes
      .filter((node) => node.getClientRects().length > 0)
      .map((node) => ({ classe: node.getAttribute('class') ?? '', caixa: node.getBoundingClientRect() }))
      .filter(({ caixa }) => Math.abs(caixa.width - 18) > 1 || Math.abs(caixa.height - 18) > 1)
      .map(({ classe, caixa }) => `${classe}: ${caixa.width}×${caixa.height}`),
  )

  expect(foraDoTamanho, 'ícone fora dos 18px').toEqual([])
})
