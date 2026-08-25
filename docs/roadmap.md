# Roadmap

> Atualizado ao fim de cada sessão de trabalho. Checkboxes refletem o que está
> implementado, não o que está planejado em `docs/estrutura-site.md`.

## Concluído

- [x] Backend: bootstrap Laravel, Postgres, Sanctum SPA mode, papéis (`spatie/laravel-permission`),
      auditoria (`spatie/laravel-activitylog`)
- [x] Infraestrutura de proteção de dados: normalização (`StringNormalizer`), blind index,
      cifra de campo customizada (`FieldEncrypted`)
- [x] Autenticação: login, logout, usuário atual, troca de senha autenticada
- [x] Scaffold do painel admin (Vue 3 + Vite + Pinia + Router) e do site público (Nuxt 4)
- [x] CI (Pint, Larastan, Pest, `composer audit`, `npm audit`, build dos dois frontends)
- [x] ADRs 0001–0009 em `docs/decisoes/`
- [x] Entidade `pages` ponta a ponta: migration, model, Action, FormRequest, Policy,
      Resources público/admin, endpoints de leitura pública e CRUD administrativo, testes Pest
- [x] Sistema de design do site público: tokens (cor, tipografia, espaçamento), fontes
      auto-hospedadas, componentes base (`AppHeader`, `AppFooter`, `LedgerLine`) — ver
      `docs/decisoes/0009-direcao-visual.md`
- [x] Site público prerenderiza a home (`/`) — `nitro.prerender.routes` incluía as subpáginas
      mas não a raiz; `.output/public` agora sai com `index.html`
- [x] Rota genérica de conteúdo (`app/pages/[...slug].vue`) substitui a rota específica de
      "Quem somos": serve qualquer página do CMS pelo slug, com 404 real, redirect 301 via
      histórico de slug e breadcrumb derivado do slug (com título real da página-mãe). Toda
      página de conteúdo puro do site passa por este único template.
- [x] Seed de conteúdo institucional completo: 28 páginas publicadas — as cinco de "Quem
      somos" (reescritas com texto de verdade) mais Educação Infantil, Contraturno, Bazar,
      Como Ajudar e a página-índice de Transparência (`ContentPagesSeeder`). Todo texto vem só
      de fatos confirmados em `docs/contexto.md`; onde falta o dado, o texto diz isso
      explicitamente. Toda página termina com `<!-- rascunho: validar com a instituição -->`.
- [x] Home com layout próprio: posicionamento direto sem hero de banco de imagem, três
      pilares com peso visual igual, linha de registro em modo `example`, chamadas para
      doação, transparência e matrícula
- [x] Entidade `transparency_documents` ponta a ponta: migration, model, Policy (só
      `direcao` administra), Actions (salvar com upload de PDF, excluir, listar publicados
      com filtro, registrar download), Resources público/admin, CRUD administrativo em
      `/api/v1/transparency-documents` e listagem+download públicos em
      `/api/v1/public/transparency-documents`. Testes Pest cobrindo Policy, upload, filtro por
      ano/tipo e contagem de download.
- [x] `/transparencia/documentos`: filtro por ano e tipo via `<form method="get">`, renderizado
      no servidor a cada request, funciona sem JavaScript. Seed com 12 documentos de exemplo
      (PDFs de uma página em branco, gerados em tempo de seed, nunca commitados).

## Em andamento

- [ ] Nenhum item em andamento no momento — próxima sessão começa do zero num item da lista
      abaixo

## Pendente

### Backend — entidades da Fase 1 restantes

- [ ] `posts`, `post_categories` (notícias)
- [ ] `media` + pipeline (MIME real, remoção de EXIF, conversão WebP, thumbnails em fila,
      armazenamento fora do webroot) — inclui a coluna `og_image_id` em `pages`, adiada nesta
      sessão porque `media` ainda não existe (ver decisão abaixo)
