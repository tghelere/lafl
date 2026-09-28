import { type Page, expect, test } from '@playwright/test'

import { openContentPageByTitle, sidebar } from '../../support/admin'
import { ROLE_USERS, storageStatePath } from '../../support/users'

/**
 * O painel em tela estreita (ver frontend-admin/src/composables/useNavDrawer.ts e as media
 * queries de src/assets/css/components.css).
 *
 * A bateria roda em Firefox de verdade porque é a única forma de provar o que aqui importa:
 * que a gaveta some da ordem de tabulação quando fechada, que o foco não escapa dela quando
 * aberta e que a página não ganha rolagem horizontal. Nada disso aparece num teste de
 * componente — e a media query nem sequer existe lá.
 */
test.use({ storageState: storageStatePath('super_admin') })

/** A largura de referência de celular da bateria. */
const CELULAR = { width: 390, height: 844 }

/** A largura mais estreita que a tarefa nomeia — celular pequeno. */
const CELULAR_ESTREITO = { width: 360, height: 740 }

function menuButton(page: Page) {
  return page.getByRole('button', { name: 'Menu', exact: true })
}

async function abrirGaveta(page: Page): Promise<void> {
  // Espera o botão ANTES de clicar: ele só existe no painel autenticado e em tela estreita.
  // Assim, uma sessão perdida ou uma janela larga falham dizendo "o botão Menu não apareceu",
  // em vez de "o clique estourou o tempo" — que não diz nada sobre a causa.
  await expect(menuButton(page), 'o botão Menu não apareceu: o painel carregou autenticado?').toBeVisible()
  await menuButton(page).click()
  await expect(sidebar(page)).toBeVisible()
}

test.describe('gaveta de navegação abaixo de 1024px', () => {
  test.use({ viewport: CELULAR })

  test('a navegação começa fora da tela e abre pelo botão da barra superior', async ({ page }) => {
    await page.goto('/admin')
    await expect(page.getByRole('heading', { name: 'Início' })).toBeVisible()

    // `visibility: hidden` na gaveta fechada, e não só um deslocamento: fosse só transform, o
    // Tab continuaria andando pelos links do menu invisível.
    await expect(sidebar(page)).toBeHidden()
    await expect(menuButton(page)).toBeVisible()
    await expect(menuButton(page)).toHaveAttribute('aria-expanded', 'false')

    await abrirGaveta(page)

    await expect(menuButton(page)).toHaveAttribute('aria-expanded', 'true')
    await expect(sidebar(page).getByRole('link', { name: /Páginas/ })).toBeVisible()
  })

  test('a barra superior continua com o usuário e o Sair', async ({ page }) => {
    await page.goto('/admin')

    await expect(page.getByRole('link', { name: ROLE_USERS.super_admin.name })).toBeVisible()
    await expect(page.getByRole('button', { name: 'Sair', exact: true })).toBeVisible()
  })

  test('a gaveta fecha ao navegar', async ({ page }) => {
    await page.goto('/admin')
    await abrirGaveta(page)

    await sidebar(page).getByRole('link', { name: /Usuários/ }).click()

    await expect(page).toHaveURL(/\/admin\/usuarios$/)
    await expect(sidebar(page)).toBeHidden()
  })

  test('a gaveta fecha ao clicar fora', async ({ page }) => {
    await page.goto('/admin')
    await abrirGaveta(page)

    // O cortinado, e não um ponto qualquer da tela: é ele que cobre o conteúdo e recebe o
    // clique de fora — clicar "no conteúdo" atrás dele é impossível de propósito.
    await page.locator('.app-scrim').click({ position: { x: 340, y: 600 } })

    await expect(sidebar(page)).toBeHidden()
    await expect(page).toHaveURL(/\/admin$/)
  })

  test('Esc fecha a gaveta e devolve o foco ao botão que a abriu', async ({ page }) => {
    await page.goto('/admin')
    await abrirGaveta(page)

    await page.keyboard.press('Escape')

    await expect(sidebar(page)).toBeHidden()
    await expect(menuButton(page)).toBeFocused()
  })

  test('o foco não sai da gaveta enquanto ela está aberta', async ({ page }) => {
    await page.goto('/admin')
    await abrirGaveta(page)

    // Ida e volta: 25 tabulações cobrem com folga os itens do menu de um super_admin, então o
    // laço passa pelo fim da lista mais de uma vez — é exatamente ali que um foco sem prisão
    // escaparia para o conteúdo atrás do cortinado.
    const escaparam: string[] = []

    for (const tecla of ['Tab', 'Shift+Tab']) {
      for (let i = 0; i < 25; i++) {
        await page.keyboard.press(tecla)

        const dentro = await page.evaluate(
          () => document.querySelector('.app-sidebar')?.contains(document.activeElement) ?? false,
        )

        if (!dentro) {
          escaparam.push(
            `${tecla} #${i}: ${await page.evaluate(() => document.activeElement?.textContent?.trim().slice(0, 40) ?? '')}`,
          )
        }
      }
    }

    expect(escaparam, 'o foco saiu da gaveta aberta').toEqual([])
  })

  test('a gaveta aberta trava a rolagem do conteúdo de trás', async ({ page }) => {
    await page.goto('/admin')
    await abrirGaveta(page)

    await expect
      .poll(() => page.evaluate(() => getComputedStyle(document.body).overflow))
      .toBe('hidden')

    await page.keyboard.press('Escape')

    await expect.poll(() => page.evaluate(() => getComputedStyle(document.body).overflow)).not.toBe('hidden')
  })
})

