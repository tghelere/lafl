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
  { alt: `Horta das crianças ${suffix}`, caption: 'Canteiros de pneu, em 2025', width: 1000, height: 1400, color: [60, 160, 60] as [number, number, number] },
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
