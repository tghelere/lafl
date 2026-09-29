import { type Page, expect, test } from '@playwright/test'

import { unique } from '../../support/admin'
import { AdminApi } from '../../support/api'
import { SITE_URL } from '../../support/env'
import { type PageWithPhotos, createPageWithPhotos, removePageWithPhotos } from '../../support/fixtures'
import { gotoSite } from '../../support/site'

/**
 * Ampliação de imagens no site (sessão 30). Uma página de teste com três fotos na galeria (a
 * segunda com legenda) e uma figura no meio do texto, montada pela API.
 *
 * Por que na pilha real: o link de cada imagem do texto é montado pela API na leitura pública
 * (ExpandContentImages), o da galeria pelo site, e a foto grande vem pelo proxy /midia do Nitro.
 * A escolha da derivada depende do tamanho real da janela.
 */

const suffix = unique('e2e')
const slug = `ampliacao-${suffix}`.toLowerCase()
const titulo = `Página com fotos ${suffix}`
const galeria = [
  { alt: `Pátio visto de cima ${suffix}`, width: 1600, height: 1000, color: [200, 60, 60] as [number, number, number] },
  { alt: `Horta das crianças ${suffix}`, caption: 'Canteiros de pneu, em 2025', credit: 'Foto: equipe do Lar', width: 1000, height: 1400, color: [60, 160, 60] as [number, number, number] },
  { alt: `Fachada ao entardecer ${suffix}`, width: 900, height: 600, color: [60, 60, 200] as [number, number, number] },
]
const noTexto = { alt: `Recepção da sede ${suffix}`, caption: 'A recepção depois da reforma', width: 1300, height: 800, color: [180, 140, 40] as [number, number, number] }

let api: AdminApi
let criada: PageWithPhotos

test.beforeAll(async () => {
  api = await AdminApi.as('direcao')
  criada = await createPageWithPhotos(api, { slug, title: titulo, gallery: galeria, inText: noTexto })
})

test.afterAll(async () => {
  await removePageWithPhotos(api, criada)
  await api.dispose()
})

function ampliaveis(page: Page) {
  return page.locator('a[data-ampliar]')
}

test.describe('sem JavaScript: a imagem continua abrindo', () => {
  test.use({ javaScriptEnabled: false })

  test('toda imagem de conteúdo é um link para a maior derivada, e o clique abre a foto', async ({ page }) => {
    await gotoSite(page, `/${slug}`)

    // Três da galeria e uma do texto, cada uma com nome que diz o que o link faz.
    await expect(ampliaveis(page)).toHaveCount(4)
    await expect(page.getByRole('link', { name: `Ampliar imagem: ${galeria[0].alt}` })).toHaveAttribute(
      'href',
      `/midia/${criada.mediaIds[0]}/1280.webp`,
    )
    await expect(page.getByRole('link', { name: `Ampliar imagem: ${noTexto.alt}` })).toHaveAttribute(
      'href',
      `/midia/${criada.mediaIds[3]}/1280.webp`,
    )

    await page.getByRole('link', { name: `Ampliar imagem: ${galeria[2].alt}` }).click()
    await expect(page).toHaveURL(`${SITE_URL}/midia/${criada.mediaIds[2]}/640.webp`)
  })
})

test.describe('fora da ampliação', () => {
  test('logotipo, mapa de /contato, cartões e destaque da página inicial não abrem ampliados', async ({ page }) => {
    await gotoSite(page, '/contato')
    await expect(page.locator('.mapa-enderecos')).toBeVisible()
    await expect(ampliaveis(page)).toHaveCount(0)

    await gotoSite(page, '/o-que-fazemos')
    await expect(ampliaveis(page)).toHaveCount(0)

    // O destaque da página inicial é capa ("banner"): aparece, mas não abre ampliado.
    const paginas = (await (await api.get('/api/v1/pages', { per_page: 100 })).json()) as { data: { id: string; slug: string }[] }
    const quemSomos = paginas.data.find((candidata) => candidata.slug === 'quem-somos')!
    expect((await api.put(`/api/v1/pages/${quemSomos.id}/images/cover`, { media: criada.mediaIds[0] })).status()).toBe(204)

    try {
      await gotoSite(page, '/')
      await expect(page.getByRole('region', { name: 'Foto da sede' }).locator('img')).toBeVisible()
      await expect(ampliaveis(page)).toHaveCount(0)
    } finally {
      await api.delete(`/api/v1/pages/${quemSomos.id}/images/${criada.mediaIds[0]}?role=cover`)
    }
  })
})

