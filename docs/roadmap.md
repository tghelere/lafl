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
- [x] Seis formulários públicos ponta a ponta (ver docs/dominio.md, "Formulários recebidos"):
      `enrollment_interests`, `program_applications`, `pickup_requests`,
      `volunteer_applications`, `partnership_inquiries`, `contact_messages`. Base comum
      (`IsFormSubmission`, `FormSubmissionColumns`, honeypot, rate limit por IP) mais as seis
      entidades (migration, model, Policy, Action, FormRequest, endpoint público, testes).
      `PickupRequest` expurga o endereço assim que a coleta é concluída, à parte do expurgo
      geral por `expires_at` que as outras cinco também têm. `PartnershipInquiry` tem blind
      index de CNPJ. Policy compartilhada (`FormSubmissionPolicy`) segue a matriz de
      `docs/estrutura-site.md` §4.4 — `bazar` só acessa `pickup_requests`, `comunicacao`
      nenhum formulário, testado explicitamente.
- [x] Notificação por e-mail via fila (`App\Mail\FormSubmissionReceived`), sem dado pessoal no
      corpo, destinatário por tipo via `config('forms.notification_recipients')`. Testado de
      ponta a ponta contra Mailpit real (docker-compose), não só com `Mail::fake()`.
- [x] Backend confia no site público como proxy de IP (`TRUSTED_PROXIES`, `bootstrap/app.php`)
      — necessário para o rate limit por IP funcionar quando o formulário passa pelo proxy do
      Nuxt em vez de chamar a API direto.
- [x] Site público: as seis páginas de formulário, `/obrigado/[tipo]` (noindex),
      `/politica-de-privacidade` e o redirect `/como-ajudar/doar-itens → /bazar/agendar-coleta`
      (pendente desde a sessão anterior, agora com destino). `<form method="post">` de
      verdade, funciona sem JavaScript via proxy servidor-a-servidor
      (`server/api/forms/[tipo].post.ts`) que decide redirect de sucesso ou reexibição de erro
      — a API Laravel continua REST puro, sem view.
- [x] Correção de conteúdo institucional no `ContentPagesSeeder` (revisão de voz e risco
      jurídico, sem mudança de funcionalidade): removida a estratégia de SEO/indexação da voz
      da própria instituição (`quem-somos/o-lar-hoje`, `transparencia`); atribuída em vez de
      afirmada a decisão de primeira instância sobre o caso de 2022
      (`quem-somos/o-lar-hoje`, `quem-somos/governanca`), com bloqueio de publicação explícito
      na primeira; removidas três menções não confirmadas de `33.000 m²` (duas no corpo, uma
      em `meta_description`); removidas quatro paráfrases de avaliações do Google atribuídas a
      "famílias" sem autorização; dois ajustes de tom (autocrítica na Visão, eufemismo nos
      Valores). Ver `docs/contexto.md` para as duas lacunas novas (status processual do caso
      de 2022, controles adotados desde então) e a seção "BLOQUEIO DE PUBLICAÇÃO" abaixo.
- [x] Leitura administrativa dos seis formulários recebidos, ponta a ponta: endpoints
      (listagem paginada com filtro por status/período, detalhe, mudança de status com
      anotação interna, um endpoint agregado de painel com contagem de pendentes por papel) e
      as telas correspondentes no `frontend-admin` (Início, Atendimento, Bazar — ver
      `docs/estrutura-site.md` §4.2/§4.3). Listagem devolve dado **mascarado** (primeiro nome,
      últimos dígitos do telefone, domínio do e-mail); valor completo só no detalhe, e todo
      acesso ao detalhe é auditado (`activity('forms')->event('viewed')`) — registrado quem,
      quando e de qual IP, nunca o valor descriptografado. `comunicacao` recebe 403/404 nos
      seis recursos e painel vazio com mensagem explicativa; `bazar` só acessa
      `pickup_requests`. `frontend-admin` ganhou seu primeiro sistema de design (reaproveita
      tokens do site público, densidade maior, Bitter só em título de tela) e sua primeira
      arquitetura de tela genérica orientada a configuração (`SUBMISSION_RESOURCES`), em vez de
      seis pares de tela quase idênticos. Nenhuma verificação em navegador real ocorreu nesta
      sessão (sem acesso a browser) — ver `docs/relatorio-sessao-6.md` para a lista completa do
      que precisa de conferência visual antes de considerar essas telas prontas.

