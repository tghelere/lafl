# Levantamento — painel administrativo e backend

> Sessão de levantamento (somente leitura, sem alteração de código ou banco). Objetivo:
> inventariar o estado atual para planejar três frentes — conteúdo editável pelo pessoal do
> Lar, gestão de usuários e tela inicial do painel. Todo fato abaixo foi verificado lendo o
> código, com caminho de arquivo citado.

## 1. Painel (frontend-admin)

**Rotas** (`frontend-admin/src/router/index.ts`):

| Rota | Nome | Componente | Observação |
|---|---|---|---|
| `/login` | `login` | `views/LoginView.vue` | `meta: { public: true }` |
| `/` | — | redirect | vai para `dashboard` |
| `/admin` | `dashboard` | `views/DashboardView.vue` | tela Início |
| `/admin/:resource` | `submissions.index` | `views/SubmissionListView.vue` | genérica, config-driven |
| `/admin/:resource/:uuid` | `submissions.show` | `views/SubmissionDetailView.vue` | genérica, config-driven |

Guarda de rota (`router.beforeEach`): se a rota não é `public`, exige
`authStore.isAuthenticated`; sem sessão, tenta `fetchCurrentUser()` e, falhando, redireciona a
`login`. Não há checagem de papel no guard — a filtragem por papel acontece só no menu (ver
abaixo) e a autorização real é 403 da API.

**Telas existentes** (`frontend-admin/src/views/`): `LoginView.vue`, `DashboardView.vue`,
`SubmissionListView.vue`, `SubmissionDetailView.vue`. Não existe tela de conteúdo (`pages`),
transparência ou usuários.

**Menu** (`frontend-admin/src/components/AppSidebar.vue`): monta a navegação lendo
`authStore.user?.roles` (array de strings) e filtrando com `hasAnyRole(...)`:

- Bloco "Início" (`Pendências`, `/admin`) — sempre visível.
- Bloco "Atendimento" — visível se `direcao`, `atendimento` ou `super_admin`; linka
  `program-applications`, `partnership-inquiries`, `volunteer-applications`,
  `contact-messages`.
- Bloco "Bazar" — visível se `direcao`, `bazar` ou `super_admin`; linka `pickup-requests`.

Comentário no próprio arquivo confirma a regra do CLAUDE.md: o menu só esconde link, quem
autoriza de fato é a Policy da API.

**Grade de cards da tela Início**: `views/DashboardView.vue`. Busca
`fetchDashboardSummary()` (`services/dashboard.ts`) e renderiza um `RouterLink` por entrada
retornada, dentro de `<div class="summary-grid">`. Cada card mostra `entry.pending` e o
título mapeado via `TYPE_TO_RESOURCE` + `SUBMISSION_RESOURCES` (config, ver abaixo). Se a
lista vem vazia (papel sem acesso a nenhum dos cinco formulários), mostra mensagem fixa
dizendo que o acesso do usuário é "a conteúdo (páginas, notícias, mídia) — ainda não
implementado nesta fatia". Não há card de "usuários" nem de "conteúdo" hoje — só os cinco
tipos de formulário.

**Configuração central dos cinco recursos**: `frontend-admin/src/config/submissionResources.ts`
— um registro (`SUBMISSION_RESOURCES`) por entidade de formulário (`program-applications`,
`pickup-requests`, `volunteer-applications`, `partnership-inquiries`, `contact-messages`),
cada um com `listColumns` e `detailFields`. É essa config que dirige as telas genéricas de
lista/detalhe — não há entrada para `pages` ou `users`.

## 2. Usuários e autenticação

**Endpoints existentes** (`backend/routes/api_v1.php`, grupo `auth.`):

- `POST /api/v1/auth/login` (`AuthController::login`, `throttle:login`)
- `POST /api/v1/auth/logout` (auth:sanctum)
- `GET /api/v1/auth/user` (auth:sanctum)
- `PUT /api/v1/auth/password` (auth:sanctum, troca de senha autenticada)

**Não existe** nenhum endpoint de CRUD de usuários (`UserController`), nenhum endpoint de
convite/criação de conta pelo painel, e **nenhum endpoint de redefinição de senha por
e-mail** (não há rota `forgot-password`/`reset-password` em `routes/api_v1.php`, nem
`routes/api.php`/`routes/web.php` — conferidos, só têm as rotas padrão do Laravel). O fluxo
de "esqueci minha senha" **não está implementado de ponta a ponta**: não existe link no
`LoginView.vue` (`frontend-admin/src/views/LoginView.vue` — só campos de e-mail/senha e botão
"Entrar"), não existe Controller, Action, Notification ou Mailable de redefinição de senha em
`backend/app/`.

