> **Modelo recomendado: Opus**

# 06 — SEO e indexação dos documentos de transparência

Leia `docs/tarefas/README.md`. Rode depois da 04 (usa a imagem Open Graph padrão).

## Por que isto importa mais que o normal

SEO é requisito de missão: o site existe, em boa parte, para que os documentos de prestação de
contas sejam encontrados no Google. Hoje: `sitemap.xml` só tem a home; nenhuma página tem
canonical, `og:url` ou `og:image`; os PDFs saem pela API como `attachment`, numa URL
`/api/v1/public/transparency-documents/{uuid}/download` sem nome descritivo — o pior formato
para indexação — e cada acesso de robô soma no `download_count`.

## Etapa 1 — Sitemap real

- Endpoint público de listagem mínima de páginas publicadas (slug e `updated_at`), paginado
  conforme a regra do projeto.
- `sitemap.xml` com as rotas fixas indexáveis, todas as páginas publicadas do CMS e todos os
  documentos publicados (URL nova da etapa 3). Fora: `noindex` (voluntariado, apoiar,
  `/obrigado/*`), rascunhos.
- Remover o comentário "Sitemap estático para o esqueleto" e resolver o item do roadmap.

## Etapa 2 — Metadados em todas as páginas

Canonical absoluto, `og:url`, `og:type`, `og:locale` `pt_BR`, `og:site_name`, `og:image`
(padrão `shared/brand/lar-analia-franco/og/og-padrao.png`, 1200×630, URL absoluta) e cartão do
Twitter/X — centralizado num composable ou no layout, não repetido página a página.

JSON-LD `NGO` na home: nome, CNPJ como identificador, logo (PNG), telefone, e **dois locais
distintos** (Sede/CEI e Bazar, endereços de `docs/contexto.md`). Contraturno descrito apenas
como programa em preparação — nunca como serviço em operação.

## Etapa 3 — PDFs indexáveis

- URL no domínio do site, legível e estável: ex. `/transparencia/documentos/2025/balanco-patrimonial-2025.pdf`.
  Slug gerado na criação e persistido (não recalculado do título a cada request — renomear o
  título não pode quebrar link já indexado); unicidade garantida; migration reversível.
- Servido por rota do servidor Nitro que faz proxy para a API, com
  `Content-Disposition: inline; filename="…"`, `Content-Type: application/pdf` e cache
  adequado. A URL antiga por UUID responde 301 para a nova.
- Botão "Baixar PDF" continua baixando (atributo `download` no link), mas a URL canônica é a
  inline.
- `download_count` não soma acessos de robôs conhecidos (lista de user agents em um lugar só,
  documentada); registrar no docblock que é aproximação.
- Título e metadados do próprio PDF não são alterados (documento oficial).

## Etapa 4 — Página de documentos

`/transparencia/documentos` com `<title>` e descrição que reflitam o filtro aplicado (ano,
tipo), canonical sem parâmetros de paginação desnecessários e links de paginação rastreáveis.

## Verificação

Pest (listagem pública, slug, 301, contagem sem robô), builds, e2e (baixar/abrir PDF pela URL
nova; URL antiga redireciona), conferir `curl -I` do PDF, validar o JSON-LD e o sitemap (XML
válido) e registrar no relatório.
