import { spawn, type ChildProcess } from 'node:child_process'
import { fileURLToPath } from 'node:url'

import { expect, request, test } from '@playwright/test'

import { API_URL, BIND_HOST, SITE_URL, STAGING_SITE_URL, portOf } from '../../support/env'

const SITE_DIR = fileURLToPath(new URL('../../../frontend-site/', import.meta.url))

/**
 * Cobertura da tarefa 07 (docs/tarefas/07-caminho-ate-a-producao.md), Etapa 4: em `staging`
 * todo o site responde `X-Robots-Tag: noindex, nofollow` e o robots.txt bloqueia tudo.
 *
 * O que dá sentido a este arquivo é subir o site DUAS VEZES a partir do MESMO `.output`
 * (o build que o webServer do playwright.config.ts já fez): uma instância normal, na porta de
 * sempre, e outra com `NUXT_PUBLIC_ENVIRONMENT=staging` na porta ao lado. Se o bloqueio
 * estivesse gravado no pacote — via `routeRules`, `nuxt generate` ou qualquer decisão de
 * build — as duas instâncias responderiam igual e metade dos casos aqui falharia. É
 * exatamente o engano que a homologação não pode cometer: o pacote que sobe em produção é o
 * mesmo que passou pela homologação.
 *
 * A lista de caminhos não é decorativa. Cada linha cobre um caminho de resposta DIFERENTE do
 * Nitro, e eles não compartilham o mesmo gancho (ver server/plugins/staging-noindex.ts):
 * página SSR, rota prerenderizada, arquivo estático de `public/`, rota de servidor e página
 * de erro.
 */
const PATHS = [
  { path: '/', descricao: 'home (SSR)' },
  { path: '/quem-somos', descricao: 'página do CMS (SSR)' },
  { path: '/o-que-fazemos', descricao: 'página prerenderizada' },
  { path: '/obrigado/contato', descricao: 'confirmação prerenderizada' },
  { path: '/transparencia/documentos', descricao: 'listagem que consulta a API' },
  { path: '/sitemap.xml', descricao: 'sitemap prerenderizado' },
  { path: '/robots.txt', descricao: 'rota de servidor' },
  { path: '/favicon.svg', descricao: 'arquivo estático de public/' },
  { path: '/rota-que-nao-existe', descricao: 'página de erro (404)' },
]

let staging: ChildProcess | undefined

test.beforeAll(async () => {
  staging = spawn('node', ['.output/server/index.mjs'], {
    cwd: SITE_DIR,
    env: {
      ...process.env,
      HOST: BIND_HOST,
      PORT: portOf(STAGING_SITE_URL),
      NUXT_PUBLIC_ENVIRONMENT: 'staging',
      NUXT_PUBLIC_API_URL: API_URL,
      NUXT_PUBLIC_SITE_URL: STAGING_SITE_URL,
    },
    stdio: 'ignore',
  })

  await waitForServer(STAGING_SITE_URL)
})

test.afterAll(() => {
  staging?.kill()
})

test.describe('site em homologação — não indexável', () => {
  for (const { path, descricao } of PATHS) {
    test(`${descricao} (${path}) responde X-Robots-Tag: noindex, nofollow`, async () => {
      const response = await (await request.newContext()).get(`${STAGING_SITE_URL}${path}`)

      expect(response.headers()['x-robots-tag']).toBe('noindex, nofollow')
    })
  }

  test('robots.txt bloqueia o site inteiro', async () => {
    const response = await (await request.newContext()).get(`${STAGING_SITE_URL}/robots.txt`)
    const body = await response.text()

    expect(response.status()).toBe(200)
    expect(body).toContain('User-agent: *')
    expect(body).toContain('Disallow: /')
    // Nem "Allow", nem a diretiva Sitemap — anunciar o sitemap da homologação entregaria ao
    // buscador a lista de tudo o que não pode ser indexado.
    expect(body).not.toContain('Allow:')
    expect(body).not.toContain('Sitemap:')
  })
})

/**
 * O contraprova, na instância normal do mesmo build: nada disso vale fora de `staging`. Sem
 * estes dois casos, um cabeçalho colado em toda resposta — inclusive a de produção — passaria
 * como sucesso.
 */
test.describe('site em produção — indexável', () => {
  for (const { path, descricao } of PATHS) {
    test(`${descricao} (${path}) não recebe X-Robots-Tag`, async () => {
      const response = await (await request.newContext()).get(`${SITE_URL}${path}`)

      expect(response.headers()['x-robots-tag']).toBeUndefined()
    })
  }

  test('robots.txt libera o site', async () => {
    const response = await (await request.newContext()).get(`${SITE_URL}/robots.txt`)
    const body = await response.text()

    expect(body).toContain('Allow: /')
    expect(body).not.toContain('Disallow:')
  })
})

async function waitForServer(url: string): Promise<void> {
  const context = await request.newContext()
  const deadline = Date.now() + 60_000

  while (Date.now() < deadline) {
    try {
      const response = await context.get(`${url}/robots.txt`)

      if (response.ok()) {
        return
      }
    } catch {
      // Servidor ainda subindo — tentar de novo.
    }

    await new Promise((resolve) => setTimeout(resolve, 500))
  }

  throw new Error(`A segunda instância do site (${url}) não subiu a tempo.`)
}