## Em andamento

- [ ] Nenhum item em andamento no momento — próxima sessão começa do zero num item da lista
      abaixo

## Pendente

### BLOQUEIO DE PUBLICAÇÃO — `/quem-somos/o-lar-hoje`

**Esta é a única página do site com essa restrição.** `/quem-somos/o-lar-hoje` não pode ir ao
ar sem:

1. **Revisão de advogado** do texto sobre o caso de 2022 — decisão de primeira instância não é
   decisão definitiva, e o texto precisa refletir isso com precisão jurídica, não só
   institucional.
2. **Confirmação do status processual atual** — se houve recurso, em que instância o processo
   está hoje, se houve trânsito em julgado. Ver `[LACUNA]` em `docs/contexto.md`, seção
   "Histórico recente".

O bloco de conteúdo da página, no `ContentPagesSeeder`, já carrega o comentário
`<!-- BLOQUEADO PARA PUBLICAÇÃO: exige revisão jurídica antes de ir ao ar -->` logo no início
do HTML. **Não remover esse comentário** até as duas condições acima estarem satisfeitas.

Ver também `docs/contexto.md`, "Controles adotados após 2022": a lacuna de maior valor
pendente do projeto. Sem saber quais controles internos, protocolos de proteção e supervisão
foram adotados desde 2022, a página não pode dizer nada concreto além de "uma nova diretoria
assumiu" — o que tranquilizaria de fato um visitante desconfiado é exatamente o que falta
levantar.

### Backend — entidades da Fase 1 restantes

- [ ] `posts`, `post_categories` (notícias)
- [ ] `media` + pipeline (MIME real, remoção de EXIF, conversão WebP, thumbnails em fila,
      armazenamento fora do webroot) — inclui a coluna `og_image_id` em `pages`, adiada nesta
      sessão porque `media` ainda não existe (ver decisão abaixo)
- [ ] `testimonials`, `partners`, `institution_stats`
- [ ] `settings`
- [ ] `bazaar_showcase_items`
- [ ] Fotos opcionais em `pickup_requests` (`media_ids` no domínio) — depende da entidade
      `media`, fora de escopo
- [ ] Endpoint para **definir** `pickup_requests.scheduled_for` (agendar data de coleta) — a
      tela do Bazar (sessão 6) já ordena e exibe a agenda por data, mas só lê; não existe
      Controller/rota para o time do bazar marcar uma data de coleta, só a mudança de
      status/anotação interna genérica das seis entidades. Sem isso, "agenda por data" no
      painel é hoje só ordenação de pedidos sem data nenhuma preenchida.

### Site público (Nuxt)

- [ ] `/bazar/novidades` (vitrine do bazar) — fora de escopo desta sessão, depende de
      `bazaar_showcase_items` e ainda tem `[VALIDAR]` pendente (preço, quem alimenta)
- [ ] Seção de notícias (`/noticias`, `/noticias/:slug`) — depende de `posts`
- [ ] `sitemap.xml`/`robots.txt` consumindo conteúdo real (hoje geram só a partir de
      `NUXT_PUBLIC_SITE_URL`, sem `pages`/`posts`/`transparency-documents`)
- [ ] JSON-LD `NGO`/`Organization`
- [ ] Eventos Umami nos CTAs
- [ ] `/educacao-infantil/estrutura` menciona uma galeria de fotos que ainda não existe —
      depende da entidade `media`
