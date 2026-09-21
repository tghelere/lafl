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
- [x] ADRs 0001–0012 em `docs/decisoes/`
- [x] Entidade `pages` ponta a ponta: migration, model, Action, FormRequest, Policy,
      Resources público/admin, endpoints de leitura pública e CRUD administrativo, testes Pest
- [x] Sistema de design do site público: tokens (cor, tipografia, espaçamento), fontes
      auto-hospedadas, componentes base (`AppHeader`, `AppFooter`, `LedgerLine`) — ver
      `docs/decisoes/0009-direcao-visual.md`
- [x] ~~Site público prerenderiza a home (`/`)~~ — **revertido na sessão 12**: a home passou a
      ler idade e ano de `/api/v1/public/institution-facts` e saiu de `nitro.prerender.routes`.
      Prerenderizada, congelaria a idade no dia do build (ver
      `docs/decisoes/0012-numeros-institucionais-calculados.md`). O site já não era hospedagem
      estática de qualquer forma — o servidor Nitro é exigido pelos cinco formulários e por
      `/transparencia/documentos`.
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
      Valores). Ver `docs/contexto.md`, seção "Histórico recente" — revisada em 17/09/2026: o
      cliente decidiu que o site não menciona o caso de 2022 em nenhuma página, e
      `quem-somos/o-lar-hoje` foi removida por completo do seeder (ver sessão de 17/09/2026
      abaixo).
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
- [x] **Ajuste de escopo de três páginas (sessão 7)**, com fatos novos confirmados pelo
      cliente em 13/09/2026 (`docs/contexto.md`): Contraturno reescrito por completo como
      programa em preparação (público de 6 a 15 anos, renda até 3 salários mínimos, meta de
      100 atendidos, parceria com o SENAI, ginásio, auditório, oficinas previstas, início
      previsto para 2027) — nenhuma página afirma operação, turma ou aluno matriculado; o
      formulário de inscrição virou aviso de interesse (só nome e telefone do responsável,
      `email`/`teen_age`/`school`/`message` removidos do schema). Matrícula do CEI passou a
      apontar exclusivamente para a Central de Vagas da Prefeitura, e o formulário
      `enrollment_interests` foi removido por completo (migration, model, Action,
      FormRequest, Resources, Policy, factory, testes, rotas, painel administrativo — reduz
      de seis para cinco formulários recebidos, ver ADR 0007 atualizada). Bazar: WhatsApp
      (43) 99950-0183 passou a ser a chamada principal de `/bazar/agendar-coleta`, formulário
      existente virou caminho secundário. Confirmado que nenhuma página publica ano de
      fundação, conforme pendência deixada pela sessão anterior. Backend com Pint/PHPStan/
      Pest verdes; `frontend-site` com `build`/`generate` verdes; `frontend-admin` com
      `lint`/`build` (`vue-tsc`) verdes. Conferência visual feita via requisição HTTP ao
      servidor de desenvolvimento (conteúdo renderizado confirmado), não em navegador real —
      Playwright não tinha Chromium disponível neste ambiente.
- [x] **Ícones, WhatsApp e mapa (sessão 8).** `@lucide/vue` instalado como dependência do site
      público (`lucide-vue-next` está deprecado a favor deste pacote desde a sessão — mesma
      API, trocado na hora da instalação). Ícones aplicados com parcimônia — telefone/endereço
      no rodapé e nos cartões de `/contato`, download em "Baixar PDF" de
      `/transparencia/documentos` — todos decorativos (`aria-hidden`), nenhum substitui rótulo
      de navegação; a seta do menu (CSS próprio) e o ícone da gaveta mobile ficaram como
      estavam, fora do escopo desta sessão. lucide não tem o glifo do WhatsApp — criado
      `AppWhatsappIcon.vue` com o SVG oficial da marca. Card do Bazar em `/contato` ganhou
      botão de WhatsApp (mensagem de contato geral); card da Sede/CEI ficou só com telefone —
      não há WhatsApp confirmado para a sede, número do bazar não foi reaproveitado.
      **Correção da mesma sessão:** a primeira versão do mapa (ilustração decorativa + iframe
      do Google sob clique) foi substituída antes do fim da sessão — a ilustração tinha forma
      de mapa sem informação real, e o endpoint do embed do Google não era documentado nem
      estável. Versão final: mapa estático único (`public/fotos/contato/mapa-enderecos-
      {960,640,400}.{webp,jpg}`, servido por `AppFoto`) — mosaico de blocos do OpenStreetMap
      com dois alfinetes numerados desenhados por script a partir de coordenadas reais (pino 1
      = POI nomeado no OSM para a Sede/CEI; pino 2 = centroide do trecho de rua do Bazar, sem
      numeração de casa mapeada — precisão de rua/quadra, registrada em `app/data/fotos.ts`),
      legenda e crédito "© OpenStreetMap contributors" na página. `AppMapaLocal.vue` ficou só
      com o link "Abrir no aplicativo de mapas" por cartão (geo link universal por texto de
      endereço, sem chave de API, sem iframe, sem requisição alguma até o clique do
      visitante). Conferido na aba de rede: zero requisição a domínio de terceiro em qualquer
      momento — só o `.webp` estático local. Rotas antes quebradas por aninhamento reconferidas em
      navegador real: `/bazar/agendar-coleta`, `/transparencia/documentos`,
      `/quem-somos/nossa-historia` — todas com título e conteúdo próprios, sem sinal de
      fallback do pai. Dois parágrafos de conteúdo do CMS (`bazar/visite-a-loja`,
      `bazar/o-que-aceitamos`, no `ContentPagesSeeder`) ainda citam o WhatsApp do bazar como
      texto solto, não como botão — fora do escopo desta sessão (frontend), não convertido.