test.describe('a partir de 1024px a navegação volta a ser coluna fixa', () => {
  test.use({ viewport: { width: 1024, height: 900 } })

  test('não há botão Menu, e o menu está sempre visível', async ({ page }) => {
    await page.goto('/admin')
    await expect(page.getByRole('heading', { name: 'Início' })).toBeVisible()

    await expect(sidebar(page)).toBeVisible()
    await expect(menuButton(page)).toHaveCount(0)
    await expect(page.locator('.app-scrim')).toHaveCount(0)

    const posicao = await page.locator('.app-sidebar').evaluate((el) => getComputedStyle(el).position)
    expect(posicao, 'a navegação lateral não deveria estar fora do fluxo em 1024px').toBe('static')
  })
})

/** As cinco listagens de formulário mais as quatro demais telas de tabela do painel. */
const LISTAGENS = [
  ['/admin/program-applications', 'Avisos de interesse no contraturno'],
  ['/admin/partnership-inquiries', 'Propostas de apoio'],
  ['/admin/volunteer-applications', 'Voluntários'],
  ['/admin/contact-messages', 'Mensagens de contato'],
  ['/admin/pickup-requests', 'Pedidos de coleta'],
  ['/admin/paginas', 'Páginas'],
  ['/admin/transparencia', 'Transparência'],
  ['/admin/usuarios', 'Usuários'],
  ['/admin/auditoria', 'Auditoria'],
]

/** Quanto a página passa da largura da janela. Zero é o único valor aceitável. */
function excessoHorizontal(page: Page): Promise<number> {
  return page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)
}