**Colunas da tabela `users`** (migrations
`backend/database/migrations/0001_01_01_000000_create_users_table.php` +
`2026_08_18_114523_add_uuid_and_two_factor_columns_to_users_table.php`):

`id`, `uuid` (adicionada depois, rota pública usa só o uuid — `User::getRouteKeyName()`),
`name`, `email` (unique), `email_verified_at`, `password`, `two_factor_secret` (nullable,
cast `encrypted`), `two_factor_recovery_codes` (nullable, cast `encrypted`),
`two_factor_confirmed_at` (nullable), `remember_token`, `created_at`, `updated_at`.

**Não existe coluna de ativo/inativo** (nenhum `is_active`, `disabled_at`, `suspended_at` ou
equivalente). Também não existe fluxo de setup/desafio de 2FA — o comentário na migration diz
explicitamente "2FA: só schema nesta fatia, sem fluxo de setup/desafio" — só as colunas.

**Como papéis são atribuídos hoje**: só via seeder, não há endpoint nem tela.
`RoleSeeder.php` (`backend/database/seeders/RoleSeeder.php`) cria os cinco papéis
(`Role::cases()`) via `Role::findOrCreate`. `DevSuperAdminSeeder.php`
(`backend/database/seeders/DevSuperAdminSeeder.php`) cria/atualiza um usuário
`dev@laranaliafranco.local` e roda `$user->assignRole(Role::SuperAdmin->value)` — mas só se
`app()->environment(['local', 'testing'])`, nunca em produção. Não há nenhum outro ponto do
código que chame `assignRole`/`syncRoles` fora desses dois seeders.

**Enum de papéis** (`backend/app/Enums/Role.php`): `SuperAdmin`, `Direcao`, `Atendimento`,
`Bazar`, `Comunicacao`. Comentário no enum registra que os papéis são "agrupamentos de
conveniência", a matriz de autorização real vive nas Policies.

**Policies ligadas a usuários**: **não existe `UserPolicy`** (busca por `class UserPolicy` em
todo `app/` não retornou nada). Não há Gate nem policy registrada para o model `User` em
`backend/app/Providers/AppServiceProvider.php` — o único provider de aplicação além do
padrão do Laravel. Esse provider registra só um `Gate::before` global:
`fn (User $user, string $ability) => $user->hasRole(Role::SuperAdmin->value) ? true : null`
(bypass geral do `super_admin` para qualquer ability, incluindo uma futura de usuários).

**Configuração de envio de e-mail**: `backend/config/mail.php`,
`'default' => env('MAIL_MAILER', 'log')` — mailer padrão é `log` (nada é enviado de fato a
menos que `.env` configure `MAIL_MAILER=smtp` ou outro driver). Mailers configurados no
arquivo: `smtp`, e os demais defaults do Laravel (array/log/failover não alterados). O único
uso de e-mail hoje no projeto é `App\Mail\FormSubmissionReceived` (notificação de formulário
recebido, endereços placeholder em `config('forms.notification_recipients')`,
`backend/config/forms.php`) — não há Mailable nem Notification relacionada a usuário/senha.

## 3. Conteúdo

Classificação de cada rota pública do Nuxt (`frontend-site/app/pages/`):

| Rota (arquivo) | Fonte |
|---|---|
| `pages/index.vue` | (b) fixo — pilares hardcoded no `<script setup>` |
| `pages/o-que-fazemos.vue` | (b) fixo — lê rótulos de `app/config/navigation.ts` (arquivo de código, não CMS) |
| `pages/doar.vue` | (a) API — `usePublicPage('como-ajudar/doar')`, `v-html="page.content"` |
| `pages/contato.vue` | (b) fixo — formulário `<form method="post" action="/api/forms/contato">`, sem `usePublicPage` |
| `pages/politica-de-privacidade.vue` | (b) fixo — conteúdo hardcoded no template, versionado com o código (comentário no arquivo confirma: "fora do CMS — conteúdo fixo") |
| `pages/quem-somos/index.vue` | (a) API — `usePublicPage`, `v-html` |
| `pages/quem-somos/nossa-historia.vue` | (a) API — `usePublicPage`, `v-html` |
| `pages/educacao-infantil/index.vue` | (a) API — `usePublicPage`, `v-html` |
| `pages/bazar/index.vue` | (a) API — `usePublicPage`, `v-html` |
| `pages/bazar/agendar-coleta.vue` | (b) fixo — formulário, sem `usePublicPage` |
| `pages/contraturno/inscricao.vue` | (b) fixo — formulário |
| `pages/contraturno/apoiar.vue` | (b) fixo — formulário |
| `pages/como-ajudar/voluntariado.vue` | (b) fixo — formulário |
| `pages/transparencia/index.vue` | (a) API — `usePublicPage`, `v-html` |
| `pages/transparencia/documentos.vue` | (a) API, mas de outra entidade — `GET /api/v1/public/transparency-documents`, não `pages` |
| `pages/obrigado/[tipo].vue` | (b) fixo — objeto `MESSAGES` hardcoded no `<script setup>` |
| `pages/[...slug].vue` | (a) API — rota genérica para qualquer página do CMS por slug (`usePublicPage`, `v-html`) |