test.describe('teclado', () => {
  test('a imagem clicável é alcançável por Tab, com o contorno de foco', async ({ page }) => {
    await gotoSite(page, `/${slug}`)
    const alvo = page.getByRole('link', { name: `Ampliar imagem: ${noTexto.alt}` })

    // Só Tab, a partir do topo, como faria quem não usa mouse.
    let alcancou = false
    for (let i = 0; i < 80 && !alcancou; i++) {
      await page.keyboard.press('Tab')
      alcancou = await alvo.evaluate((node) => node === document.activeElement)
    }

    expect(alcancou, 'a imagem não foi alcançada por Tab').toBe(true)
    expect(await alvo.evaluate((node) => getComputedStyle(node).outlineStyle)).toBe('solid')
  })
})

function ampliacao(page: Page) {
  return page.getByRole('dialog', { name: 'Imagem ampliada' })
}

/**
 * Confere a regra da derivada contra o tamanho REAL do palco nesta janela: a escolhida é a
 * maior cuja largura não passa do tamanho em que a imagem aparece (em pixels do aparelho).
 */
async function expectLargestThatFits(page: Page, widths: number[], ratio: number): Promise<void> {
  const imagem = ampliacao(page).locator('img')
  await expect(imagem).toBeVisible()

  const { largura, altura, densidade } = await page.locator('.ampliacao__palco').evaluate((node) => {
    const caixa = node.getBoundingClientRect()
    return { largura: caixa.width, altura: caixa.height, densidade: window.devicePixelRatio }
  })
  const alvo = Math.min(largura, altura * ratio) * densidade
  const cabem = widths.filter((w) => w <= alvo)
  const esperada = cabem.length > 0 ? Math.max(...cabem) : Math.min(...widths)

  await expect(imagem).toHaveAttribute('data-largura', String(esperada))
  await expect.poll(() => imagem.evaluate((node) => (node as HTMLImageElement).complete && (node as HTMLImageElement).naturalWidth)).toBe(esperada)
}

test.describe('ampliação — 1280px', () => {
  test.use({ viewport: { width: 1280, height: 800 } })

  test('abre sobre a página na maior derivada que cabe, com posição, legenda e crédito', async ({ page }) => {
    await gotoSite(page, `/${slug}`)
    await page.getByRole('link', { name: `Ampliar imagem: ${galeria[0].alt}` }).click()

    const dialogo = ampliacao(page)
    await expect(dialogo).toBeVisible()
    // A página continua por trás: a URL não mudou.
    await expect(page).toHaveURL(new RegExp(`/${slug}$`))
    await expect(dialogo.getByText('1 de 3', { exact: true })).toBeVisible()
    await expect(dialogo.locator('img')).toHaveAttribute('alt', galeria[0].alt)
    await expectLargestThatFits(page, [400, 640, 960, 1280], 1600 / 1000)
    await expect(dialogo.locator('.ampliacao__legenda')).toHaveCount(0)

    // Próxima pelo botão: a segunda tem legenda e crédito, embaixo da imagem.
    await dialogo.getByRole('button', { name: 'Próxima imagem' }).click()
    await expect(dialogo.getByText('2 de 3', { exact: true })).toBeVisible()
    await expect(dialogo.locator('.ampliacao__legenda')).toContainText('Canteiros de pneu, em 2025')
    await expect(dialogo.locator('.ampliacao__legenda')).toContainText('Foto: equipe do Lar')
    await expectLargestThatFits(page, [400, 640, 960], 1000 / 1400)
  })

  test('setas do teclado e do botão navegam, e as pontas não passam', async ({ page }) => {
    await gotoSite(page, `/${slug}`)
    await page.getByRole('link', { name: `Ampliar imagem: ${galeria[0].alt}` }).click()
    const dialogo = ampliacao(page)

    await expect(dialogo.getByRole('button', { name: 'Imagem anterior' })).toBeDisabled()
    await page.keyboard.press('ArrowRight')
    await page.keyboard.press('ArrowRight')
    await expect(dialogo.getByText('3 de 3', { exact: true })).toBeVisible()
    await expect(dialogo.getByRole('button', { name: 'Próxima imagem' })).toBeDisabled()
    await page.keyboard.press('ArrowRight')
    await expect(dialogo.getByText('3 de 3', { exact: true })).toBeVisible()

    await page.keyboard.press('ArrowLeft')
    await expect(dialogo.getByText('2 de 3', { exact: true })).toBeVisible()
    await dialogo.getByRole('button', { name: 'Imagem anterior' }).click()
    await expect(dialogo.locator('img')).toHaveAttribute('alt', galeria[0].alt)
  })

  test('deslizar para o lado troca de imagem, e não fecha', async ({ page }) => {
    await gotoSite(page, `/${slug}`)
    await page.getByRole('link', { name: `Ampliar imagem: ${galeria[0].alt}` }).click()
    const caixa = (await page.locator('.ampliacao__palco').boundingBox())!
    const meio = { x: caixa.x + caixa.width / 2, y: caixa.y + caixa.height / 2 }

    await page.mouse.move(meio.x + 150, meio.y)
    await page.mouse.down()
    await page.mouse.move(meio.x - 150, meio.y + 10, { steps: 5 })
    await page.mouse.up()

    await expect(ampliacao(page).getByText('2 de 3', { exact: true })).toBeVisible()
    await expect(ampliacao(page)).toBeVisible()
  })

  test('fecha pelo botão, pelo Esc e pelo clique fora', async ({ page }) => {
    await gotoSite(page, `/${slug}`)
    const abrir = page.getByRole('link', { name: `Ampliar imagem: ${galeria[2].alt}` })

    await abrir.click()
    await ampliacao(page).getByRole('button', { name: 'Fechar' }).click()
    await expect(ampliacao(page)).toBeHidden()

    await abrir.click()
    await page.keyboard.press('Escape')
    await expect(ampliacao(page)).toBeHidden()

    await abrir.click()
    await expect(ampliacao(page)).toBeVisible()
    // Canto da janela: fora do diálogo, no fundo escurecido.
    await page.mouse.click(4, 4)
    await expect(ampliacao(page)).toBeHidden()
  })

  test('a figura do texto abre sozinha, com a legenda do texto e sem posição', async ({ page }) => {
    await gotoSite(page, `/${slug}`)
    await page.getByRole('link', { name: `Ampliar imagem: ${noTexto.alt}` }).click()

    const dialogo = ampliacao(page)
    await expect(dialogo.locator('img')).toHaveAttribute('alt', noTexto.alt)
    await expect(dialogo.locator('.ampliacao__legenda')).toHaveText('A recepção depois da reforma')
    await expect(dialogo.getByText(/de \d+$/)).toHaveCount(0)
    await expect(dialogo.getByRole('button', { name: 'Próxima imagem' })).toHaveCount(0)
  })
})