test.describe('listagens viram lista de cards abaixo de 768px', () => {
  test.use({ viewport: CELULAR })

  for (const [caminho, titulo] of LISTAGENS) {
    test(`${caminho} vira cards, sem cabeçalho de tabela e sem rolagem lateral`, async ({ page }) => {
      await page.goto(caminho!)
      await expect(page.locator('.page-header__title')).toHaveText(titulo!)
      await expect(page.locator('.table tbody tr').first()).toBeVisible()

      // O cabeçalho da tabela sai de cena: o rótulo de cada coluna passou a acompanhar o seu
      // valor dentro do card, e manter os dois faria o leitor de tela dizer tudo duas vezes.
      await expect(page.locator('.table thead')).toBeHidden()

      const linha = page.locator('.table tbody tr').first()
      const empilhado = await linha.evaluate((el) => getComputedStyle(el).display)
      expect(empilhado, 'a linha da listagem continua sendo linha de tabela').toBe('block')

      expect(await excessoHorizontal(page), `${caminho} rola de lado`).toBe(0)
    })
  }

  test('cada valor do card vem com o rótulo da sua coluna', async ({ page }) => {
    await page.goto('/admin/usuarios')
    await expect(page.locator('.table tbody tr').first()).toBeVisible()

    // O rótulo é desenhado pelo `content: attr(data-label)` do ::before (ver components.css).
    // Ler o `content` calculado é o que prova que ele chegou à tela — a presença do atributo
    // no HTML provaria só que alguém o escreveu.
    const rotulos = await page.locator('.table tbody tr').first().locator('td').evaluateAll((celulas) =>
      celulas.map((celula) => ({
        atributo: celula.getAttribute('data-label'),
        desenhado: getComputedStyle(celula, '::before').content,
      })),
    )

    expect(rotulos.length, 'nenhuma célula no primeiro card').toBeGreaterThan(1)

    // A primeira célula é o título do card e não leva rótulo — "NOME" acima do próprio nome.
    expect(rotulos[0]!.desenhado, 'a primeira célula não deveria ter rótulo').toBe('none')

    for (const celula of rotulos.slice(1)) {
      expect(celula.atributo, 'célula sem data-label').toBeTruthy()
      expect(celula.desenhado, `rótulo de ${celula.atributo}`).toContain(celula.atributo!)
    }
  })

  test('o card de um formulário mostra nome, recebido em, status e o indicador de não lido', async ({ page }) => {
    await page.goto('/admin/contact-messages')

    const naoLido = page.locator('.table tbody tr.table__row--unread').first()
    await expect(naoLido).toBeVisible()

    // O indicador não é só a cor nem só o peso da fonte: é um ponto com texto próprio, que o
    // leitor de tela anuncia (ver SubmissionListView.vue).
    await expect(naoLido.getByRole('img', { name: 'Não lido' })).toBeVisible()
    await expect(naoLido.locator('[data-label="Recebido em"]')).toBeVisible()
    await expect(naoLido.locator('[data-label="Status"] .badge')).toBeVisible()
    await expect(naoLido.getByRole('link')).toBeVisible()
  })

  test('os filtros empilham em largura inteira', async ({ page }) => {
    await page.goto('/admin/contact-messages')
    await expect(page.locator('.filter-bar')).toBeVisible()

    const barra = (await page.locator('.filter-bar').boundingBox())!
    const campos = await page.locator('.filter-bar__field select, .filter-bar__field input').all()
    const botoes = await page.locator('.filter-bar .btn').all()

    expect(campos.length, 'a barra de filtro desta tela não tem campo nenhum').toBeGreaterThan(1)

    const caixas = await Promise.all([...campos, ...botoes].map((alvo) => alvo.boundingBox()))
    const larguraUtil = barra.width - 2 * 16 // padding lateral de --space-4 dos dois lados

    for (const caixa of caixas) {
      expect(Math.abs(caixa!.width - larguraUtil), `campo com ${caixa!.width}px numa barra de ${larguraUtil}px`).toBeLessThanOrEqual(2)
    }

    // Empilhados: cada controle começa abaixo do anterior, nenhum dividindo linha.
    const topos = caixas.map((caixa) => caixa!.y)
    for (let i = 1; i < topos.length; i++) {
      expect(topos[i]!, 'dois controles da barra de filtro na mesma linha').toBeGreaterThan(topos[i - 1]!)
    }
  })
})

test.describe('a partir de 768px a listagem volta a ser tabela', () => {
  test.use({ viewport: { width: 768, height: 900 } })

  test('o cabeçalho da tabela reaparece e a página não rola de lado', async ({ page }) => {
    await page.goto('/admin/auditoria')
    await expect(page.locator('.table thead')).toBeVisible()

    const linha = page.locator('.table tbody tr').first()
    expect(await linha.evaluate((el) => getComputedStyle(el).display)).toBe('table-row')

    // A tabela larga rola DENTRO do seu próprio contêiner (.table-wrapper), não arrastando a
    // página inteira junto.
    expect(await excessoHorizontal(page), 'a página inteira rola de lado em 768px').toBe(0)
  })
})