Não há rota (c) verdadeiramente híbrida no sentido de "parte fixa + parte de API na mesma
página com o mesmo template" — as rotas de tipo (a) são todas o mesmo padrão (arquivo `.vue`
próprio que existe só para dar uma URL de primeiro nível a um slug do CMS, sobrepondo a rota
genérica `[...slug].vue`, ver comentário em `doar.vue`), e as de tipo (b) não tocam a API de
`pages`.

**Endpoints administrativos de `pages`** (`backend/routes/api_v1.php`, grupo `auth:sanctum`):
`Route::apiResource('pages', PageController::class)` completo (index/store/show/update/
destroy) — CRUD existe e está sob auth. `PagePolicy`
(`backend/app/Policies/PagePolicy.php`): `viewAny`/`view` para `direcao`, `atendimento` ou
`comunicacao`; `create`/`update`/`delete` só para `direcao` ou `comunicacao`.

**Nenhuma tela do painel usa esses endpoints hoje** — confirmado: `frontend-admin/src/` não
tem nenhum arquivo `.ts`/`.vue` que referencie `/pages` ou `pageService`/`PageResource` (só
`services/dashboard.ts` e `services/submissions.ts` existem em `services/`, nenhum dos dois
toca `pages`).

## 4. Transparência

**Model**: `backend/app/Models/TransparencyDocument.php`. **Migration**:
`backend/database/migrations/2026_08_25_090000_create_transparency_documents_table.php`.
**Endpoints**:

- Público: `GET /api/v1/public/transparency-documents` (listagem paginada, filtro por
  ano/tipo), `GET /api/v1/public/transparency-documents/{uuid}/download` (soma
  `download_count` antes de servir o arquivo — `RegisterTransparencyDocumentDownload`).
- Administrativo: `Route::apiResource('transparency-documents', ...)` completo, sob
  `auth:sanctum`.

**Upload**: `SaveTransparencyDocument`
(`backend/app/Actions/Transparency/SaveTransparencyDocument.php`) grava o arquivo com
`$data->file->store('transparency-documents', 'local')` — disco `local`
(`backend/config/filesystems.php`, `root => storage_path('app/private')`, fora do webroot).
Validação (`StoreTransparencyDocumentRequest`): só PDF (`mimes:pdf`), máximo 20 MB
(`max:20480`). Trocar o arquivo numa atualização não apaga o antigo do disco (comentário
explícito no Action, para não perder evidência de download já contabilizado).

**De onde a página pública tira os dados hoje**: `frontend-site/app/pages/transparencia/
documentos.vue` chama diretamente `GET /api/v1/public/transparency-documents` via
`useAsyncData`/`$fetch` — não há intermediário nem cache no lado do Nuxt para essa rota.

**Não existe tela do painel** para gerenciar documentos de transparência — confirmado pela
ausência de qualquer referência a `transparency-documents` em `frontend-admin/src/` (mesma
checagem do item 3). A API já está pronta e testada (roadmap confirma).

## 5. Renderização e cache

