import { type Page, expect } from '@playwright/test'

import { SITE_URL } from './env'

/**
 * Abre uma rota do site público. O site é servido pelo Nitro em SSR a cada requisição (ver
 * frontend-site/nuxt.config.ts: nenhuma rota que lê conteúdo do CMS é prerenderizada), então
 * a alteração salva no painel aparece já na requisição seguinte — é exatamente essa promessa
 * que os testes daqui verificam.
 */
export async function gotoSite(page: Page, path: string): Promise<void> {
  await page.goto(`${SITE_URL}${path}`)
}

/** O HTML que o servidor entregou, antes de qualquer coisa que o navegador faça com ele. */
export async function serverHtml(path: string): Promise<string> {
  const response = await fetch(`${SITE_URL}${path}`)

  expect(response.ok, `GET ${path} no site público respondeu ${response.status}`).toBe(true)

  return response.text()
}

/**
 * Só o HTML do conteúdo vindo do CMS (`<div class="page-content">`), sem o resto do documento.
 *
 * Existe porque o documento inteiro do Nuxt tem `<script>` legítimo (importmap, entrada do
 * bundle, payload do SSR): procurar "<script" no HTML completo acusaria a própria página.
 * Quem precisa estar limpo é o trecho que sai de `pages.content` — é ele que a allowlist do
 * backend governa.
 */
export async function serverPageContent(path: string): Promise<string> {
  const html = await serverHtml(path)
  const match = html.match(/<div class="page-content"[^>]*>([\s\S]*?)<\/div><\/article>/)

  expect(match, `não encontrei o conteúdo do CMS no HTML de ${path}`).not.toBeNull()

  return match![1]!
}