test.describe('editor de páginas em tela estreita', () => {
  test.use({ viewport: CELULAR })

  test('a barra de formatação rola de lado, em uma linha só', async ({ page }) => {
    await openContentPageByTitle(page, 'Governança')

    const barra = page.locator('.rich-text__toolbar')
    await expect(barra).toBeVisible()

    const medida = await barra.evaluate((el) => ({
      wrap: getComputedStyle(el).flexWrap,
      transbordaDeLado: el.scrollWidth > el.clientWidth,
      altura: el.clientHeight,
      alturaDoBotao: el.querySelector('button')!.getBoundingClientRect().height,
    }))

    expect(medida.wrap, 'a barra ainda quebra em fileiras').toBe('nowrap')
    expect(medida.transbordaDeLado, 'os nove botões caberiam em 390px?').toBe(true)

    // Uma linha só: a barra não é mais alta que um botão mais o seu respiro. Quebrada em três
    // fileiras, ela empurraria o texto que está sendo escrito para fora da tela.
    expect(medida.altura, `barra de ${medida.altura}px para botão de ${medida.alturaDoBotao}px`).toBeLessThan(
      medida.alturaDoBotao * 2,
    )

    // E rola de verdade — `overflow-x: auto` num contêiner que não transborda não rolaria.
    const rolou = await barra.evaluate((el) => {
      el.scrollLeft = 9999

      return el.scrollLeft
    })
    expect(rolou, 'a barra não rolou').toBeGreaterThan(0)
  })

  test('o botão Salvar fica no rodapé da tela enquanto se edita', async ({ page }) => {
    await openContentPageByTitle(page, 'Governança')

    const salvar = page.getByRole('button', { name: 'Salvar', exact: true })
    await expect(salvar).toBeVisible()

    // Rola até o meio do formulário — é lá que o botão precisa estar à mão. Sem o rodapé fixo,
    // ele estaria muitas telas abaixo, depois do editor e dos dois campos de busca.
    await page.evaluate(() => window.scrollBy(0, 400))

    const caixa = (await salvar.boundingBox())!
    const altura = page.viewportSize()!.height

    expect(caixa.y + caixa.height, 'o Salvar saiu da tela ao rolar o formulário').toBeLessThanOrEqual(altura + 1)

    const posicao = await page.locator('.page-form__actions').evaluate((el) => getComputedStyle(el).position)
    expect(posicao).toBe('sticky')
  })

  test('a edição de página não rola de lado', async ({ page }) => {
    await openContentPageByTitle(page, 'Governança')

    expect(await excessoHorizontal(page)).toBe(0)
  })
})

test.describe('editor de páginas em tela larga', () => {
  test.use({ viewport: { width: 1024, height: 900 } })

  test('a barra volta a quebrar em fileiras e o Salvar volta ao fim do formulário', async ({ page }) => {
    await openContentPageByTitle(page, 'Governança')

    const wrap = await page.locator('.rich-text__toolbar').evaluate((el) => getComputedStyle(el).flexWrap)
    expect(wrap).toBe('wrap')

    const posicao = await page.locator('.page-form__actions').evaluate((el) => getComputedStyle(el).position)
    expect(posicao).toBe('static')
  })
})

/**
 * Tudo o que se toca no painel: item do menu, marca, botão (de tela, de filtro, de paginação,
 * de formatação do editor), o link que abre o registro no card, o degrau da trilha, o card do
 * Início e o nome de quem está logado, que leva ao /conta.
 */
const ALVOS_DE_TOQUE = [
  '.app-sidebar__brand',
  '.app-sidebar__link',
  '.app-sidebar__close',
  '.btn',
  '.pagination button',
  '.rich-text__button',
  '.table__row-link',
  '.breadcrumb a',
  '.app-topbar__user a',
  '.summary-card',
].join(', ')

/** Os alvos visíveis da tela que medem menos de 44px de altura. Lista vazia é o esperado. */
async function alvosPequenos(page: Page): Promise<string[]> {
  return page.locator(ALVOS_DE_TOQUE).evaluateAll((nodes) =>
    nodes
      .filter((node) => node.getClientRects().length > 0)
      .map((node) => ({
        descricao: `${node.tagName.toLowerCase()}.${node.className || '(sem classe)'}: ${(node.textContent ?? '').replace(/\s+/g, ' ').trim().slice(0, 30)}`,
        altura: node.getBoundingClientRect().height,
      }))
      // Meio pixel de tolerância: 44px vindo de `rem` em zoom fracionário arredonda para
      // baixo em 43,98 sem que nada esteja errado.
      .filter((alvo) => alvo.altura < 43.5)
      .map((alvo) => `${alvo.descricao} — ${alvo.altura.toFixed(1)}px`),
  )
}