**`nitro.prerender.routes`** (`frontend-site/nuxt.config.ts`, `crawlLinks: false`) —
lista manual com `/`, `/robots.txt`, `/sitemap.xml`, e ~30 slugs de conteúdo (`/o-que-
fazemos`, `/doar`, toda a árvore de `/quem-somos`, `/educacao-infantil`, `/contraturno`,
`/bazar`, `/como-ajudar`, `/transparencia`, `/politica-de-privacidade`, e as cinco páginas
`/obrigado/:tipo`). **Deliberadamente fora da lista**: `/quem-somos/o-lar-hoje` (página em
`Draft` no seeder — prerenderizar quebraria o build, API pública devolve 404) e os cinco
formulários públicos (comentário no arquivo explica: uma rota prerenderizada vira arquivo
estático por caminho, ignorando query string — os formulários dependem de `route.query` para
reexibir erro de validação sem JavaScript, então precisam de SSR real a cada request).
`/transparencia/documentos` também está fora da lista pelo mesmo motivo (depende de query
string de filtro).

**SSR (fora do prerender)**: tudo que não está na lista acima e existe como rota — em
especial `/transparencia/documentos` e as seis páginas de formulário (`/contraturno/
inscricao`, `/bazar/agendar-coleta`, `/contraturno/apoiar`, `/como-ajudar/voluntariado`,
`/contato`) — precisa do servidor Nitro rodando (`node .output/server/index.mjs`), não
funciona em hospedagem 100% estática.

**`routeRules`** (`nuxt.config.ts`): só uma entrada, `'/fotos/**'` com
`cache-control: public, max-age=2592000` (30 dias, sem `immutable`). Nenhuma outra rota tem
`routeRules` configurada.

**Cache de conteúdo público no backend**: `App\Actions\Content\ResolvePublicPageBySlug`
(`backend/app/Actions/Content/ResolvePublicPageBySlug.php`) — `Cache::remember` por slug
(`PublicPageCache::key($slug)`), TTL de **10 minutos** (`now()->addMinutes(10)`). Cacheia só
um array de campos escalares, nunca o model `Page` (comentário explica a armadilha de
`cache.serializable_classes = false` no Laravel 13). **Invalidação ao salvar**: sim —
`SavePage` (`backend/app/Actions/Content/SavePage.php`) chama `Cache::forget` tanto para o
slug antigo (se mudou) quanto para o novo; `DeletePage`
(`backend/app/Actions/Content/DeletePage.php`) também chama `Cache::forget` no slug excluído.
`transparency-documents` (listagem pública) **não tem cache** — roadmap confirma que foi
decisão deliberada (volume baixo, ~12 documentos de exemplo hoje).

## 6. Upload de arquivos

Existe um único pipeline de upload real hoje: documentos de transparência (PDF, ver item 4).
**Não existe pipeline de upload de imagem** — nenhuma entidade `media`, nenhum Controller,
Action ou Job relacionado a imagem no backend. Busca por "exif" em todo `backend/app/` não
retornou nenhuma ocorrência — **remoção de EXIF não está implementada**, porque não há
upload de imagem para remover EXIF de nada ainda. Isso é consistente com o roadmap
(`docs/roadmap.md`, "Backend — entidades da Fase 1 restantes": `media` + pipeline, incluindo
"remoção de EXIF", segue pendente).

Disco `local` (`backend/config/filesystems.php`): `root => storage_path('app/private')`,
`serve => true`, fora do webroot — é o disco usado pelo único upload que existe
(transparência). Limite de tamanho e tipo aceito hoje são os de
`StoreTransparencyDocumentRequest`: `mimes:pdf`, `max:20480` (20 MB) — não há regra
equivalente para imagem porque não há campo de imagem em nenhum FormRequest do projeto.

## 7. Formulários públicos

Seis entidades foram implementadas ao longo do projeto; uma foi removida por completo depois
(matrícula do CEI). Estado atual, conforme `App\Enums\FormSubmissionType`
(`backend/app/Enums/FormSubmissionType.php`) e `config('forms.submission_models')`
(`backend/config/forms.php`) — ambos listam **cinco** casos/models, não seis:

| Formulário | Endpoint público | Tela no painel? |
|---|---|---|
| `program_application` (aviso de interesse — contraturno) | `POST /api/v1/public/program-applications` | Sim — `program-applications` em `SUBMISSION_RESOURCES` |
| `pickup_request` (agendamento de coleta — bazar) | `POST /api/v1/public/pickup-requests` | Sim — `pickup-requests` |
| `volunteer_application` (voluntariado) | `POST /api/v1/public/volunteer-applications` | Sim — `volunteer-applications` |
| `partnership_inquiry` (proposta de parceria) | `POST /api/v1/public/partnership-inquiries` | Sim — `partnership-inquiries` |
| `contact_message` (contato) | `POST /api/v1/public/contact-messages` | Sim — `contact-messages` |