- [ ] `/educacao-infantil/depoimentos` só cita a avaliação agregada pública (4,5★ no Google) —
      não tem depoimentos individuais reais, que dependem de `testimonials` e de autorização
      registrada por família (ver `docs/contexto.md`, "Regras de conteúdo"). Corrigido nesta
      sessão: a página chegou a parafrasear avaliações do Google atribuindo opinião a
      "famílias" sem autorização — removido, ver commit "corrige voz institucional e risco
      jurídico".
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
- [ ] `/quem-somos/missao-visao-valores` é explicitamente um rascunho de trabalho, não texto
      final — `docs/contexto.md` registra que reescrever a missão (a atual descreve o antigo
      acolhimento) é entregável em aberto a validar com a instituição
- [x] Direção visual (paleta, tipografia, componentes base) — ver
      `docs/decisoes/0009-direcao-visual.md`
- [x] O menu principal (`AppHeader.vue`) e o rodapé (`AppFooter.vue`) linkavam rotas sem
      página própria (`/como-ajudar/doar-itens`, as seis rotas de formulário) — todas
      resolvidas nesta sessão. Nenhum link conhecido do header/footer aponta para rota
      inexistente no momento.
- [ ] As páginas de conteúdo (`/educacao-infantil`, `/contraturno`, `/bazar/o-que-aceitamos`,
      `/como-ajudar`) ainda não têm CTA direto para os formulários correspondentes
      (`matricula`, `inscricao`, `agendar-coleta`, `voluntariado`) — só o rodapé linka. Editar
      o `ContentPagesSeeder` para adicionar essas chamadas é uma melhoria de conteúdo pequena,
      não fechada nesta sessão por não estar no escopo pedido (só as seis páginas de
      formulário e o redirect).
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

- [ ] Gestão de conteúdo (`pages`, `posts`, mídia) e de documentos de transparência ainda não
      têm tela — a API de `pages`/`transparency-documents` já existe e está testada desde
      sessões anteriores, só falta a interface. As telas de leitura dos seis formulários
      recebidos (Início/Atendimento/Bazar) foram construídas na sessão 6; conteúdo,
      transparência e usuários seguem fora de escopo.
- [ ] Editor de texto rico com sanitização no backend (Tiptap, a justificar como nova
      dependência quando a tela existir)
- [ ] Preview de SERP nos campos de SEO
- [ ] Tela de upload de documento de transparência (a API já existe e está testada — só falta
      a interface)
- [ ] `frontend-admin` não tem nenhuma ferramenta de teste (Vitest, Testing Library ou
      equivalente) — a sessão 6 construiu a primeira fatia de UI real do painel sem nenhum
      teste automatizado do lado do front, só Pest no backend e verificação manual via
      `php artisan tinker` (sem navegador disponível na sessão). Vale considerar antes da
      próxima leva de telas, quando a superfície ficar grande demais para revisão visual pura.

### Formulários públicos — decisões e pendências desta sessão

- [ ] `[LACUNA]` Endereços reais de notificação por tipo de formulário — hoje
      `config('forms.notification_recipients')` usa placeholders em domínio `.invalid`
      (RFC 2606). Definir via `FORM_RECIPIENT_*` no `.env` de cada ambiente quando a
      instituição informar quem recebe cada tipo (secretaria do CEI, coordenação do
      contraturno, bazar, etc.)
- [ ] `TRUSTED_PROXIES` (`bootstrap/app.php`) está com o default de loopback, correto só para
      dev onde site público e API rodam no mesmo host. Em produção, precisa do IP/CIDR real do
      serviço do site público (Nuxt) — sem isso, o rate limit por IP dos seis formulários passa
      a ver sempre o IP do próprio Nuxt, não o do visitante
      (ver `frontend-site/server/api/forms/[tipo].post.ts`)
- [ ] `/politica-de-privacidade` é rascunho de trabalho — mesmo tratamento do resto do
      conteúdo institucional (ver `docs/contexto.md`): precisa de validação jurídica antes de
      produção (já registrado como pendência geral em `docs/protecao-de-dados.md`), e de um
      Encarregado/DPO nomeado antes de publicar um canal de contato específico para isso
- [ ] `php artisan queue:work` (ou `schedule:work` para os jobs de expurgo) precisa estar
      rodando em produção — nada disparado por este código roda sozinho sem um worker; ver
      `docker-compose.yml`, que hoje não tem um serviço dedicado a isso