- [x] **Gestão de usuários — backend completo (sessão de definição de senha por link).**
      Rodou depois da sessão de redesenho de papéis (papéis por área, `docs/dominio.md`).
      `users.deactivated_at` (nullable, null = ativo). Login recusa conta desativada só
      depois de confirmar a senha certa (senha errada nunca diferencia ativo de desativado);
      sessão aberta perde acesso na requisição seguinte à desativação
      (`App\Http\Middleware\EnsureUserIsActive`, aplicado com `auth:sanctum` em toda rota
      autenticada). CRUD sob `/api/v1/users` (busca por nome/e-mail, filtro ativo/inativo,
      paginação, criar, editar nome/e-mail/papéis, desativar, reativar — sem exclusão, conta
      só desativa), autorizado só a `super_admin` via `App\Policies\UserPolicy` (nem
      `direcao`). Usuário nasce com hash de valor aleatório no lugar de senha, inútil até
      definir a própria. Proteções testadas: ninguém desativa a si mesmo nem remove o
      próprio papel `super_admin`; não é possível desativar nem remover `super_admin` do
      último `super_admin` ativo (`App\Actions\Users\AssertLastActiveSuperAdminSurvives`,
      `lockForUpdate()` dentro de `DB::transaction()` contra duas requisições concorrentes).
      Link de definição de senha de uso único
      (`POST /api/v1/users/{uuid}/password-link`, `super_admin`): reaproveita o password
      broker nativo do Laravel (`Illuminate\Auth\Passwords\DatabaseTokenRepository`, token
      com hash, nunca texto puro) com broker próprio `user_setup` em `config/auth.php` (24h
      de validade, separado do broker `users` padrão reservado para o futuro "esqueci minha
      senha" por e-mail); gerar de novo invalida o anterior de graça, por já ser como o
      repositório do broker funciona. `POST /api/v1/auth/set-password` (público, throttle
      próprio) usa `broker->reset()` inteiro — token inválido, expirado, e-mail que não
      confere ou usuário desativado dão todos a mesma mensagem genérica. Sessões antigas
      invalidadas na troca de senha sem código próprio:
      `Laravel\Sanctum\Http\Middleware\AuthenticateSession` já faz isso em modo SPA, só
      precisou ser ligado. Auditoria (`activity('users')`): criação, alteração de papéis,
      desativação, reativação e geração de link — nunca token, URL ou senha (varrido por
      teste dedicado). `GET /api/v1/roles` (mesma ability de `users`) lista os papéis com
      nome e descrição definidos só em `App\Enums\Role`. `GET /api/v1/auth/user` ganhou
      `data.access`, mapa de `viewAny` por recurso calculado via `$user->can()` — **o
      frontend ainda não consome isto**. `docs/estrutura-site.md` §4.4 realinhado com a
      matriz de `docs/dominio.md`. Só backend nesta sessão — tela vem depois (ver pendência
      abaixo). 269 testes Pest, Pint e Larastan verdes.

- [x] Bateria de ponta a ponta do painel administrativo (`e2e/`, Playwright + Firefox):
      24 testes contra a pilha real em modo de produção (API Laravel, build do painel servido
      por `vite preview`, site Nuxt em SSR), cobrindo login e acesso por papel, gestão de
      usuários (link de definição de senha, desativação com sessão aberta, troca da própria
      senha), transparência (upload, publicação, filtro) e páginas (ida e volta do editor,
      publicação no site, sanitização, busca). Terceiro banco dedicado
      (`lar_analia_franco_e2e`) com ambiente próprio em `backend/.env.e2e` e guarda dupla em
      `php artisan e2e:prepare`, que recusa rodar fora dele. Job `e2e` no CI com Postgres e
      Redis, publicando trace/captura/vídeo em caso de falha e marcando teste instável em
      separado. Provado que a bateria pega defeito: filtragem do menu, sanitização do
      `SavePage` e middleware `EnsureUserIsActive` removidos um a um deixam testes vermelhos
      — ver `docs/relatorio-sessao-10.md`.

- [x] **Conteúdo e escopo de lançamento (sessão 11, `docs/tarefas/01-...md`).** Decisões do
      cliente de 17/09/2026 aplicadas ao `ContentPagesSeeder` e à navegação:
      `quem-somos/o-lar-hoje` removida por completo (não mais Draft), com remoção explícita no
      seeder para não sobreviver num banco de desenvolvimento já seedado; toda menção direta ou
      indireta ao processo judicial de 2022 removida do conteúdo institucional (ver
      `docs/contexto.md`, "Histórico recente" revisada, e a antiga seção "BLOQUEIO DE
      PUBLICAÇÃO" removida deste arquivo). `nossa-historia` ganhou os marcos de fundação/obra/
      inauguração da sede (1953/1957/1963), agora confirmados. Escopo de lançamento fechado:
      `missao-visao-valores`, `educacao-infantil/dia-da-crianca` e
      `educacao-infantil/depoimentos` passam a Draft e saem da navegação;
      `/como-ajudar/voluntariado` e `/contraturno/apoiar` continuam publicadas, mas `noindex,
      nofollow`; menu "Seja parceiro" virou "Parceiros"; `como-ajudar` perdeu voluntariado e
      apoio empresarial do texto; `siteNav.ts` aponta `Doar` direto para `/doar`. Faixa etária
      do contraturno corrigida para "crianças e adolescentes de 6 a 15 anos" (home e o hint de
      `navigation.ts`, repetido em `/o-que-fazemos`); duas afirmações de completude do acervo
      de transparência removidas da home (a contagem real fica para a próxima sessão, números
      calculados); parágrafo da fachada em `nossa-historia.vue` reescrito em tom neutro.
      Verificação completa: Pint, Larastan, Pest (336 testes), build/generate do site, lint/
      build do painel e as 25 da bateria de ponta a ponta (Firefox, via `npm run test:e2e`),
      todos verdes sem precisar ajustar nenhum teste. `migrate:fresh --seed` + `cache:clear`
      rodados no banco de desenvolvimento; conferência visual das páginas alteradas feita
      contra o dev server real via MCP do Playwright, que neste ambiente roda em Chromium, não
      Firefox — a cobertura de Firefox de fato veio só da bateria de ponta a ponta (que abre
      `contraturno`, `bazar/visite-a-loja` e `quem-somos/nossa-historia` no editor real).

- [x] **Números calculados em vez de texto fixo (sessão 12, `docs/tarefas/02-...md`).** Toda
      contagem e toda idade que o site publica passa a ser calculada; ano de acontecimento
      ("o bazar existe desde 1968") e valor de documento (repasse de R$ 2.819.892,84, 15 turmas
      do plano 2026) continuam literais, porque não são cálculo. `config/institution.php` é a
      fonte das datas, e o formato do valor declara a precisão: `'1953-07-12'` respeita o
      aniversário, `'1968'` cai na diferença de ano. `App\Services\InstitutionalFacts` calcula
      no fuso `America/Sao_Paulo` e devolve tudo já formatado em português, com o plural certo.
      Dois caminhos até o texto: marcadores `{{...}}` (`App\Enums\ContentMarker`) dentro do
      conteúdo do CMS, resolvidos só na leitura pública e só depois do cache de dez minutos de
      `ResolvePublicPageBySlug`; e `GET /api/v1/public/institution-facts` para a home, que saiu
      do prerender por causa disso. Marcador desconhecido é recusado ao salvar (422 listando os
      válidos); o endpoint administrativo devolve o marcador cru, senão o primeiro salvamento
      gravaria o número do dia e o cálculo morreria em silêncio. O editor de páginas do painel
      lista os cinco marcadores com o valor de hoje ao lado, tudo vindo da API. `transparencia`
      trocou "cerca de 70 documentos" por `{{documentos_transparencia}}`. Ver
      `docs/decisoes/0012-numeros-institucionais-calculados.md` e
      `docs/relatorio-sessao-12.md`.

- [x] **Alinhamento visual — ritmo de lista e altura de controle (sessão 13,
      `docs/tarefas/03-alinhamento-visual.md`).** `li + li` global de `base.css` (site) dava
      margem no topo a partir do segundo item de qualquer lista — em lista de bloco isso é
      ritmo, mas em toda lista em linha com `align-items: center` (menu do header, breadcrumb,
      navegação de seção) desalinhava o centro vertical do item. Escopado para
      `.prose`/`.page-content`; `.mobile-nav ul`/`.mobile-nav__children`, que dependiam da
      regra global, ganharam espaçamento próprio. Em ambos os frontends, `.btn` chegava à
      própria altura por padding + `line-height: 1` e input/select por padding + line-height
      herdado — nunca batiam, na barra de filtro das listagens do painel e em
      `/transparencia/documentos` no site. Tokens `--control-height-sm`/`--control-height-md`
      (um em cada `tokens.css`) aplicados a `.btn`, input e select nesses contextos. Cinco
      testes novos em `e2e/tests/layout/alinhamento.spec.ts` (tolerância de 1px), com prova de
      que pegam o defeito (regra global e altura de controle reintroduzidas, ficou vermelho,
      revertido antes do commit — mesmo método da sessão 10). Ver `docs/relatorio-sessao-13.md`.

- [x] **Logo institucional, crédito da Softhing e três correções do header (sessão 14,
      `docs/tarefas/04-marca-e-credito-softhing.md`).** `shared/brand/` criado como fonte
      única dos arquivos de marca (Lar Anália Franco e Softhing); site e painel importam a
      logo direto de lá (mesmo padrão de `shared/design-tokens/tokens.css`), favicon e a logo
      do e-mail são cópia documentada (consumidos fora do grafo de módulos JS). Logo no
      header/rodapé do site, no login e na sidebar do painel; favicons substituídos nos dois
      frontends; crédito "Desenvolvido por Softhing" no rodapé do site e na sidebar/login do
      painel, com evento Umami no clique (só no site — o painel não tem Umami) e
      `utm_campaign` distinto por app. `--color-yellow-500`/`--color-orange-500` corrigidos
      para o hex oficial do `.ai` de identidade (`docs/decisoes/0013-...md`, fecha a pendência
      de ADR aberta desde `6b55ccb`). Antes das etapas da tarefa: três correções pedidas no
      header do site — texto do painel suspenso não alinhava com o texto do gatilho (a caixa
      do painel soma 21px de recuo próprio contra 12px do gatilho), espaçamento entre itens do
      painel suspenso perdido na sessão 13, e `.mobile-nav a` vencendo `.btn` do CTA Doar na
      gaveta mobile por especificidade. Oito testes novos em `e2e/tests/layout/` (três de
      alinhamento, cinco de marca/crédito) e um teste Pest para a logo do e-mail. Ver
      `docs/relatorio-sessao-14.md`.
- [x] **Quatro correções de código apontadas em revisão (sessão 15,
      `docs/tarefas/05-correcoes-de-codigo.md`).** Link do e-mail de notificação apontava para
      `{ADMIN_BASE_URL}/{recurso}/{uuid}`, sem o prefixo `/admin` que a rota do painel exige —
      corrigido em `NotifyFormSubmissionReceived`. Requisição não autenticada à API sem
      `Accept: application/json` (curl, robô, navegador abrindo a URL direto) virava 500
      ("Route [login] not defined") em vez de 401, porque o middleware `auth` tentava
      redirecionar para uma rota de login que não existe numa API REST pura sem view —
      corrigido com `redirectGuestsTo(null)` em `bootstrap/app.php`. Painel ganhou tela de
      "página não encontrada" (ver entrada acima, em "Painel administrativo"). Varredura de
      comentários que ainda diziam que o painel administrativo não existia (quatro no backend,
      mais um em "Limitações conhecidas de `pages`" abaixo). Ver `docs/relatorio-sessao-15.md`.

- [x] **Caminho até a produção, lado da aplicação (sessão 16,
      `docs/tarefas/07-caminho-ate-a-producao.md`).** Um deploy antes disso subiria sem
      conteúdo e sem ninguém capaz de entrar. Entregues: `php artisan conteudo:importar-inicial`
      (cria só as páginas que faltam, nunca sobrescreve; o texto saiu do `ContentPagesSeeder`
      para `App\Support\Content\InitialPages`, fonte única dos dois caminhos),
      `php artisan usuarios:criar-super-admin` (interativo, sem senha em argumento nenhum,
      imprime o link de definição de senha, recusa se já houver super_admin ativo), serviços
      `queue` e `scheduler` no `docker-compose.yml`, suporte a `staging` de ponta a ponta
      (site inteiro `noindex` em tempo de execução, `MAIL_ALWAYS_TO`, `.env.example`
      documentado, guardas de ambiente dos seeders travadas por teste),
      `scripts/deploy/empacotar.sh` com verificação do que não pode ir junto
      (`docs/decisoes/0014-pacote-de-deploy-minimo.md`) e `docs/deploy.md`. Simulado o primeiro
      deploy num banco vazio, a partir do pacote podado. Ver `docs/relatorio-sessao-16.md`.

- [x] **Servidor, homologação e deploy automático (sessão 17,
      `docs/tarefas/07b-servidor-homologacao-e-deploy-automatico.md`).** Escrito, não
      executado: o domínio ainda não está registrado e a VPS ainda não existe, então nada foi
      provisionado de verdade. O que existe agora é `infra/` — o servidor descrito em script
      idempotente em vez de na memória de quem provisionar: `provisionar.sh` (Nginx, PHP-FPM
      8.5, Node 24, PostgreSQL 16, Redis, Certbot, `ufw`, `fail2ban`, usuário `deploy` com
      `sudo` de lista fechada, trancamento do SSH em segunda passada), `criar-ambiente.sh` (um
      ambiente inteiro: banco e usuário próprios, três hosts com TLS por `--webroot`,
      autenticação básica só no site de homologação, pool de PHP-FPM, três serviços systemd,
      backup diário), `publicar.sh` (release ao lado, troca atômica por `mv -T`, checagem de
      saúde e reversão automática), `reverter.sh` e `backup-banco.sh`. `.github/workflows/
      deploy.yml` publica em homologação a cada push na `main` e em produção por tag `v*`,
      travado por variável de repositório enquanto o servidor não existe. O painel passou a
      ler a configuração em tempo de execução
      (`docs/decisoes/0015-painel-configurado-em-tempo-de-execucao.md`), o que tornou o pacote
      promovível inteiro. `docs/deploy.md` reescrito. Ver `docs/relatorio-sessao-17.md`.

- [x] **Homologação no ar (sessão 19, mesma tarefa 07b, agora executada).** A VPS e o DNS
      passaram a existir, e tudo o que a 17 tinha escrito foi rodado de verdade contra um
      Ubuntu 24.04. **Homologação está no ar** em `homologacao-laf.softhing.com.br` (site),
      `api.homologacao-laf…` e `painel.homologacao-laf…`, com TLS, autenticação básica no
      site, 26 páginas importadas, super administrador criado e
      `DEPLOY_STAGING_HABILITADO=true` — push na `main` publica sozinho.
      A execução encontrou seis defeitos que nenhuma revisão de código tinha pego, todos
      corrigidos: o job de publicação do workflow não fazia checkout e por isso **todo deploy**
      morria antes de começar; `--trancar-ssh` deixaria o servidor sem administrador nenhum;
      `/etc/laf` era criado sem travessia para o usuário `deploy`; os modelos de Nginx usavam
      `http2 on;`, que não existe no nginx do Ubuntu 24.04; o ensaio de reversão terminava
      acusando ambiente quebrado quando tudo dera certo; e o passo a passo do `docs/deploy.md`
      mandava trancar o SSH antes de um passo que precisa de root. Ver
      `docs/relatorio-sessao-19.md`.

- [x] **Site distingue "página não existe" de "API falhou", e ganhou página de erro própria**
      — sessão 22. As sete páginas que leem o CMS traduziam *qualquer* falha da API em 404,
      inclusive 500, timeout e conexão recusada; em produção, uma instabilidade da API
      anunciaria ao Google que o site inteiro foi removido. Agora só o 404 vindo da API vira
      404 do site, e o resto vira 503 com `Cache-Control: no-store`
      (`app/utils/apiPageError.ts`, `server/plugins/sem-cache-em-erro.ts`, teto de 4s em
      `usePublicPage`). `app/error.vue` substituiu a tela padrão do Nuxt, dentro do layout do
      site e em pt-BR, com links para as seções principais no 404 e sem eles no 503. Ver
      `docs/decisoes/0019-falha-da-api-responde-503-nao-404.md` e
      `docs/relatorio-sessao-22.md`.
- [x] **O setup do zero passou a ser verificado, não só executado** — sessão 22.
      `migrate:fresh --seed` terminar em verde não dizia nada sobre o que ele promete; agora
      há teste Pest atravessando os endpoints reais de login e de conteúdo
      (`tests/Feature/Seeders/SetupDoZeroTest.php`). O `DevSuperAdminSeeder` também passou a
      reativar o dev — `db:seed` num banco já existente não limpava `deactivated_at`, e o
      login era recusado logo depois de rodar o seeder que existe para devolver o acesso.

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
- [ ] Fotos opcionais em `pickup_requests` (`media_ids` no domínio) — depende da entidade
      `media`, fora de escopo
- [ ] Endpoint para **definir** `pickup_requests.scheduled_for` (agendar data de coleta) — a
      tela do Bazar (sessão 6) já ordena e exibe a agenda por data, mas só lê; não existe
      Controller/rota para o time do bazar marcar uma data de coleta, só a mudança de
      status/anotação interna genérica das cinco entidades. Sem isso, "agenda por data" no
      painel é hoje só ordenação de pedidos sem data nenhuma preenchida.

### Site público (Nuxt)

- [ ] O conteúdo do CMS é renderizado via `v-html` (`app/pages/[...slug].vue`), o que impede
      posicionar imagem dentro do texto pelo painel administrativo — o editor produz HTML puro,
      sem espaço para um componente Vue no meio. Hoje isso é contornado com página própria por
      seção sobrepondo a rota genérica (`bazar/index.vue`, `transparencia/index.vue`, etc. — ver
      docs/fotos.md), que anexa a galeria de fotos depois do conteúdo em vez de intercalar. Se a
      instituição quiser controlar posicionamento de imagem pelo próprio admin, será preciso
      trocar por renderização em blocos.
- [ ] `/bazar/novidades` (vitrine do bazar) — fora de escopo desta sessão, depende de
      `bazaar_showcase_items` e ainda tem `[VALIDAR]` pendente (preço, quem alimenta)
- [ ] Seção de notícias (`/noticias`, `/noticias/:slug`) — depende de `posts`
- [x] `sitemap.xml` consumindo conteúdo real — feito na sessão 21 (tarefa 06, etapa 1): rotas
      fixas indexáveis, todas as páginas publicadas (`GET /api/v1/public/pages`, listagem
      mínima de slug e `updated_at`) e todos os documentos publicados, pela URL legível da
      etapa 3. Saiu de `nitro.prerender.routes` pelo mesmo motivo das páginas do CMS. Falta
      `posts`, que ainda não existe — acrescentar a busca aqui quando existir.
- [x] PDFs de transparência indexáveis — feito na sessão 21 (tarefa 06, etapa 3):
      `/transparencia/documentos/{ano}/{slug}.pdf`, `inline`, no domínio do site, com slug
      persistido na criação, 301 do endereço antigo e contagem que não soma robô. Ver
      `docs/decisoes/0017-url-publica-dos-documentos-de-transparencia.md`.
- [x] JSON-LD `NGO` na home — feito na sessão 21 (tarefa 06, etapa 2), em
      `app/composables/useOrganizationJsonLd.ts`: CNPJ como `identifier`, logo PNG, telefone,
      `foundingDate` vindo da API (nunca digitada na página) e os dois locais distintos em
      `location` (Sede/CEI e Bazar). O contraturno aparece só na descrição, como programa em
      preparação — sem `makesOffer`, `hasOfferCatalog` ou `Service`, e há teste de ponta a
      ponta que falha se alguém acrescentar um deles. Falta marcação por página (`WebPage`,
      `BreadcrumbList`), que depende de nada — só não foi pedida ainda
- [ ] **A home e os números institucionais ficaram fora da regra de 503** (sessão 22, ver
      `docs/decisoes/0019-falha-da-api-responde-503-nao-404.md`). O conteúdo da home é fixo no
      `.vue`; só as idades vêm de `useInstitutionFacts` e já degradam para nada quando a API
      falha. Derrubar a home inteira em 503 por causa de um número é desproporcional, mas
      servi-la em 200 com a frase de fundação incompleta também não está certo. Sem decisão —
      as saídas prováveis são um texto de reserva para o número ou aceitar o 503. Vale junto
      com isso conferir as outras leituras que hoje degradam em silêncio.
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
- [ ] `/como-ajudar/doar` publica a chave PIX e os dados bancários como texto (confirmados em
      10/09/2026, ver `docs/contexto.md`) — ainda não tem QR code
- [ ] `/educacao-infantil/dia-da-crianca` tem conteúdo muito magro (dois parágrafos genéricos)
      — não há nenhum fato confirmado sobre a edição do evento em `docs/contexto.md`; revisar
      assim que houver informação real, ou considerar remover a página até lá
- [x] Quatro páginas de Contraturno (`para-quem-e`, `como-funciona`, `parceiros`,
      `o-que-vem-por-ai`) estavam abaixo de ~300 palavras — resolvido nesta sessão com fatos
      novos confirmados pelo cliente em 13/09/2026 (público de 6 a 15 anos, renda até 3
      salários mínimos, meta de 100 atendidos, parceria com o SENAI, ginásio, auditório,
      lista de oficinas previstas — ver `docs/contexto.md`). Todas as páginas reescritas no
      presente para o que já existe e no futuro só para início de turmas/inscrições
      (previsto para 2027), sem afirmar operação, turma ou aluno matriculado.
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

#### Deploy — cache de HTML das páginas editáveis pelo painel

> Esta seção virou a §7 de `docs/deploy.md` na sessão 16, que é onde ela vale para quem está
> publicando. Fica aqui o registro da medição original.

Desde que o painel passou a editar `pages.content`, **toda rota cujo conteúdo vem do CMS saiu
de `nitro.prerender.routes`** e passou a ser SSR a cada request. Prerenderizar essas rotas
gravaria o texto no build, e a edição pelo painel só apareceria depois de um novo
`nuxt generate` — exatamente o que a tela existe para evitar. Continuam prerenderizadas
apenas as páginas de conteúdo fixo no `.vue` (`/o-que-fazemos`, `/politica-de-privacidade`,
`/obrigado/:tipo`). A home saiu na mesma sessão (lê `institution-facts`), e o `sitemap.xml`
saiu na 21, quando passou a listar páginas e documentos lidos da API.

`robots.txt` saiu do prerender na sessão 16: gravado no build, o mesmo pacote de deploy diria
"Allow: /" em homologação e em produção, e só uma das duas pode ser indexada. Consequência
registrada: **`npm run generate` não emite mais `robots.txt`** — o site já não era hospedável
como estático de qualquer forma (formulários, `/transparencia/documentos` e redirect de slug
antigo exigem o Nitro), mas se algum dia voltar a ser, esta rota precisa voltar para a lista.

Medido nesta sessão: salvar invalida o cache de 10 minutos do backend
(`App\Actions\Content\SavePage` → `Cache::forget`) e a alteração aparece na **requisição
seguinte** ao site, sem rebuild. A cadeia inteira só se mantém verdadeira se nada acrescentar
uma camada de cache de HTML por cima.

- [ ] **Se o Cloudflare (ou qualquer CDN na frente do Nitro) cachear HTML**, as rotas
      editáveis precisam de `Cache-Control` de TTL curto **ou** de purge no salvamento —
      senão o CDN serve o HTML antigo por horas e a edição "não aparece", com o backend e o
      Nitro certos. Por padrão o Cloudflare não cacheia HTML (só assets por extensão), então
      isso só morde se alguém criar uma Page Rule / Cache Rule de "cache everything". Duas
      saídas, na ordem de preferência: (1) não cachear HTML dessas rotas; (2) TTL curto
      (1–5 min) somado a purge por URL no salvar, o que exigiria o backend chamar a API do
      Cloudflare — dependência e credencial novas, a avaliar só se o tráfego justificar.
      Decidir junto com a escolha de hospedagem, que ainda não está fechada.

### Painel administrativo (Vue)

- [x] Edição do conteúdo das páginas existentes — feito
      (`ContentPageListView.vue`/`ContentPageFormView.vue`): título, conteúdo e campos de SEO,
      com sanitização no backend (ver
      `docs/decisoes/0010-html-do-cms-sanitizado-no-backend.md`).
- [ ] Gestão de conteúdo além dessa fatia: **criar** e **excluir** página, **renomear slug** e
      **publicar/despublicar** continuam sem tela — a API aceita as quatro operações, mas o
      painel não as expõe. Para quem não tem `direcao`, `slug` e `status` são ignorados no
      update (`UpdatePageRequest::prepareForValidation` + `PagePolicy::managePublication`), de
      modo que o recorte vale mesmo para chamada direta à API. Renomear slug é a mais delicada
      das quatro: quebra link já divulgado e mexe na lista de prerender do Nuxt.
- [ ] `posts` e mídia seguem sem entidade e sem tela (ver "Backend — entidades da Fase 1
      restantes").
- [x] Tela de gestão de usuários — feita (`UserListView.vue`/`UserFormView.vue`), junto das
      telas de conta (`/definir-senha`, `/conta`).
- [x] Telas de listagem e detalhe dos cinco formulários recebidos — feitas
      (`SubmissionListView.vue`/`SubmissionDetailView.vue`, genéricas por `:resource`, ver
      `src/config/submissionResources.ts`), com mudança de status e nota interna. Esta entrada
      não tinha chegado ao roadmap antes da sessão 15, que também corrigiu o link do e-mail de
      notificação para apontar para elas (faltava o prefixo `/admin`, ver
      `docs/tarefas/05-correcoes-de-codigo.md`) e vários comentários no backend que ainda
      diziam que a tela não existia.
- [x] Tela de "página não encontrada" (sessão 15) — rota coringa no vue-router e `:resource`
      fora do mapa de acesso levam à mesma tela (`NotFoundState.vue`), dentro do layout
      autenticado. Antes, URL desconhecida renderizava em branco e recurso inválido caía num
      estado `unmapped` com mensagem própria; unificados porque, para quem usa o painel, os
      dois são a mesma coisa ("isto não existe").
- [x] Editor de texto rico com sanitização no backend — Tiptap no painel, allowlist em
      `App\Support\Html\ContentSanitizer`, dependências justificadas no ADR 0010.
- [ ] Preview de SERP nos campos de SEO — hoje há só contador de caracteres na descrição,
      avisando a partir de 160 (onde o Google costuma cortar), sem a simulação visual do
      resultado de busca.
- [ ] `frontend-admin` não tem nenhuma ferramenta de teste (Vitest, Testing Library ou
      equivalente) — a sessão 6 construiu a primeira fatia de UI real do painel sem nenhum
      teste automatizado do lado do front, só Pest no backend e verificação manual via
      `php artisan tinker` (sem navegador disponível na sessão). Vale considerar antes da
      próxima leva de telas, quando a superfície ficar grande demais para revisão visual pura.

### Autenticação — pendências pós-lançamento

- [ ] **"Esqueci minha senha" por e-mail** — depende de SMTP configurado em produção (ver
      `MAIL_MAILER` em `.env.example`, hoje só Mailpit em dev). O broker `users` em
      `config/auth.php` já existe pronto para isso (60 min de validade, padrão), separado do
      broker `user_setup` que o link administrativo usa (24h) — implementar como
      `POST /api/v1/auth/forgot-password` + `POST /api/v1/auth/reset-password` reaproveitando
      `Password::broker('users')->sendResetLink()`/`->reset()`, mesmo padrão de
      `App\Actions\Auth\SetUserPassword`.
- [ ] **2FA** — schema já existe em `users` (`two_factor_secret`, `two_factor_recovery_codes`,
      `two_factor_confirmed_at`, todos cifrados), mas sem fluxo de setup, desafio no login ou
      recuperação. `docs/protecao-de-dados.md`/`docs/dominio.md` registram 2FA como
      obrigatório para conta de sistema — pendência de implementação, não de desenho.

### Formulários públicos — decisões e pendências desta sessão

- [ ] `[LACUNA]` Endereços reais de notificação por tipo de formulário — hoje
      `config('forms.notification_recipients')` usa placeholders em domínio `.invalid`
      (RFC 2606). Definir via `FORM_RECIPIENT_*` no `.env` de cada ambiente quando a
      instituição informar quem recebe cada tipo (secretaria do CEI, coordenação do
      contraturno, bazar, etc.)
- [ ] `TRUSTED_PROXIES` (`bootstrap/app.php`) está com o default de loopback, correto só para
      dev onde site público e API rodam no mesmo host. Em produção, precisa do IP/CIDR real do
      serviço do site público (Nuxt) — sem isso, o rate limit por IP dos cinco formulários passa
      a ver sempre o IP do próprio Nuxt, não o do visitante
      (ver `frontend-site/server/api/forms/[tipo].post.ts`)
- [x] `/politica-de-privacidade` deixou de ser rascunho: reescrita na sessão 20 a partir de um
      levantamento do código (ver `docs/relatorio-sessao-20.md`), afirmação por afirmação. O
      aviso de "rascunho de trabalho" saiu do texto público.

### Política de privacidade — pendências que sobreviveram à reescrita

Nenhuma delas pode virar texto público antes de estar resolvida. Todas são **bloqueantes para
produção**, não para homologação.

- [ ] **Validação jurídica antes de produção.** O texto é fiel ao código, o que é uma garantia
      de engenharia — não é parecer jurídico. Já registrado como pendência geral em
      `docs/protecao-de-dados.md`; fica repetido aqui porque agora existe uma página publicada
      que dá a impressão de estar pronta.
- [ ] **Encarregado (DPO) nomeado**, com canal próprio publicado na política. Hoje a página
      direciona aos canais gerais da instituição (formulário de contato, telefone, endereço) —
      correto enquanto não há DPO, insuficiente quando houver.
- [ ] **`FORM_CONSENT_TERMS_VERSION` no `.env` de cada servidor.** A variável vive em
      `shared/.env`, que o deploy **não** sobrescreve: subir a nova política sem atualizar o
      `.env` do servidor faz a API gravar em `consent_terms_version` o número de uma versão que
      já saiu do ar. Atualizar no mesmo deploy em que o texto muda, em homologação e em
      produção.
- [ ] **A política afirma coisas que dependem da configuração do servidor, não do código.** Se
      qualquer uma mudar, o texto mente até ser corrigido:
      - "o registro técnico de acesso do servidor é descartado após 14 dias" — vem do
        `logrotate` do nginx (`daily`, `rotate 14`), não do repositório;
      - "nenhuma ferramenta de medição de audiência está ativa" — vale enquanto
        `NUXT_PUBLIC_UMAMI_*` estiver vazia no `site.env`. Ligar o Umami exige atualizar a
        política **e** subir a versão, antes de ligar;
      - "o servidor fica nos Estados Unidos" — muda quando a hospedagem voltar para o Brasil.
- [ ] **Retenção das cópias de segurança em produção.** A política diz que uma cópia pode
      conter registro já apagado até ser descartada, sem prometer prazo — porque produção ainda
      não tem política de retenção definida (homologação usa 7 dias, ver
      `/etc/laf/<ambiente>.conf`). Definido o prazo de produção, vale dizê-lo na página.
- [ ] `php artisan queue:work` (ou `schedule:work` para os jobs de expurgo) precisa estar
      rodando em produção — nada disparado por este código roda sozinho sem um worker; ver
      `docker-compose.yml`, que hoje não tem um serviço dedicado a isso
- [x] As páginas de conteúdo do CMS não linkavam diretamente para os formulários
      correspondentes — corrigido para Contraturno (CTA "Avise-me quando abrir" no pilar e em
      "O Que Vem por Aí"), Bazar (WhatsApp em destaque e link para o formulário em "O Que
      Aceitamos") e Educação Infantil (link para a nova página de Matrícula). Notícias,
      Voluntariado e Contato ainda dependem de página própria que não existe.
- [ ] Prazos de retenção usados (12/6/24/36/6 meses, ver `docs/estrutura-site.md` §2.2) são
      os sugeridos no documento, não confirmados pela instituição — ver `[VALIDAR]` abaixo.
      **Subiu de importância na sessão 20:** esses números agora estão publicados em
      `/politica-de-privacidade`, então deixaram de ser sugestão interna e viraram compromisso
      com o titular. Confirmar com a instituição antes de produção; mudá-los depois exige mudar
      a política e subir a versão do termo

### Validações pendentes com a instituição

- [x] `[VALIDAR]` Campo `school` em `program_applications` — obsoleto: o formulário deixou de
      ser uma inscrição e virou um aviso de "me avise quando abrir" (ver ADR 0007,
      atualização de 13/09/2026); `school`, `email`, `teen_age` e `message` foram removidos,
      só resta nome e telefone do responsável
- [ ] `[LACUNA]` **Dia** do início do Bazar Beneficente (1968) e da criação do CEI Anália
      Franco (2002) — só o ano está documentado. Enquanto for só o ano, a idade calculada sai
      por diferença de ano e fica adiantada de 1º de janeiro até o aniversário real (ver
      `docs/decisoes/0012-numeros-institucionais-calculados.md`). Confirmando o dia, o conserto
      é trocar `'1968'` por `'1968-MM-DD'` em `config/institution.php` — nada mais muda
- [ ] `[VALIDAR]` Vitrine do bazar (`bazaar_showcase_items`) terá preço? Há quem alimente
      semanalmente? (schema já esboçado com `price` nullable — ver `docs/dominio.md`)
- [ ] `[VALIDAR]` Prazos de retenção exatos de cada formulário
- [ ] `[VALIDAR]` Convênio com a Secretaria Municipal de Educação impõe campo ou relatório?
- [x] `[CONFIRMAR]` Faixa etária do Contraturno — confirmada em 13/09/2026: 6 a 15 anos,
      famílias com renda de até 3 salários mínimos, meta de 100 atendidos (ver
      `docs/contexto.md`). `[VALIDAR]` ainda em aberto: critérios de seleção além de faixa
      etária e renda, quando a inscrição efetiva abrir
- [ ] `[LACUNA]` Horário de funcionamento do Bazar — endereço e telefones já confirmados em
      10/09/2026 (ver `docs/contexto.md`), só falta horário e mapa de acesso para
      `/bazar/visite-a-loja`
- [ ] `[LACUNA]` Telefone da Central de Vagas da Prefeitura de Londrina — endereço confirmado
      em 13/09/2026 (Rua Benjamin Constant, 800, Centro), `/educacao-infantil/matricula`
      publica só o endereço até o telefone ser confirmado
- [ ] Nota Paraná e destinação de Imposto de Renda (fundo da criança e do adolescente/FMDCA)
      — o cliente confirmou em 10/09/2026 que nenhuma das duas opções está disponível hoje.
      `/como-ajudar/nota-parana` e `/como-ajudar/empresas-ir` foram removidas do
      `ContentPagesSeeder`; voltam ao escopo só quando a instituição avisar (ver
      `docs/contexto.md`)
- [ ] Texto final de missão, visão e valores (o publicado é rascunho de trabalho explícito)
- [ ] Preencher `docs/lgpd/inventario-de-dados.md` — bloqueia toda a Fase 2

### Decisões técnicas em aberto, registradas mas não bloqueantes

- [x] ~~**O painel é o único artefato de deploy amarrado ao ambiente em tempo de build.**~~
      Resolvido na sessão 17 (tarefa 07b), pela alternativa que estava anotada aqui: o painel
      lê `window.__LAF_CONFIG__` de um `/config.js` servido fora do bundle e reescrito no
      servidor a cada publicação. As `VITE_*` continuam valendo como origem secundária, em
      desenvolvimento e na bateria de ponta a ponta. O pacote passou a ser promovível inteiro.
      Ver `docs/decisoes/0015-painel-configurado-em-tempo-de-execucao.md`.
- [ ] `pages.og_image_id`: entra numa migration futura, junto da entidade `media` — decisão
      de sessão anterior, para não criar coluna sem uso funcional possível antes de `media`
      existir
- [ ] **Ordenação de listagem sem critério de desempate.** Todas as listagens administrativas
      dos cinco formulários ordenam só por `created_at`, que o Laravel grava com precisão de
      **segundo** (`timestamps()` cria `timestamp(0)` no Postgres). Dois registros criados no
      mesmo segundo empatam, e aí a ordem que o Postgres devolve não é definida: com
      `LIMIT/OFFSET`, a mesma linha pode aparecer em duas páginas ou sumir entre elas.
      Descoberto na sessão 21 por uma falha real da bateria de ponta a ponta — o teste da
      política de privacidade pegava "a primeira da lista" e recebeu a mensagem do teste
      anterior, criada 0,9 s antes. O teste foi corrigido (procura pelo assunto), mas a
      listagem continua sem desempate: acrescentar `->orderByDesc('id')` depois do
      `latest('created_at')` nas seis listagens resolve. Não foi feito na sessão 21 por ser
      escopo de outra tarefa
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
filha órfã é maior do que quando isso foi escrito. A tela de admin para páginas
(`ContentPageListView`/`ContentPageFormView`) já existe e não valida nada disso — vale
resolver (validar em cascata ou migrar para `parent_id`) antes que alguém use a tela para
excluir ou renomear uma página-mãe de verdade, não depois.

## Fase 2 — bloqueada

- [ ] Cadastro de assistidos (`assisted_minors`, `guardians`, `guardianships`, `consents`,
      `health_records`, `classes`, `enrollments`, `attendance_records`) — **bloqueado** até
      `docs/lgpd/inventario-de-dados.md` ser preenchido com a instituição
