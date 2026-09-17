> **Modelo recomendado: Opus**

# 07 — Caminho até a produção (lado da aplicação)

Leia `docs/tarefas/README.md`. Rode depois da 05. A 07b (servidor e deploy) vem logo depois e
usa o que esta tarefa produzir.

## Problema

Um deploy hoje subiria sem conteúdo e sem ninguém capaz de entrar: `ContentPagesSeeder` e
`DevSuperAdminSeeder` só rodam em `local`/`testing`/`e2e`, e não existe outro jeito de criar a
primeira conta `super_admin`. Além disso, não há um pacote de deploy definido: o que vai para o
servidor precisa ser **só o necessário para rodar** — nunca o repositório inteiro.

## Etapa 1 — Conteúdo inicial

Comando artisan (ex.: `php artisan conteudo:importar-inicial`) que cria **apenas as páginas que
ainda não existem**, a partir da mesma fonte do `ContentPagesSeeder` (sem duplicar o conteúdo
em dois lugares). Nunca sobrescreve página existente — fora do ambiente local o texto é editado
pelo painel. Idempotente, com resumo do que criou e do que pulou. Funciona em `staging` e
`production`. Testes Pest.

## Etapa 2 — Primeira conta de super_admin

Comando (ex.: `php artisan usuarios:criar-super-admin`) que pede nome e e-mail
interativamente, cria a conta e imprime o **link de definição de senha** (reaproveitar
`GeneratePasswordLink`). Nenhuma senha em argumento de linha de comando nem no histórico do
shell. Auditoria registrada. Recusa criar se já existir `super_admin` ativo, salvo com opção
explícita. Testes Pest.

## Etapa 3 — Worker e scheduler

Serviços de fila e scheduler no `docker-compose.yml` (paridade com o servidor — hoje nada
disparado por fila ou agendamento roda sozinho: notificações por e-mail, expurgo por
`expires_at`, expurgo de endereço de coleta). Conferir que o expurgo agendado roda.

## Etapa 4 — Ambiente `staging` (homologação)

O `docs/arquitetura.md` já prevê `staging`. Garantir que a aplicação suporte esse ambiente de
ponta a ponta:

- `backend/.env.example` documenta as variáveis de `staging` e `production`, sem valor real.
- Em `staging`, **todo** o site público responde `X-Robots-Tag: noindex, nofollow` e o
  `robots.txt` bloqueia tudo — independente de qualquer outra configuração. Teste cobrindo isso.
- Seeders de desenvolvimento (`DevSuperAdminSeeder`, `TransparencyDocumentsSeeder` com PDFs em
  branco) **não** rodam em `staging` nem em `production`.
- Mailer de `staging` configurável para um único endereço de teste (nenhum e-mail sai para
  destinatário real durante a homologação).

## Etapa 5 — Pacote de deploy

Script versionado (ex.: `scripts/deploy/empacotar.sh`), usado pelo CI na 07b, que gera a
partir de uma árvore limpa um pacote contendo **somente**:

- `frontend-site/.output/` (build do Nuxt);
- `frontend-admin/dist/` (build do painel);
- `backend/` com `composer install --no-dev --optimize-autoloader`, **sem** `tests/`,
  `scripts/`, `.env*`, `phpunit.xml`, `phpstan.neon`, `pint.json`, `stubs/`, `storage/logs`,
  arquivos de editor;
- os arquivos de marca efetivamente servidos.

Ficam de fora, sempre: `docs/`, `CLAUDE.md`, `README.md` (todos), `.claude/`, `.github/`,
`e2e/`, `docker/`, `shared/` (exceto o que o build já copiou), `.git`, qualquer `.md` e o código-
fonte Vue/Nuxt. O script falha se encontrar qualquer um desses dentro do pacote (lista de
verificação no próprio script). Registrar o motivo em `docs/decisoes/` (ADR nova): o servidor
pertence à instituição, e o que é propriedade da Softhing — código-fonte dos frontends,
documentação, testes, decisões — não é copiado para lá.

## Etapa 6 — Documento de deploy

`docs/deploy.md`: requisitos do servidor (versões iguais às do CI e do compose), ambientes
(`staging` e `production` no mesmo VPS, bases e processos separados), ordem do primeiro deploy
(migrations, `RoleSeeder`, importação de conteúdo, criação do super_admin, upload real dos
documentos de transparência), processos permanentes (Nitro, PHP-FPM, worker, scheduler) e
checklist de variáveis de ambiente: `APP_KEY`, `FIELD_ENCRYPTION_KEY`, `BLIND_INDEX_KEY` (com
aviso de backup — perder é irreversível), `SESSION_DOMAIN`, `SANCTUM_STATEFUL_DOMAINS`,
`TRUSTED_PROXIES`, `ADMIN_BASE_URL`, `FORM_RECIPIENT_*`, SMTP, `NUXT_PUBLIC_*`, Umami. Incluir a
regra de CDN/cache de HTML já registrada no roadmap. Atualizar "Ambientes e deploy" em
`docs/arquitetura.md` apontando para este documento.

## Verificação

Pest, Pint, Larastan, builds. Rodar o script de empacotamento e listar o conteúdo do pacote no
relatório (e a verificação de proibidos passando). Simular o primeiro deploy localmente num
banco vazio (base temporária, removida ao final): migrations → papéis → importação →
super_admin → login pelo link no painel → página do CMS abrindo no site.
