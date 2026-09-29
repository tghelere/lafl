import { deflateSync } from 'node:zlib'

import type { APIResponse } from '@playwright/test'

import { AdminApi } from './api'

/**
 * PDF mínimo de uma página em branco, o mesmo formato que os seeders usam — nenhum arquivo
 * real da instituição entra no repositório (CLAUDE.md, regra 10). Precisa ser um PDF de
 * verdade porque a validação da API é por mimetype, não por extensão.
 */
export const placeholderPdf = Buffer.from(
  '%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n' +
    '2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n' +
    '3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << >> >>\nendobj\n' +
    'trailer\n<< /Size 4 /Root 1 0 R >>\n%%EOF\n',
)

/**
 * PNG de cor sólida, gerado na hora — nenhuma foto no repositório (CLAUDE.md, regra 10). Só
 * `node:zlib`: um PNG é a assinatura, o IHDR, os pixels comprimidos (cada linha precedida do
 * byte de filtro 0) e o IEND, cada bloco com o seu CRC-32.
 *
 * Imagem de verdade, e não bytes quaisquer: a API confere o tipo pelo conteúdo e decodifica
 * para gerar as derivadas.
 */
export function solidPng(width: number, height: number, [r, g, b]: [number, number, number]): Buffer {
  const row = Buffer.alloc(1 + width * 3)
  for (let x = 0; x < width; x++) {
    row[1 + x * 3] = r
    row[2 + x * 3] = g
    row[3 + x * 3] = b
  }

  const ihdr = Buffer.alloc(13)
  ihdr.writeUInt32BE(width, 0)
  ihdr.writeUInt32BE(height, 4)
  ihdr[8] = 8 // profundidade de bits
  ihdr[9] = 2 // RGB

  return Buffer.concat([
    Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
    pngChunk('IHDR', ihdr),
    pngChunk('IDAT', deflateSync(Buffer.concat(Array.from({ length: height }, () => row)))),
    pngChunk('IEND', Buffer.alloc(0)),
  ])
}

function pngChunk(type: string, data: Buffer): Buffer {
  const length = Buffer.alloc(4)
  length.writeUInt32BE(data.length)
  const body = Buffer.concat([Buffer.from(type, 'ascii'), data])
  const crc = Buffer.alloc(4)
  crc.writeUInt32BE(crc32(body))

  return Buffer.concat([length, body, crc])
}

function crc32(bytes: Buffer): number {
  let crc = 0xffffffff
  for (const byte of bytes) {
    crc ^= byte
    for (let bit = 0; bit < 8; bit++) {
      crc = crc & 1 ? (crc >>> 1) ^ 0xedb88320 : crc >>> 1
    }
  }

  return (crc ^ 0xffffffff) >>> 0
}

export type CreatedUser = { id: string; name: string; email: string; roles: string[] }

export type CreatedPage = { id: string; slug: string; title: string; content: string }

function ok(response: APIResponse, what: string): APIResponse {
  if (!response.ok()) {
    throw new Error(`${what} respondeu ${response.status()}`)
  }

  return response
}

/**
 * Cria pela API a página que um teste vai editar pela interface. Criar página é ação de
 * `direcao`/`super_admin` e o painel não oferece a tela (ver App\Policies\PagePolicy) — a API
 * é o caminho honesto para montar o cenário, em vez de um teste depender do conteúdo que
 * outro teste deixou para trás.
 */
export async function createPage(
  api: AdminApi,
  data: { slug: string; title: string; content: string; status?: 'draft' | 'published' },
): Promise<CreatedPage> {
  const response = ok(
    await api.post('/api/v1/pages', {
      slug: data.slug,
      title: data.title,
      content: data.content,
      meta_title: null,
      meta_description: null,
      status: data.status ?? 'published',
    }),
    'POST /api/v1/pages',
  )

  const body = (await response.json()) as { data: CreatedPage }

  return body.data
}

export async function deletePage(api: AdminApi, uuid: string): Promise<void> {
  await api.delete(`/api/v1/pages/${uuid}`)
}

export async function createUser(
  api: AdminApi,
  data: { name: string; email: string; roles: string[] },
): Promise<CreatedUser> {
  const response = ok(await api.post('/api/v1/users', data), 'POST /api/v1/users')
  const body = (await response.json()) as { data: CreatedUser }

  return body.data
}

/** A URL de /definir-senha só existe nesta resposta — ver App\Actions\Users\GeneratePasswordLink. */
export async function generatePasswordLink(api: AdminApi, uuid: string): Promise<string> {
  const response = ok(
    await api.post(`/api/v1/users/${uuid}/password-link`),
    `POST /api/v1/users/${uuid}/password-link`,
  )
  const body = (await response.json()) as { data: { url: string } }

  return body.data.url
}

/** uuid da conta de quem está autenticado neste cliente. */
export async function currentUserId(api: AdminApi): Promise<string> {
  const response = ok(await api.get('/api/v1/auth/user'), 'GET /api/v1/auth/user')
  const body = (await response.json()) as { data: { id: string } }

  return body.data.id
}

export type GalleryPhoto = {
  alt: string
  caption?: string
  credit?: string
  width: number
  height: number
  color: [number, number, number]
}

export type PageWithPhotos = {
  page: CreatedPage
  /** Uuids na ordem: galeria primeiro, depois a imagem do texto (se houver). */
  mediaIds: string[]
}

/**
 * Página publicada com galeria e, se pedido, uma figura no meio do texto — o cenário da
 * ampliação. As fotos entram pela rota de "Imagens desta página" (galeria) e pela biblioteca
 * (texto), como o painel faria. `removePageWithPhotos` desfaz tudo.
 */
export async function createPageWithPhotos(
  api: AdminApi,
  data: { slug: string; title: string; gallery: GalleryPhoto[]; inText?: GalleryPhoto },
): Promise<PageWithPhotos> {
  const mediaIds: string[] = []
  let content = '<p>Texto antes da imagem.</p>'

  if (data.inText) {
    const response = ok(await api.upload('/api/v1/media', photoFields(data.inText)), 'POST /api/v1/media')
    const id = ((await response.json()) as { data: { id: string } }).data.id
    const caption = data.inText.caption ? `<figcaption>${data.inText.caption}</figcaption>` : ''
    content += `<figure><img src="/midia/${id}" alt="${data.inText.alt}" />${caption}</figure><p>Texto depois.</p>`
    mediaIds.push(id)
  }

  const page = await createPage(api, { slug: data.slug, title: data.title, content })

  for (const photo of data.gallery) {
    const response = ok(await api.upload(`/api/v1/pages/${page.id}/images`, photoFields(photo)), 'POST /pages/{page}/images')
    mediaIds.splice(mediaIds.length - (data.inText ? 1 : 0), 0, ((await response.json()) as { data: { id: string } }).data.id)
  }

  return { page, mediaIds }
}

export async function removePageWithPhotos(api: AdminApi, created: PageWithPhotos): Promise<void> {
  await deletePage(api, created.page.id)

  for (const id of created.mediaIds) {
    await api.delete(`/api/v1/media/${id}`)
  }
}

function photoFields(photo: GalleryPhoto): Record<string, string | { name: string; mimeType: string; buffer: Buffer }> {
  return {
    file: { name: 'foto.png', mimeType: 'image/png', buffer: solidPng(photo.width, photo.height, photo.color) },
    alt: photo.alt,
    ...(photo.caption ? { caption: photo.caption } : {}),
    ...(photo.credit ? { credit: photo.credit } : {}),
    depicts_assisted_minor: '0',
  }
}
