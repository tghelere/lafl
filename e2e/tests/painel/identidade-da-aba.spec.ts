import { expect, test } from '@playwright/test'

import { storageStatePath } from '../../support/users'

/**
 * Ícone, manifesto e título da aba do painel (ver frontend-admin/index.html,
 * frontend-admin/public/manifest.webmanifest e o `afterEach` de
 * frontend-admin/src/router/index.ts).
 *
 * O título é escrito pelo roteador depois que a navegação é confirmada, não pelo HTML — numa
 * SPA o `<title>` do index.html vale só até o Vue montar. Por isso todas as asserções de
 * título usam `toHaveTitle`, que reavalia até o valor chegar, e nunca uma leitura instantânea.
 */
test.describe('painel — ícone e manifesto', () => {
  test.use({ storageState: { cookies: [], origins: [] } })

  test('o HTML aponta para os três ícones e para o manifesto, e todos respondem', async ({ page, request }) => {
    await page.goto('/login')

    // `rel~="icon"` NÃO alcança rel="apple-touch-icon": o seletor casa palavra inteira numa
    // lista separada por espaço, e "apple-touch-icon" é uma palavra só. Por isso os três rel
    // são listados um a um.
    const hrefs = await page
      .locator('head link[rel="icon"], head link[rel="apple-touch-icon"], head link[rel="manifest"]')
      .evaluateAll((nodes) =>
        nodes.map((node) => ({
          rel: node.getAttribute('rel'),
          href: node.getAttribute('href'),
        })),
      )

    expect(hrefs).toEqual([
      { rel: 'icon', href: '/favicon.ico' },
      { rel: 'icon', href: '/favicon.svg' },
      { rel: 'apple-touch-icon', href: '/apple-touch-icon.png' },
      { rel: 'manifest', href: '/manifest.webmanifest' },
    ])

    for (const { href } of hrefs) {
      const response = await request.get(href!)

      expect(response.status(), `${href} respondeu ${response.status()}`).toBe(200)
    }
  })

  test('o manifesto tem o nome pedido e os dois ícones de instalação', async ({ request }) => {
    const manifest = (await (await request.get('/manifest.webmanifest')).json()) as {
      name: string
      short_name: string
      icons: { src: string; sizes: string }[]
    }

    expect(manifest.name).toBe('Painel · Lar Anália Franco')
    expect(manifest.short_name).toBe('Painel LAF')
    expect(manifest.icons.map((icon) => icon.sizes)).toEqual(['192x192', '512x512'])

    for (const icon of manifest.icons) {
      expect((await request.get(icon.src)).status(), `${icon.src}`).toBe(200)
    }
  })

  test('a tela de login já usa o formato "<Tela> · Painel LAF"', async ({ page }) => {
    await page.goto('/login')

    await expect(page).toHaveTitle('Entrar · Painel LAF')
  })
})

test.describe('painel — título da aba por tela', () => {
  test.use({ storageState: storageStatePath('super_admin') })

  /**
   * Uma tela de cada família: fixa (Início, Páginas), derivada do recurso (as cinco listagens
   * de formulário são a MESMA tela — o título tem de vir da config, não de um mapa paralelo),
   * e a de detalhe, que acrescenta o sufixo.
   */
  const screens: [string, string][] = [
    ['/admin', 'Início · Painel LAF'],
    ['/admin/paginas', 'Páginas · Painel LAF'],
    ['/admin/transparencia', 'Transparência · Painel LAF'],
    ['/admin/transparencia/novo', 'Novo documento · Painel LAF'],
    ['/admin/usuarios', 'Usuários · Painel LAF'],
    ['/admin/usuarios/novo', 'Novo usuário · Painel LAF'],
    ['/admin/auditoria', 'Auditoria · Painel LAF'],
    ['/conta', 'Minha conta · Painel LAF'],
    ['/admin/volunteer-applications', 'Voluntários · Painel LAF'],
    ['/admin/contact-messages', 'Mensagens de contato · Painel LAF'],
  ]

  for (const [path, expected] of screens) {
    test(`${path} → "${expected}"`, async ({ page }) => {
      await page.goto(path)

      await expect(page).toHaveTitle(expected)
    })
  }

  test('o detalhe de um formulário acrescenta o sufixo do recurso', async ({ page }) => {
    await page.goto('/admin/contact-messages')
    await page.locator('.table tbody tr').first().getByRole('link').click()

    await expect(page).toHaveTitle('Mensagens de contato — detalhe · Painel LAF')
  })

  /**
   * URL de recurso inexistente casa com /admin/:resource, e a tela mostrada é a de não
   * encontrada — o título tem de dizer a mesma coisa que a tela, não sobrar só o sufixo.
   */
  test('recurso inexistente usa o título da tela de não encontrada', async ({ page }) => {
    await page.goto('/admin/recurso-que-nao-existe')

    await expect(page).toHaveTitle('Página não encontrada · Painel LAF')
  })
})
