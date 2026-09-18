import { expect, test } from '@playwright/test'

import { SITE_URL } from '../../support/env'
import { serverHtml } from '../../support/site'

/**
 * Cobertura da tarefa 06 (docs/tarefas/06-seo-e-pdfs-da-transparencia.md), etapa 2: toda
 * página indexável sai com canônico absoluto, Open Graph completo e cartão de link; a home
 * traz o JSON-LD da instituição; e página `noindex` não emite nada disso.
 *
 * Lê o HTML que o SERVIDOR entregou, não o DOM depois do JavaScript: é assim que o buscador e
 * o rastreador de link do WhatsApp leem a página.
 */
const INDEXAVEIS = [
  '/',
  '/o-que-fazemos',
  '/politica-de-privacidade',
  '/contato',
  '/quem-somos',
  '/doar',
  '/transparencia/documentos',
]

function conteudoDaMeta(html: string, atributo: string, nome: string): string | null {
  const escapado = nome.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  const padrao = new RegExp(`<meta[^>]*${atributo}="${escapado}"[^>]*content="([^"]*)"`, 'i')

  return html.match(padrao)?.[1] ?? null
}

function canonical(html: string): string | null {
  return html.match(/<link[^>]*rel="canonical"[^>]*href="([^"]*)"/i)?.[1] ?? null
}

test.describe('metadados de cada página', () => {
  for (const caminho of INDEXAVEIS) {
    test(`${caminho} sai com canônico absoluto e Open Graph completo`, async () => {
      const html = await serverHtml(caminho)

      expect(canonical(html)).toBe(`${SITE_URL}${caminho}`)
      expect(conteudoDaMeta(html, 'property', 'og:url')).toBe(`${SITE_URL}${caminho}`)
      expect(conteudoDaMeta(html, 'property', 'og:type')).toBe('website')
      expect(conteudoDaMeta(html, 'property', 'og:locale')).toBe('pt_BR')
      expect(conteudoDaMeta(html, 'property', 'og:site_name')).toBe('Lar Anália Franco')
      expect(conteudoDaMeta(html, 'property', 'og:image')).toBe(`${SITE_URL}/og/og-padrao.png`)
      expect(conteudoDaMeta(html, 'name', 'twitter:card')).toBe('summary_large_image')

      // Título e descrição são conteúdo, não estrutura — aqui só confere que existem.
      expect(conteudoDaMeta(html, 'property', 'og:title')).toBeTruthy()
      expect(conteudoDaMeta(html, 'name', 'description')).toBeTruthy()
    })
  }

  test('a imagem Open Graph padrão existe e é servida como imagem', async ({ request }) => {
    const response = await request.get(`${SITE_URL}/og/og-padrao.png`)

    expect(response.status()).toBe(200)
    expect(response.headers()['content-type']).toContain('image/png')
  })

  test('página noindex não emite canônico nem cartão de compartilhamento', async () => {
    const html = await serverHtml('/como-ajudar/voluntariado')

    expect(conteudoDaMeta(html, 'name', 'robots')).toBe('noindex, nofollow')
    expect(canonical(html)).toBeNull()
    expect(conteudoDaMeta(html, 'property', 'og:url')).toBeNull()
    expect(conteudoDaMeta(html, 'name', 'twitter:card')).toBeNull()
  })
})

test.describe('JSON-LD da instituição', () => {
  test('a home traz um NGO com CNPJ, logo e os dois locais distintos', async () => {
    const html = await serverHtml('/')
    const bruto = html.match(/<script type="application\/ld\+json"[^>]*>([\s\S]*?)<\/script>/)?.[1]

    expect(bruto, 'a home deveria trazer um bloco JSON-LD').toBeTruthy()

    const dados = JSON.parse(bruto!)

    expect(dados['@context']).toBe('https://schema.org')
    expect(dados['@type']).toBe('NGO')
    expect(dados.identifier).toMatchObject({ propertyID: 'CNPJ', value: '78.614.096/0001-75' })
    expect(dados.logo).toBe(`${SITE_URL}/brand/lar-analia-franco-horizontal-600.png`)
    expect(dados.telephone).toBeTruthy()
    // Data de fundação calculada pela API, não digitada na página (config/institution.php).
    expect(dados.foundingDate).toBe('1953-07-12')

    const locais = dados.location as Array<{ name: string; address: { streetAddress: string } }>
    expect(locais).toHaveLength(2)
    expect(locais[0]!.address.streetAddress).toContain('Anália Franco, 33')
    expect(locais[1]!.address.streetAddress).toContain('Rosa Siqueira, 152')
  })

  test('o contraturno nunca aparece como serviço em operação', async () => {
    const html = await serverHtml('/')
    const dados = JSON.parse(
      html.match(/<script type="application\/ld\+json"[^>]*>([\s\S]*?)<\/script>/)![1]!,
    )

    // O programa não tem turma nem inscrição aberta (ver docs/contexto.md): marcá-lo como
    // oferta ou serviço faria o Google anunciar uma vaga que não existe.
    for (const chave of ['makesOffer', 'hasOfferCatalog', 'offers', 'availableService']) {
      expect(dados[chave], `${chave} não pode existir enquanto o contraturno não abrir`).toBeUndefined()
    }

    expect(dados.description).toContain('programa em preparação')
  })
})
