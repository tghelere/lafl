import { spawnSync } from 'node:child_process'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'

import { expect, test } from '@playwright/test'

import { AdminApi } from '../../support/api'
import { SITE_URL } from '../../support/env'

/**
 * Os endereços antigos das fotos do site (`/fotos/{secao}/{chave}-{largura}.{webp,jpg}`), que
 * existiram até a sessão 28, respondem 301 para o endereço atual em /midia/, e o destino é a
 * imagem. Pela pilha real: site (rota do Nitro) → API → 301 repassado ao visitante.
 *
 * A lista é a mesma do teste Pest (backend/tests/Fixtures/legacy-photo-paths.txt, os 144
 * arquivos do commit 071b95a). O banco de e2e começa sem fotos, então este spec roda
 * `midia:importar-fotos-iniciais` (como o globalSetup roda `e2e:prepare`) e, no fim, desfaz tudo
 * pela API: tira as fotos das páginas e as exclui, para os specs seguintes verem o banco como
 * era.
 */

const BACKEND_DIR = fileURLToPath(new URL('../../../backend/', import.meta.url))
const PATHS = readFileSync(`${BACKEND_DIR}tests/Fixtures/legacy-photo-paths.txt`, 'utf8')
  .split('\n')
  .map((line) => line.trim())
  .filter(Boolean)

const SLUGS_WITH_PHOTOS = ['quem-somos', 'quem-somos/nossa-historia', 'educacao-infantil', 'bazar', 'transparencia']

type Listed = { id: string }
type PageImagesBody = { data: { cover: Listed | null; gallery: Listed[] } }

let api: AdminApi
let imported: string[] = []

test.beforeAll(async () => {
  api = await AdminApi.as('direcao')
  const before = new Set((((await (await api.get('/api/v1/media', { per_page: 60 })).json()) as { data: Listed[] }).data).map((m) => m.id))

  const result = spawnSync('php', ['artisan', 'midia:importar-fotos-iniciais'], {
    cwd: BACKEND_DIR,
    env: { ...process.env, APP_ENV: 'e2e' },
    encoding: 'utf8',
  })
  expect(result.status, result.stdout + result.stderr).toBe(0)

  const after = ((await (await api.get('/api/v1/media', { per_page: 60 })).json()) as { data: Listed[] }).data
  imported = after.map((m) => m.id).filter((id) => !before.has(id))
  expect(imported).toHaveLength(24)
})

test.afterAll(async () => {
  const pages = ((await (await api.get('/api/v1/pages', { per_page: 100 })).json()) as { data: { id: string; slug: string }[] }).data

  for (const page of pages.filter((candidate) => SLUGS_WITH_PHOTOS.includes(candidate.slug))) {
    const { data } = (await (await api.get(`/api/v1/pages/${page.id}/images`)).json()) as PageImagesBody

    if (data.cover) {
      await api.delete(`/api/v1/pages/${page.id}/images/${data.cover.id}?role=cover`)
    }

    for (const item of data.gallery) {
      await api.delete(`/api/v1/pages/${page.id}/images/${item.id}?role=gallery`)
    }
  }

  for (const id of imported) {
    await api.delete(`/api/v1/media/${id}`)
  }

  await api.dispose()
})

test('os 144 endereços antigos respondem 301 para /midia/ na mesma largura', async ({ request }) => {
  expect(PATHS).toHaveLength(144)

  for (const path of PATHS) {
    const width = path.match(/-(\d+)\.(webp|jpg)$/)![1]
    const response = await request.get(`${SITE_URL}${path}`, { maxRedirects: 0 })

    expect(response.status(), path).toBe(301)
    expect(response.headers().location, path).toMatch(
      new RegExp(`^${SITE_URL}/midia/[0-9a-f-]{36}/${width}\\.webp$`),
    )
  }
})

test('seguir o redirecionamento entrega a imagem, também para HEAD', async ({ request }) => {
  const seguida = await request.get(`${SITE_URL}/fotos/historia/placa-inauguracao-640.jpg`)
  expect(seguida.status()).toBe(200)
  expect(seguida.headers()['content-type']).toBe('image/webp')

  const head = await request.head(`${SITE_URL}/fotos/bazar/bazar-entrada-400.webp`, { maxRedirects: 0 })
  expect(head.status()).toBe(301)
})

test('o mapa de /contato continua sendo arquivo, e endereço que nunca existiu dá 404', async ({ request }) => {
  const mapa = await request.get(`${SITE_URL}/fotos/contato/mapa-enderecos-1x.webp`, { maxRedirects: 0 })
  expect(mapa.status()).toBe(200)
  expect(mapa.headers()['content-type']).toBe('image/webp')
  expect(mapa.headers()['cache-control']).toBe('public, max-age=2592000')

  expect((await request.get(`${SITE_URL}/fotos/bazar/nao-existe-400.webp`, { maxRedirects: 0 })).status()).toBe(404)
  // A seção faz parte do endereço antigo: a placa morava em historia/, não em bazar/.
  expect((await request.get(`${SITE_URL}/fotos/bazar/placa-inauguracao-640.webp`, { maxRedirects: 0 })).status()).toBe(404)
})