test.describe('acessibilidade — 1280px, só teclado', () => {
  test.use({ viewport: { width: 1280, height: 800 } })

  async function focusByTab(page: Page, name: string): Promise<void> {
    const alvo = page.getByRole('link', { name })
    for (let i = 0; i < 80; i++) {
      await page.keyboard.press('Tab')
      if (await alvo.evaluate((node) => node === document.activeElement)) {
        return
      }
    }
    throw new Error(`"${name}" não foi alcançado por Tab`)
  }

  async function focusIsInsideDialog(page: Page): Promise<boolean> {
    return page.evaluate(() => document.querySelector('dialog.ampliacao')?.contains(document.activeElement) ?? false)
  }

  test('abre com Enter, prende o foco, anuncia a troca e devolve o foco à imagem de origem', async ({ page }) => {
    await gotoSite(page, `/${slug}`)
    const origem = `Ampliar imagem: ${galeria[0].alt}`
    await focusByTab(page, origem)
    await page.keyboard.press('Enter')

    const dialogo = ampliacao(page)
    await expect(dialogo).toBeVisible()
    await expect(dialogo.getByRole('button', { name: 'Fechar' })).toBeFocused()
    await expect(dialogo.getByText(`Imagem 1 de 3: ${galeria[0].alt}`)).toBeAttached()

    // Tab e Shift+Tab muitas vezes: o foco nunca sai do diálogo.
    for (const tecla of ['Tab', 'Tab', 'Tab', 'Tab', 'Shift+Tab', 'Shift+Tab', 'Shift+Tab']) {
      await page.keyboard.press(tecla)
      expect(await focusIsInsideDialog(page), `o foco saiu do diálogo depois de ${tecla}`).toBe(true)
    }

    // Enter em "Próxima imagem" até a ponta: o botão desliga e o foco passa a "Imagem anterior".
    await dialogo.getByRole('button', { name: 'Próxima imagem' }).focus()
    await page.keyboard.press('Enter')
    await expect(dialogo.getByText(`Imagem 2 de 3: ${galeria[1].alt}`)).toBeAttached()
    await page.keyboard.press('Enter')
    await expect(dialogo.getByText(`Imagem 3 de 3: ${galeria[2].alt}`)).toBeAttached()
    await expect(dialogo.getByRole('button', { name: 'Imagem anterior' })).toBeFocused()

    // Fechar depois de navegar: o foco volta à imagem clicada, não à última vista.
    await page.keyboard.press('Escape')
    await expect(dialogo).toBeHidden()
    await expect(page.getByRole('link', { name: origem })).toBeFocused()
  })

  test('o foco volta à origem também pelo botão Fechar e pelo clique fora', async ({ page }) => {
    await gotoSite(page, `/${slug}`)
    const origem = page.getByRole('link', { name: `Ampliar imagem: ${noTexto.alt}` })

    await origem.click()
    await ampliacao(page).getByRole('button', { name: 'Fechar' }).click()
    await expect(origem).toBeFocused()

    await origem.click()
    await expect(ampliacao(page)).toBeVisible()
    await page.mouse.click(4, 4)
    await expect(ampliacao(page)).toBeHidden()
    await expect(origem).toBeFocused()
  })

  test('rótulos em pt-BR e a legenda descrevendo o diálogo', async ({ page }) => {
    await gotoSite(page, `/${slug}`)
    await page.getByRole('link', { name: `Ampliar imagem: ${galeria[1].alt}` }).click()

    const dialogo = ampliacao(page)
    await expect(dialogo).toHaveAttribute('aria-describedby', 'ampliacao-legenda')
    for (const nome of ['Fechar', 'Imagem anterior', 'Próxima imagem']) {
      await expect(dialogo.getByRole('button', { name: nome })).toBeVisible()
    }
  })
})