- [ ] `testimonials`, `partners`, `institution_stats`
- [ ] `settings`
- [ ] `bazaar_showcase_items`
- [ ] Formulários recebidos: `enrollment_interests`, `program_applications`,
      `pickup_requests`, `volunteer_applications`, `partnership_inquiries`, `contact_messages`
      — com rate limit, honeypot, job de descarte por `expires_at`
- [ ] Teste Pest explícito de que `comunicacao` não acessa nenhum formulário recebido — ainda
      não escrito porque nenhum formulário existe no código ainda; não há o que testar até a
      primeira entidade de formulário ser criada. `TransparencyDocumentPolicy` já restringe
      documentos de transparência a só `direcao` (testado), mas isso não é um "formulário
      recebido" no sentido do domínio.

### Site público (Nuxt)

- [ ] Formulários e rotas dependentes de formulário — nenhum implementado nesta sessão por
      escopo explícito: `/educacao-infantil/matricula`, `/contraturno/inscricao`,
      `/contraturno/apoiar`, `/bazar/agendar-coleta`, `/como-ajudar/voluntariado`, `/contato`
- [ ] `/como-ajudar/doar-itens` (redirect 301 → `/bazar/agendar-coleta`) — não implementado
      porque o destino ainda não existe (é rota de formulário); criar o redirect junto da
      página de agendamento de coleta
- [ ] `/bazar/novidades` (vitrine do bazar) — fora de escopo desta sessão, depende de
      `bazaar_showcase_items` e ainda tem `[VALIDAR]` pendente (preço, quem alimenta)
- [ ] Seção de notícias (`/noticias`, `/noticias/:slug`) — depende de `posts`
- [ ] `sitemap.xml`/`robots.txt` consumindo conteúdo real (hoje geram só a partir de
      `NUXT_PUBLIC_SITE_URL`, sem `pages`/`posts`/`transparency-documents`)
- [ ] JSON-LD `NGO`/`Organization`
- [ ] Eventos Umami nos CTAs
- [ ] `/educacao-infantil/estrutura` menciona uma galeria de fotos que ainda não existe —
      depende da entidade `media`
- [ ] `/educacao-infantil/depoimentos` descreve o que as famílias destacam publicamente, mas
      não tem depoimentos individuais reais — depende de `testimonials` e de autorização
      registrada por família (ver `docs/contexto.md`, "Regras de conteúdo")
- [ ] `/bazar/visite-a-loja` não tem horário de funcionamento nem mapa — `[LACUNA]`, pendente
      de confirmação com a administração do bazar
- [ ] `/como-ajudar/doar` não tem chave PIX nem QR code — bloqueado por `[LACUNA]` em
      `docs/contexto.md` (chave PIX institucional)
- [ ] `/educacao-infantil/dia-da-crianca` tem conteúdo muito magro (dois parágrafos genéricos)
      — não há nenhum fato confirmado sobre a edição do evento em `docs/contexto.md`; revisar
      assim que houver informação real, ou considerar remover a página até lá
- [ ] Quatro páginas de Contraturno (`para-quem-e`, `como-funciona`, `parceiros`,
      `o-que-vem-por-ai`) ficaram abaixo de ~300 palavras — esperado dado que o programa é
      novo (ver `docs/estrutura-site.md` §1.4, que já previa esse risco e autoriza unir à
      página-pilar se não crescerem). Mantidas separadas por ora porque a decisão é de
      conteúdo, não de arquitetura, e não bloqueia a implementação; reavaliar se o texto não
      crescer numa próxima rodada de conteúdo.
- [ ] `/quem-somos/governanca` não cita nomes da diretoria atual — "Júlio Palmiro" e "Sidnei
      Pereira do Nascimento" estão marcados `[CONFIRMAR]` em `docs/contexto.md`; adicionar
      só após confirmação
- [ ] `/quem-somos/missao-visao-valores` é explicitamente um rascunho de trabalho, não texto
      final — `docs/contexto.md` registra que reescrever a missão (a atual descreve o antigo
      acolhimento) é entregável em aberto a validar com a instituição
- [x] Direção visual (paleta, tipografia, componentes base) — ver
      `docs/decisoes/0009-direcao-visual.md`