- [ ] As páginas de conteúdo do CMS ainda não linkam diretamente para os formulários
      correspondentes (ver nota na seção "Site público" acima)
- [ ] Prazos de retenção usados (12/12/6/24/36/6 meses, ver `docs/estrutura-site.md` §2.2) são
      os sugeridos no documento, não confirmados pela instituição — ver `[VALIDAR]` abaixo

### Validações pendentes com a instituição

- [ ] `[VALIDAR]` Campo `school` em `program_applications` — nome do rótulo e obrigatoriedade
      (o campo em si já está decidido: existe, `enc`, nullable — ver `docs/dominio.md`)
- [ ] `[VALIDAR]` Vitrine do bazar (`bazaar_showcase_items`) terá preço? Há quem alimente
      semanalmente? (schema já esboçado com `price` nullable — ver `docs/dominio.md`)
- [ ] `[VALIDAR]` Prazos de retenção exatos de cada formulário
- [ ] `[VALIDAR]` Convênio com a Secretaria Municipal de Educação impõe campo ou relatório?
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
- [ ] **Armadilha de `nitro.prerender.routes` descoberta nesta sessão**: uma rota
      prerenderizada vira arquivo estático servido por caminho — o Nitro nunca reexecuta o
      SSR para ela em produção, então qualquer página cujo conteúdo dependa de query string
      (`route.query`) nunca pode entrar nessa lista, senão serve sempre o mesmo snapshot
      independente da URL real pedida. Foi um bug real: os seis formulários entraram na lista
      por engano e o reaproveitamento de erro (`?erro=1&campos=...`) parou de funcionar antes
      de eu perceber e reverter. `/transparencia/documentos` já seguia essa regra
      corretamente; os formulários não seguiam. Checar isso antes de adicionar qualquer rota
      nova a `nitro.prerender.routes` daqui pra frente.
- [ ] Larastan/PHPStan (`parseModelCastsMethod: true`) infere `$this->coluna` automaticamente
      só *dentro do próprio arquivo do model* (via `casts()` + schema do banco). Não propaga
      isso para um parâmetro genérico em outra classe, nem via `Model $model`, nem via
      interface com `@property`, nem via `Model&Interface` — só funciona quando o `@property`
      está no próprio model ou numa trait que o model usa. Descrição completa e o porquê da
      trait `StampsSubmissionMetadata` ter sido descartada (três linhas repetidas em seis
      Actions, não valia a complexidade) está no commit "enrollment_interests e
      program_applications" desta sessão.
- [ ] **Duas armadilhas novas de Larastan descobertas na sessão 6**, mesma limitação de fundo
      da anterior, casos diferentes:
      1. A inferência automática de tipo de coluna via `casts()` **não propaga através de
         `@mixin ModelClass` numa Resource externa quando o cast é um enum PHP** — a Resource
         via `$this->campo->value` falha com "Cannot access property $value on string", mesmo
         a coluna estando corretamente tipada como enum no model. Casts customizados (ex.:
         `FieldEncrypted`) propagam normalmente pelo `@mixin`; só o caso de enum falhou.
         Contornado com `@property EnumType $campo` explícito direto no model (não na
         Resource) — ver `EnrollmentInterest`, `PartnershipInquiry`, `PickupRequest`.
      2. **Declarar qualquer `@property` explícito numa classe/trait suspende a inferência
         automática de todas as outras propriedades daquela classe** que não estejam
         explicitamente listadas. Ao adicionar `@property` dos enums acima, `created_at`/
         `updated_at` — nunca declarados explicitamente antes, sempre inferidos — passaram a
         falhar como "Access to an undefined property" em todo lugar que os usava via
         `@mixin`. Corrigido declarando-os também, e como `Carbon|null` (não `Carbon` puro)
         para não gerar o aviso oposto ("nullsafe call on non-nullable type") nos ~40 pontos
         que já usavam `?->` nesses campos. Moral: uma vez que se opta por `@property`
         explícito numa classe, é preciso listar **todas** as propriedades usadas por quem a
         consome via `@mixin`, não só a que motivou a anotação.

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
