import { type Page, expect, test } from '@playwright/test'

import { openContentPageByTitle } from '../../support/admin'
import { ADMIN_URL } from '../../support/env'
import { storageStatePath } from '../../support/users'

/**
 * Tipografia do painel (ver frontend-admin/src/assets/css/fonts.css e tokens.css).
 *
 * Três coisas que só um navegador de verdade prova, e por isso estão aqui e não num teste de
 * componente: que o arquivo da fonte CHEGA (um `@font-face` com caminho errado não quebra
 * nada, só cai no fallback em silêncio), que nenhum elemento renderizado acaba numa serifa, e
 * que o painel não pede fonte a domínio de terceiro.
 */
test.use({ storageState: storageStatePath('super_admin') })

const TELAS = [
  '/admin',
  '/admin/paginas',
  '/admin/volunteer-applications',
  '/admin/transparencia',
  '/admin/usuarios',
  '/conta',
]

/**
 * As famílias efetivamente resolvidas em cada elemento visível da tela — a PRIMEIRA da lista
 * declarada, que é a que o navegador usa quando a fonte carregou.
 */
async function familiasEmUso(page: Page): Promise<string[]> {
  return page.evaluate(() => {
    const familias = new Set<string>()

    document.querySelectorAll<HTMLElement>('body *').forEach((element) => {
      const rect = element.getBoundingClientRect()

      if (rect.width === 0 && rect.height === 0) {
        return
      }

      familias.add(getComputedStyle(element).fontFamily)
    })

    return [...familias]
  })
}

test('o corpo do painel é Source Sans 3, e a fonte realmente carregou', async ({ page }) => {
  await page.goto('/admin')
  await expect(page.getByRole('heading', { name: 'Início' })).toBeVisible()

  const corpo = await page.evaluate(() => getComputedStyle(document.body).fontFamily)
  expect(corpo).toMatch(/^"?Source Sans 3"?/)

  // `document.fonts.check` só responde `true` se o arquivo estiver carregado — é o que
  // distingue "declarei o @font-face" de "o navegador está usando esta fonte". Sem isto, um
  // caminho errado em fonts.css passaria pelo teste acima do mesmo jeito.
  const carregou = await page.evaluate(async () => {
    await document.fonts.ready

    return {
      regular: document.fonts.check('400 14px "Source Sans 3"'),
      semibold: document.fonts.check('600 14px "Source Sans 3"'),
      titulo: document.fonts.check('700 32px "Poppins"'),
      familias: [...document.fonts].map((face) => `${face.family} ${face.style} ${face.weight}`),
    }
  })

  expect(carregou.regular, JSON.stringify(carregou.familias)).toBe(true)
  expect(carregou.semibold, JSON.stringify(carregou.familias)).toBe(true)
  expect(carregou.titulo, JSON.stringify(carregou.familias)).toBe(true)
})

test('os títulos continuam em Poppins', async ({ page }) => {
  await openContentPageByTitle(page, 'Governança')

  const h1 = await page.locator('.page-header__title').evaluate((el) => getComputedStyle(el).fontFamily)
  expect(h1).toMatch(/^"?Poppins"?/)

  // O h2 de dentro da tela ("Busca no Google") também: nenhum título cai na fonte de corpo.
  const h2 = await page.locator('.page-form__section').evaluate((el) => getComputedStyle(el).fontFamily)
  expect(h2).toMatch(/^"?Poppins"?/)
})

for (const tela of TELAS) {
  test(`nenhuma serifa em ${tela}`, async ({ page }) => {
    await page.goto(tela)
    await expect(page.locator('.page-header__title')).toBeVisible()

    const familias = await familiasEmUso(page)

    expect(familias.length, `${tela}: nenhum elemento medido`).toBeGreaterThan(0)

    for (const familia of familias) {
      // Nome próprio da serifa que o painel usava, e o genérico `serif` isolado — `sans-serif`
      // e `monospace` continuam válidos (o endereço público da página é `<code>`).
      expect(familia, `${tela}: ${familia}`).not.toMatch(/Lora|Georgia|Times/)
      expect(familia.split(',').map((nome) => nome.trim()), `${tela}: ${familia}`).not.toContain('serif')
    }
  })
}

test('nenhuma fonte vem de domínio de terceiro', async ({ page }) => {
  const externas: string[] = []

  page.on('request', (request) => {
    const url = request.url()

    if (!url.startsWith(ADMIN_URL) && /\.(woff2?|ttf|otf|eot)(\?|$)|fonts\.(googleapis|gstatic)\.com/.test(url)) {
      externas.push(url)
    }
  })

  await page.goto('/admin/paginas')
  await expect(page.locator('.page-header__title')).toBeVisible()
  await page.evaluate(() => document.fonts.ready)

  expect(externas, 'o painel pediu fonte a um domínio de fora').toEqual([])
})

/**
 * Os quatro arquivos que fonts.css declara. Um `@font-face` apontando para um caminho que não
 * existe é silencioso no navegador: a tela só fica com a fonte de reserva, o que ninguém nota
 * numa conferência rápida.
 */
test('os arquivos da fonte estão publicados', async ({ request }) => {
  const arquivos = [
    '/fonts/source-sans-3-latin.woff2',
    '/fonts/source-sans-3-latin-ext.woff2',
    '/fonts/source-sans-3-italic-latin.woff2',
    '/fonts/source-sans-3-italic-latin-ext.woff2',
    '/fonts/poppins-600.woff2',
    '/fonts/poppins-700.woff2',
  ]

  for (const arquivo of arquivos) {
    const response = await request.get(arquivo)

    expect(response.status(), arquivo).toBe(200)
    // O tipo importa: o painel é uma SPA e o servidor devolve o index.html, com status 200,
    // para qualquer caminho que não exista. Só o content-type distingue "o arquivo está lá" de
    // "caiu na rota coringa".
    expect(response.headers()['content-type'], arquivo).toContain('font/woff2')
    expect((await response.body()).byteLength, `${arquivo} veio vazio`).toBeGreaterThan(1000)
  }
})

test('a Lora não é mais declarada no painel', async ({ page }) => {
  await page.goto('/admin')
  await expect(page.locator('.page-header__title')).toBeVisible()

  const familias = await page.evaluate(async () => {
    await document.fonts.ready

    // `face.family` volta com as aspas do CSS quando o nome tem espaço ("Source Sans 3") —
    // tirar aqui, para a comparação ser sobre o nome e não sobre a grafia do navegador.
    return [...document.fonts].map((face) => face.family.replace(/^["']|["']$/g, ''))
  })

  expect([...new Set(familias)].sort()).toEqual(['Poppins', 'Source Sans 3'])
})
