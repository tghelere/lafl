# Arquitetura

## Estrutura do monorepo

```
lar-analia-franco/
├── CLAUDE.md
├── docs/
│   ├── arquitetura.md
│   ├── protecao-de-dados.md
│   ├── dominio.md
│   ├── convencoes.md
│   └── lgpd/
│       └── inventario-de-dados.md      # a preencher com a instituição
├── backend/                            # Laravel
├── frontend-site/                      # Nuxt 3 — público
├── frontend-admin/                     # Vue 3 SPA — administrativo
└── docker/                             # compose para dev (postgres, redis, umami)
```

### Por que dois frontends

Vue 3 SPA e SEO não convivem bem: o Google renderiza JavaScript com fila e atraso, e
Bing/WhatsApp/redes sociais praticamente não renderizam. Para uma instituição que depende de
ser encontrada por doadores e famílias, isso é problema de missão.

- `frontend-site/` — Nuxt 3, SSR/SSG. Páginas institucionais como SSG; notícias com ISR.
- `frontend-admin/` — Vue 3 SPA. SEO irrelevante, `noindex`, atrás de login.

Ambos usam a mesma sintaxe (Vue 3 `<script setup>`, Pinia), então o custo cognitivo de manter
os dois é baixo.

## Camadas do backend

```
Route
  → Middleware (auth:sanctum, throttle, role)
  → FormRequest        validação + autorização de entrada
  → Controller         fino: recebe DTO, chama Action, devolve Resource
  → Action / Service   regra de negócio, transação, eventos
  → Model / Query      persistência
  → API Resource       serialização controlada da saída
```

**Responsabilidades que não podem vazar de camada:**

| Camada | Faz | Nunca faz |
|---|---|---|
| Controller | orquestra | regra de negócio, query, formatação |
| FormRequest | valida formato e autoriza | consulta regra de domínio complexa |
| Action | regra de negócio, transação | conhece HTTP, `Request` ou `Response` |
| Model | relacionamentos, casts, scopes | regra de negócio, envio de e-mail |
| Resource | decide o que sai | consulta ao banco (causa N+1) |

Eventos/Listeners para efeitos colaterais: e-mail, notificação, log de auditoria, geração de
thumbnail. Nunca dentro da Action principal.

## Contrato da API

- Versionada: `/api/v1/...`
- Recursos no plural, em inglês: `/api/v1/assisted-minors`, `/api/v1/guardians`
- Identificadores sempre UUID
- Paginação padrão do Laravel, `per_page` limitado no servidor (teto de 100)
- Filtros via query string, com whitelist explícita — nunca repasse direto ao Eloquent
- Erros de validação: formato padrão do Laravel (`422` com `errors`)
- Demais erros: RFC 7807 (`type`, `title`, `status`, `detail`)
- Datas em ISO 8601, UTC no transporte, `America/Sao_Paulo` na exibição

### Códigos de resposta

| Situação | Código |
|---|---|
| Leitura ok | 200 |
| Criação ok | 201 |
| Ação sem retorno | 204 |
| Validação falhou | 422 |
| Não autenticado | 401 |
| Autenticado, sem permissão | 403 |
| Recurso inexistente **ou** sem permissão de vê-lo | 404 |
| Rate limit | 429 |

Nota sobre o 404: para dados de assistido, "existe mas você não pode ver" deve responder
**404**, não 403. Confirmar existência já é vazamento.

## Autenticação

Sanctum em **SPA mode**:

1. Front chama `GET /sanctum/csrf-cookie`
2. `POST /login` com credenciais → sessão em cookie `httpOnly`
3. Requisições subsequentes enviam cookie + header `X-XSRF-TOKEN`

Configuração exigida:

- `SANCTUM_STATEFUL_DOMAINS` com os domínios do front
- `SESSION_DOMAIN` no domínio raiz compartilhado (`.dominio.org.br`)
- CORS restrito aos domínios do front, `supports_credentials: true`
- Cookie: `httpOnly`, `secure` em produção, `SameSite=Lax`

**Consequência de infraestrutura:** front e API precisam compartilhar domínio raiz — por
exemplo `dominio.org.br` e `api.dominio.org.br`. Em desenvolvimento funciona com `localhost`
em portas diferentes, porque cookie ignora porta.

**SSR do Nuxt:** chamadas server-side precisam repassar o cookie da requisição original. Usar
um plugin de fetch que propague `cookie` em contexto de servidor. O site público idealmente
consome apenas endpoints públicos, evitando o problema.

**2FA obrigatório** para contas com acesso a dados de assistido.

## Segurança

- HTTPS obrigatório, HSTS
- Rate limit diferenciado: login e recuperação de senha bem mais restritos que leitura pública
- Headers: CSP, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`
- Uploads: validação por MIME real (não extensão), armazenamento fora do webroot, servidos por
  rota autenticada com Policy
- Chave de criptografia de campo **separada** do `APP_KEY`, fora do repositório
- Backups criptografados, testados, com chave guardada em local distinto do dump
- Dependências auditadas (`composer audit`, `npm audit`) em CI

## Performance

**Índices** (planejados, não improvisados):

- Toda FK
- Colunas de `WHERE`, `ORDER BY`, `JOIN`
- Compostos na ordem real da query, coluna mais seletiva primeiro
- Parciais para `WHERE deleted_at IS NULL` e `WHERE status = 'published'`
- GIN para JSONB e busca textual
- Blind indexes (ver `@docs/protecao-de-dados.md`)

Resistir ao impulso de indexar tudo: cada índice encarece escrita.

**Aplicação:**

- Eager loading disciplinado; `preventLazyLoading()` em dev
- Redis para cache e filas
- `Cache-Control` e ETag em endpoints públicos
- Filas para e-mail, thumbnails, relatórios
- Imagens convertidas para WebP no upload, thumbnails em fila
- OPcache ligado, php-fpm dimensionado
- `pg_stat_statements` habilitado; Telescope em dev
- gzip/Brotli no nginx (Brotli para estáticos, gzip para JSON)

**Frontend:**

- Code splitting e rotas lazy
- Imagens responsivas com `<NuxtImg>`
- Core Web Vitals monitorados via Search Console

## SEO (site público)

- SSR/SSG por padrão; nada de conteúdo institucional dependente de JS
- Meta tags e Open Graph por página, alimentadas pela API
- `sitemap.xml` gerado a partir de `updated_at` dos conteúdos
- `robots.txt` liberando o site e bloqueando o admin
- JSON-LD com schema `NGO` / `Organization`
- URLs semânticas por slug único, com redirect 301 ao mudar slug
- `alt` obrigatório em toda imagem — validado na API, não só na interface
- Campos de meta title e meta description editáveis no painel

## Analytics

**Umami** (Cloud, plano Hobby gratuito; migrar para auto-hospedado se a retenção de 6 meses
apertar). Cookieless — sem cookie, sem localStorage, identidade por hash salgado rotativo no
servidor. Portanto **não exige banner de consentimento**.

**Google Search Console** para dados de busca orgânica.

Eventos personalizados a instrumentar: cliques em doação/PIX, envio de formulário de contato,
envio de formulário de voluntariado, clique no WhatsApp, download de relatório de
transparência.

Se algum dia entrar cookie não essencial, o banner volta à pauta — e aí com recusa tão
visível quanto o aceite.

## Ambientes e deploy

- `local` — Docker Compose: PHP, Postgres, Redis, Mailpit
- `staging` — espelho de produção, **sem dado real de assistido**
- `production` — VPS ou serviço gerenciado, backup diário automatizado

CI: Pint, PHPStan, Pest, `composer audit`, `npm audit`, build dos dois frontends.