test.describe('áreas de toque de 44px abaixo de 1024px', () => {
  test.use({ viewport: CELULAR })

  const TELAS = ['/admin', '/admin/usuarios', '/admin/contact-messages', '/admin/auditoria']

  for (const tela of TELAS) {
    test(`nenhum alvo de toque abaixo de 44px em ${tela}`, async ({ page }) => {
      await page.goto(tela)
      await expect(page.locator('.page-header__title')).toBeVisible()

      await expect.poll(() => page.locator(ALVOS_DE_TOQUE).count(), `${tela}: nenhum alvo medido`).toBeGreaterThan(3)

      expect(await alvosPequenos(page), `alvos pequenos em ${tela}`).toEqual([])
    })
  }

  test('nenhum alvo de toque abaixo de 44px no menu aberto', async ({ page }) => {
    await page.goto('/admin')
    await abrirGaveta(page)

    expect(await alvosPequenos(page), 'alvos pequenos na gaveta').toEqual([])
  })

  test('nenhum alvo de toque abaixo de 44px na edição de página', async ({ page }) => {
    await openContentPageByTitle(page, 'Governança')

    expect(await alvosPequenos(page), 'alvos pequenos na edição de página').toEqual([])
  })

  test('nenhum alvo de toque abaixo de 44px no detalhe de um registro', async ({ page }) => {
    await page.goto('/admin/contact-messages')
    await page.locator('.table tbody tr:not(.table__row--unread)').first().getByRole('link').click()
    await expect(page).toHaveURL(/\/admin\/contact-messages\/[0-9a-f-]{36}$/)

    expect(await alvosPequenos(page), 'alvos pequenos no detalhe').toEqual([])
  })
})

test.describe('a densidade de tela larga continua a mesma', () => {
  test.use({ viewport: { width: 1280, height: 900 } })

  /**
   * A área de toque vale abaixo de 64rem. Acima, onde há mouse, o painel volta à sua densidade
   * — é uma tela de trabalho, lida de perto, e 44px por controle empurraria a listagem para
   * fora da primeira dobra sem ganho nenhum.
   */
  test('os controles da barra de filtro voltam aos 36px', async ({ page }) => {
    await page.goto('/admin/usuarios')
    await expect(page.locator('.filter-bar .btn').first()).toBeVisible()

    const alturas = await page
      .locator('.filter-bar input, .filter-bar select, .filter-bar .btn')
      .evaluateAll((nodes) => nodes.map((node) => Math.round(node.getBoundingClientRect().height)))

    expect([...new Set(alturas)], 'altura dos controles da barra de filtro em 1280px').toEqual([36])
  })
})

test.describe('nada rola de lado em 360px', () => {
  test.use({ viewport: CELULAR_ESTREITO })

  /**
   * As quatro telas da conferência visual da tarefa, na largura mais estreita que ela nomeia.
   * Rolagem horizontal é o defeito que mais some numa conferência a olho — a página parece
   * certa, e só um arrasto lateral acidental revela a faixa vazia à direita.
   */
  test('Início, listagem, detalhe e edição de página cabem na largura da tela', async ({ page }) => {
    await page.goto('/admin')
    await expect(page.getByRole('heading', { name: 'Início' })).toBeVisible()
    expect(await excessoHorizontal(page), 'Início').toBe(0)

    await page.goto('/admin/contact-messages')
    await expect(page.locator('.table tbody tr').first()).toBeVisible()
    expect(await excessoHorizontal(page), 'listagem').toBe(0)

    await page.locator('.table tbody tr:not(.table__row--unread)').first().getByRole('link').click()
    await expect(page).toHaveURL(/\/admin\/contact-messages\/[0-9a-f-]{36}$/)
    expect(await excessoHorizontal(page), 'detalhe').toBe(0)

    await openContentPageByTitle(page, 'Governança')
    expect(await excessoHorizontal(page), 'edição de página').toBe(0)
  })
})