- [ ] O menu principal (`AppHeader.vue`) e o rodapé (`AppFooter.vue`) ainda linkam algumas
      rotas sem página própria: `/como-ajudar/doar-itens` (redirect pendente, ver acima) e as
      rotas de formulário listadas no início desta seção. O aviso
      `[VUE_ROUTER_R0004] No match found` no console do `nuxt dev` para essas rotas é esperado
      (`crawlLinks: false` em `nuxt.config.ts` já impede que isso quebre `nuxt generate`, ver
      ADR 0009) e some conforme cada uma ganhar página.
- [ ] **Bloqueia publicação:** a linha de registro (`LedgerLine.vue`) na home exibe três
      números institucionais (250 crianças, 63 anos, 40% do orçamento) em modo `example` —
      vêm de `docs/contexto.md` sem marca `[CONFIRMAR]` explícita, mas tratados como não
      validados por decisão desta e da sessão anterior (o levantamento em si veio de fonte
      única/entrevista). Não publicar sem validação institucional.
- [ ] Cache Redis + ETag para os demais endpoints públicos — implementado só para `pages`.
      `public/transparency-documents` (listagem) não está cacheado nesta sessão: o volume
      atual (12 documentos de exemplo) não justificou o risco de repetir o cuidado com
      `cache.serializable_classes` (ver armadilha abaixo) sob prazo apertado; revisitar quando
      o acervo real (~70 documentos) entrar.
- [ ] `nitro.prerender.routes` continua sendo uma lista manual de slugs — não existe endpoint
      público de listagem de páginas (só `GET /pages/{slug}`) para descobrir rotas publicadas
      em tempo de build. Se o volume de páginas crescer muito, vale considerar um endpoint de
      listagem só para isso.
- [ ] `/transparencia/documentos` é renderizada 100% no servidor (Nitro) e **não** faz parte
      do build estático (`nuxt generate`). Hospedagem 100% estática (sem servidor Node) não
      serve essa rota — precisa do mesmo modo de deploy já exigido pelo redirect de slug
      antigo (`nuxt build` + `node .output/server/index.mjs`), não só `.output/public`
      hospedado como arquivos estáticos.

### Painel administrativo (Vue)

- [ ] Todas as telas de `docs/estrutura-site.md` §4.2/§4.3 — nesta fase só existe API
      administrativa (`pages`, `transparency-documents`), sem tela nenhuma no painel ainda
- [ ] Editor de texto rico com sanitização no backend (Tiptap, a justificar como nova
      dependência quando a tela existir)
- [ ] Preview de SERP nos campos de SEO
- [ ] Tela de upload de documento de transparência (a API já existe e está testada — só falta
      a interface)

### Validações pendentes com a instituição

- [ ] `[VALIDAR]` Campo `school` em `program_applications` — nome do rótulo e obrigatoriedade
      (o campo em si já está decidido: existe, `enc`, nullable — ver `docs/dominio.md`)
- [ ] `[VALIDAR]` Vitrine do bazar (`bazaar_showcase_items`) terá preço? Há quem alimente
      semanalmente? (schema já esboçado com `price` nullable — ver `docs/dominio.md`)
- [ ] `[VALIDAR]` Prazos de retenção exatos de cada formulário
- [ ] `[VALIDAR]` Convênio com a Secretaria Municipal de Educação impõe campo ou relatório?
- [ ] `[CONFIRMAR]` Nomes e cargos da diretoria atual (para `/quem-somos/governanca`)
- [ ] `[CONFIRMAR]` Faixa etária exata e critérios de seleção do Contraturno
- [ ] `[LACUNA]` Chave PIX institucional e dados bancários (para `/como-ajudar/doar`)
- [ ] `[LACUNA]` Horário de funcionamento e endereço de acesso detalhado do Bazar (a página
      `/bazar/visite-a-loja` já existe, só falta o dado)
- [ ] Cadastro da instituição no Nota Paraná e no fundo municipal da criança e do adolescente,
      e o passo a passo de cada um (para `/como-ajudar/nota-parana` e `/como-ajudar/empresas-ir`)