test.describe('celular — 360px', () => {
  test.use({ viewport: { width: 360, height: 740 } })

  test('usa a tela toda, com controles de 44px, e a imagem cabe sem rolagem lateral', async ({ page }) => {
    await gotoSite(page, `/${slug}`)
    await page.getByRole('link', { name: `Ampliar imagem: ${galeria[1].alt}` }).click()

    const dialogo = ampliacao(page)
    await expect(dialogo).toBeVisible()
    const caixa = (await dialogo.boundingBox())!
    expect(caixa).toEqual({ x: 0, y: 0, width: 360, height: 740 })

    for (const nome of ['Fechar', 'Imagem anterior', 'Próxima imagem']) {
      const botao = (await dialogo.getByRole('button', { name: nome }).boundingBox())!
      expect(botao.height, nome).toBeGreaterThanOrEqual(44)
      expect(botao.width, nome).toBeGreaterThanOrEqual(44)
      expect(botao.x + botao.width, nome).toBeLessThanOrEqual(360)
    }

    const imagem = (await dialogo.locator('img').boundingBox())!
    expect(imagem.x).toBeGreaterThanOrEqual(0)
    expect(imagem.x + imagem.width).toBeLessThanOrEqual(360)
    await expect(dialogo.locator('.ampliacao__legenda')).toBeInViewport()
    await expectLargestThatFits(page, [400, 640, 960], 1000 / 1400)
    expect(await dialogo.evaluate((node) => node.scrollWidth <= node.clientWidth)).toBe(true)
  })

  test('deslize de dedo navega, e o teclado continua funcionando', async ({ page }) => {
    await gotoSite(page, `/${slug}`)
    await page.getByRole('link', { name: `Ampliar imagem: ${galeria[0].alt}` }).click()
    const dialogo = ampliacao(page)
    await expect(dialogo.getByText('1 de 3', { exact: true })).toBeVisible()

    // Deslize de toque para a esquerda: eventos de ponteiro do tipo "touch", como num celular.
    await page.locator('.ampliacao__palco').evaluate((palco) => {
      const caixa = palco.getBoundingClientRect()
      const y = caixa.top + caixa.height / 2
      const toque = (tipo: string, x: number) =>
        palco.dispatchEvent(new PointerEvent(tipo, { bubbles: true, clientX: x, clientY: y, pointerType: 'touch', isPrimary: true }))
      toque('pointerdown', caixa.right - 30)
      toque('pointerup', caixa.left + 30)
    })
    await expect(dialogo.getByText('2 de 3', { exact: true })).toBeVisible()

    await page.keyboard.press('ArrowLeft')
    await expect(dialogo.getByText('1 de 3', { exact: true })).toBeVisible()
    await page.keyboard.press('Escape')
    await expect(page.getByRole('link', { name: `Ampliar imagem: ${galeria[0].alt}` })).toBeFocused()
  })
})
