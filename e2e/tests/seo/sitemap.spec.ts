import { expect, test } from '@playwright/test'

import { SITE_URL } from '../../support/env'

/**
 * Cobertura da tarefa 06 (docs/tarefas/06-seo-e-pdfs-da-transparencia.md), etapa 1: o
 * sitemap declara o site que existe de verdade — rotas fixas, páginas publicadas do CMS e os
 * PDFs de transparência —, e não declara o que não pode ser indexado.
 *
 * A bateria roda contra o acervo do E2eSeeder, então os endereços conferidos aqui são os que
 * ele semeia.
 */
test.describe('sitemap.xml', () => {
  let xml: string

  test.beforeAll(async ({ request }) => {
    const response = await request.get(`${SITE_URL}/sitemap.xml`)

    expect(response.status()).toBe(200)
    expect(response.headers()['content-type']).toContain('xml')

    xml = await response.text()
  })

  test('é XML bem formado, com uma <loc> absoluta por URL', () => {
    expect(xml.startsWith('<?xml version="1.0" encoding="UTF-8"?>')).toBe(true)

    const locs = [...xml.matchAll(/<loc>(.*?)<\/loc>/g)].map((match) => match[1]!)

    expect(locs.length).toBeGreaterThan(20)
    expect(locs.every((loc) => loc.startsWith(SITE_URL))).toBe(true)
    // Endereço repetido divide o sinal de busca entre duas entradas iguais.
    expect(new Set(locs).size).toBe(locs.length)
  })

  test('traz as rotas fixas, as páginas do CMS e os PDFs de transparência', () => {
    for (const caminho of [
      '/',
      '/o-que-fazemos',
      '/politica-de-privacidade',
      '/contato',
      '/transparencia/documentos',
      // Página do CMS, publicada.
      '/quem-somos/nossa-historia',
      // Slug "como-ajudar/doar" servido em /doar — é o endereço final que entra, não o antigo.
      '/doar',
      // PDF do acervo semeado, pela URL legível da etapa 3.
      '/transparencia/documentos/2024/balanco-patrimonial-2024.pdf',
    ]) {
      expect(xml, `sitemap deveria listar ${caminho}`).toContain(`<loc>${SITE_URL}${caminho}</loc>`)
    }
  })

  test('não traz nada que seja noindex, rascunho ou redirect', () => {
    for (const caminho of [
      // noindex (ver as próprias páginas: robots meta)
      '/como-ajudar/voluntariado',
      '/contraturno/apoiar',
      '/obrigado/contato',
      // Rascunho no CMS — não pode aparecer nem no sitemap nem no site.
      '/quem-somos/missao-visao-valores',
      '/educacao-infantil/depoimentos',
      // Endereço antigo, que responde 301 para /doar.
      '/como-ajudar/doar',
    ]) {
      expect(xml, `sitemap não deveria listar ${caminho}`).not.toContain(`<loc>${SITE_URL}${caminho}</loc>`)
    }
  })
})