- [ ] Texto final de missão, visão e valores (o publicado é rascunho de trabalho explícito)
- [ ] Preencher `docs/lgpd/inventario-de-dados.md` — bloqueia toda a Fase 2

### Decisões técnicas em aberto, registradas mas não bloqueantes

- [ ] `pages.og_image_id`: entra numa migration futura, junto da entidade `media` — decisão
      de sessão anterior, para não criar coluna sem uso funcional possível antes de `media`
      existir
- [ ] PHPStan (Larastan) estoura o limite padrão de memória do PHP (128M) com o volume atual
      de código — rodar sempre com `./vendor/bin/phpstan analyse --memory-limit=512M`. Vale
      considerar fixar isso em `phpstan.neon` ou num script composer numa sessão futura, para
      não depender de lembrar a flag.

### Armadilha de infraestrutura descoberta em sessão anterior — vale para toda entidade futura

`config('cache.serializable_classes')` vem `false` por padrão no Laravel 13
(`config/cache.php`): `unserialize()` recusa reconstruir **qualquer objeto** vindo do cache
(Redis, database, file — todos os stores que passam por `unserialize()`), inclusive Eloquent
models e DTOs simples, e devolve silenciosamente `__PHP_Incomplete_Class` em vez de lançar
erro. Isso só aparece num cache HIT (segunda leitura em diante) — o primeiro request sempre
funciona porque recalcula em vez de ler do cache, o que torna o bug fácil de não notar em
teste manual rápido.

`App\Actions\Content\ResolvePublicPageBySlug` cacheia só array com campo escalar por causa
disso — nunca o `Page` model nem nenhum DTO. **Toda Action futura que usar `Cache::remember`
com algo além de array/escalar precisa do mesmo cuidado** (posts, settings, etc., quando
existirem — `transparency_documents` evitou o problema simplesmente não cacheando ainda, ver
pendência acima). Não afrouxar `serializable_classes` globalmente para contornar isso — é uma
trava de segurança deliberada contra injeção de objeto via cache envenenado, e vale para o
projeto inteiro, não só para este caso de uso.

### Limitações conhecidas de `pages` — reavaliar em breve

- **`DeletePage` não impede apagar uma página-mãe que tenha filhas.** Excluir
  `quem-somos` com `quem-somos/nossa-historia` ainda existindo deixa a filha com slug
  órfão (o primeiro segmento não resolve mais a lugar nenhum). Consequência do modelo de
  slug plano (ver decisão em `docs/roadmap.md`, "Formato do slug", e
  `App\Actions\Content\SavePage::assertValidSlugDepth`, que só valida na escrita da
  filha, não na exclusão da mãe).
- **Renomear a mãe não propaga para as filhas.** `SavePage` grava histórico e invalida
  cache só do slug que está sendo salvo; renomear `quem-somos` para `sobre-nos` não move
  `quem-somos/nossa-historia` para `sobre-nos/nossa-historia` — a filha continua
  respondendo no prefixo antigo, que passa a não ter mãe.

Eram "aceitáveis com cinco páginas"; agora são **cinco famílias de páginas-mãe com filhas**
(`quem-somos`, `educacao-infantil`, `contraturno`, `bazar`, `como-ajudar`, mais
`transparencia`), 28 páginas ao todo. O risco de uma exclusão ou renomeação acidental deixar
filha órfã é maior do que quando isso foi escrito. Ainda não há tela de admin para páginas
(painel administrativo não existe), o que limita a exposição prática por ora — mas vale
resolver (validar em cascata ou migrar para `parent_id`) antes de a tela existir, não depois.

## Fase 2 — bloqueada

- [ ] Cadastro de assistidos (`assisted_minors`, `guardians`, `guardianships`, `consents`,
      `health_records`, `classes`, `enrollments`, `attendance_records`) — **bloqueado** até
      `docs/lgpd/inventario-de-dados.md` ser preenchido com a instituição