**Interesse de matrícula do CEI (`enrollment_interests`)**: **não existe mais no código**.
Busca por `enrollment` e `EnrollmentInterest` em `backend/app/` e `frontend-admin/src` e
`frontend-site/app` não retornou nenhuma ocorrência — nem migration, nem model, nem rota, nem
tela. `docs/roadmap.md` confirma: a entidade foi removida por completo (migration, model,
Action, FormRequest, Resources, Policy, factory, testes, rotas e painel administrativo) numa
sessão anterior, quando a matrícula do CEI passou a apontar só para a Central de Vagas da
Prefeitura (`frontend-site/app/pages/educacao-infantil` — a página cita esse redirecionamento
de fluxo, não um formulário próprio). Portanto, dos seis formulários que já existiram no
projeto, **os cinco atuais têm tela no painel; o sexto (matrícula do CEI) não tem porque foi
descontinuado, não porque falta implementar.**

## 8. `docs/roadmap.md` — pendências registradas para o painel

Trecho relevante, seção "Painel administrativo (Vue)":

- Gestão de conteúdo (`pages`, `posts`, mídia) e de documentos de transparência **ainda não
  têm tela** — a API de `pages`/`transparency-documents` já existe e está testada; só falta a
  interface.
- Editor de texto rico com sanitização no backend (Tiptap, a justificar como nova dependência
  quando a tela existir).
- Preview de SERP nos campos de SEO.
- Tela de upload de documento de transparência (API já existe e testada — só falta a
  interface).
- `frontend-admin` não tem nenhuma ferramenta de teste (Vitest, Testing Library ou
  equivalente).

Outras pendências relevantes espalhadas pelo roadmap, fora dessa seção específica:

- Backend — entidades da Fase 1 ainda por fazer: `posts`/`post_categories` (notícias),
  `media` + pipeline (MIME real, remoção de EXIF, WebP, thumbnails em fila, fora do webroot —
  inclui `pages.og_image_id`, adiada até `media` existir), `testimonials`, `partners`,
  `institution_stats`, `settings`, `bazaar_showcase_items`.
- Conteúdo do CMS é renderizado via `v-html` (`[...slug].vue`) — impede posicionar imagem
  dentro do texto pelo painel; contornado hoje com páginas próprias por seção.
- Cadastro de assistidos (Fase 2) está **bloqueado** até `docs/lgpd/inventario-de-dados.md`
  ser preenchido com a instituição — não é escopo desta frente de trabalho.

Não há, em nenhum lugar do roadmap, menção explícita a "tela de gestão de usuários" ou
"redefinição de senha por e-mail" como pendência registrada — a ausência desses dois itens no
roadmap é, ela mesma, um dado a levar em conta no planejamento (não é um esquecimento de
registro; é território não mapeado ainda).

## Lacunas (resumo objetivo)

1. **Gestão de usuários**: nenhum endpoint (CRUD, atribuição de papel), nenhum `UserPolicy`,
   nenhuma tela no painel. Papéis só são atribuídos via seeder local/testing.
2. **Redefinição de senha por e-mail**: não implementada de ponta a ponta — sem rota, sem
   Action/Notification, sem link no `LoginView.vue`.
3. **Coluna de ativo/inativo em `users`**: não existe. Não há como desativar um usuário sem
   excluí-lo.
4. **2FA**: só schema (colunas). Sem fluxo de setup, desafio no login ou recuperação.
5. **Envio de e-mail**: mailer padrão é `log` — nada sai de fato sem configurar
   `MAIL_MAILER` em produção; hoje só é usado para notificação de formulário, não para conta
   de usuário.
6. **Tela de conteúdo (`pages`)**: API completa e testada, zero interface no painel.
7. **Tela de documentos de transparência**: API completa e testada, zero interface no painel.
8. **Card/seção de conteúdo na tela Início**: hoje o dashboard só mostra pendências dos cinco
   formulários; um usuário com acesso só a conteúdo vê mensagem fixa de "ainda não
   implementado", sem nenhum card útil.
9. **Upload de imagem / remoção de EXIF**: pipeline inteiro não existe (`media` é item de
   roadmap, Fase 1 restante) — regra 7 do CLAUDE.md ("EXIF é removido de todo upload de
   imagem") não tem nenhum upload de imagem para se aplicar ainda.
10. **`enrollment_interests` (matrícula do CEI)**: confirmado removido do escopo, não é uma
    lacuna de implementação — só registrado aqui porque o levantamento pediu conferência
    explícita.
