# Lar Anália Franco

Monorepo do site institucional e do sistema administrativo do Lar Anália Franco. Contexto,
regras invioláveis e convenções de código estão em `CLAUDE.md` e `docs/`.

**Estado atual:** fatia vertical de autenticação (Sanctum SPA mode) e a infraestrutura de
proteção de dados (normalização, blind index, cifra de campo). O domínio de assistidos
ainda **não** foi implementado — ver `docs/dominio.md` e `docs/protecao-de-dados.md` antes de
começar.

## Estrutura

```
backend/            # Laravel — API REST, /api/v1
frontend-site/       # Nuxt — site público (SSR/SSG)
frontend-admin/       # Vue 3 SPA — painel administrativo
e2e/                  # bateria de ponta a ponta (Playwright) — ver e2e/README.md
docker/               # imagens de desenvolvimento
docs/                 # arquitetura, proteção de dados, domínio, convenções, deploy
scripts/deploy/       # empacotamento para o servidor (ver docs/decisoes/0014-...)
.github/workflows/    # CI
```

## Setup local — com Docker

Pré-requisito: Docker Desktop rodando, com a **integração WSL ativa para a distro do
projeto** (Docker Desktop → Settings → Resources → WSL Integration → habilitar a distro →
Apply & Restart). Sem esse toggle específico, o Postgres/Redis/Mailpit sobem normalmente,
mas o container `app` falha ao montar `./backend` (erro de "distro mount service").

**Validado de ponta a ponta nesta sessão**, com o toggle ativado: os serviços sobem,
`composer install` + `migrate:fresh --seed` rodam dentro do container `app`, e o fluxo
completo do Sanctum SPA mode funciona contra Postgres/Redis reais (csrf-cookie → login →
rota protegida → logout → 401), com a suíte Pest inteira (30/30) passando dentro do
container.

```bash
cp .env.example .env                               # portas do compose, ver abaixo
cp backend/.env.example backend/.env
cp frontend-admin/.env.example frontend-admin/.env
cp frontend-site/.env.example frontend-site/.env

docker compose up -d

docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate:fresh --seed

cd frontend-admin && npm install && npm run dev    # http://localhost:5173
cd frontend-site  && npm install && npm run dev    # http://localhost:3000
```

Se alguma porta padrão (5432, 6379, 8000, 8025, 1025) já estiver em uso por outro serviço
local, redefina em `.env` na raiz — ex. `DB_FORWARD_PORT=5433` (`APP_FORWARD_PORT`,
`DB_FORWARD_PORT`, `REDIS_FORWARD_PORT`, `MAILPIT_UI_PORT`, `MAILPIT_SMTP_PORT`).

Ainda faltam gerar `FIELD_ENCRYPTION_KEY` e `BLIND_INDEX_KEY` em `backend/.env` — ver seção
"Chaves de criptografia" abaixo.

Mailpit (e-mails capturados em dev): http://localhost:8025
Documentação OpenAPI (Scramble): http://localhost:8000/docs/api

### Fila e agendador

O compose sobe dois containers além da API: `queue` (`php artisan queue:work`) e `scheduler`
(`php artisan schedule:work`), na mesma imagem do `app` — é a mesma separação de processos
que o servidor usa (ver `docs/deploy.md`). Sem eles, nada disparado por fila ou agendamento
acontece: a notificação por e-mail dos formulários fica na fila do Redis sem nunca ser
enviada, e os dois expurgos por retenção (`App\Jobs\PurgeExpiredFormSubmissions`,
`App\Jobs\PurgeCompletedPickupRequestAddresses`, agendados em `backend/routes/console.php`)
nunca rodam.

```bash
docker compose logs -f queue scheduler                 # acompanhar
docker compose exec app php artisan schedule:list      # o que está agendado e quando roda
docker compose exec app php artisan schedule:test      # dispara um agendado agora, sem esperar
```

Fora do Docker, os mesmos dois processos precisam estar no ar em terminais próprios
(`php artisan queue:work` e `php artisan schedule:work`, a partir de `backend/`).

## Setup local — sem Docker (parcial)

**PostgreSQL é obrigatório**, sem alternativa: é o único banco suportado em desenvolvimento,
teste e produção (ver `CLAUDE.md`, "Armadilhas conhecidas"). O projeto já usou SQLite como
atalho para rodar sem serviço externo; essa opção não existe mais, porque produzia um segundo
comportamento de banco que escondeu bug real (busca com `LIKE` sensível a maiúsculas, que o
Postgres respeita e o SQLite ignora).

Dá para rodar PHP e Node na máquina e deixar só a infraestrutura no Docker:

```bash
docker compose up -d postgres redis mailpit      # só os serviços

cp backend/.env.example backend/.env             # já vem apontando para pgsql
cd backend
composer install
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve                                   # http://localhost:8000

cd ../frontend-admin && npm install && npm run dev   # http://localhost:5173
cd ../frontend-site  && npm install && npm run dev   # http://localhost:3000
```

Sem Docker nenhum, é preciso um PostgreSQL 16 local ouvindo em `DB_HOST`/`DB_PORT` (e, para
fila/cache/sessão como configurados, um Redis) — ver `backend/.env.example`.

## Login de desenvolvimento

Criado pelo `DevSuperAdminSeeder` (só roda em `local`/`testing`, nunca em produção — ver
`docs/protecao-de-dados.md`, regra de não usar dado real em seeder):

| Campo | Valor |
|---|---|
| E-mail | `dev@laranaliafranco.local` |
| Senha | `password` |
| Papel | `super_admin` |

Isso existe **só** em `local`/`testing`. Em homologação e em produção a primeira conta é criada
por `php artisan usuarios:criar-super-admin`, que não aceita senha em lugar nenhum — imprime o
link de definição de senha. Ver `docs/deploy.md`.

## Chaves de criptografia

`backend/.env` precisa de três chaves **distintas**, nenhuma delas reutilizada:

| Variável | Uso | Gerar com |
|---|---|---|
| `APP_KEY` | Encrypter padrão do Laravel (sessão, cookies) | `php artisan key:generate` |
| `FIELD_ENCRYPTION_KEY` | Cifra de campo pessoal (`App\Casts\FieldEncrypted`) | `php artisan key:generate --show` |
| `BLIND_INDEX_KEY` | HMAC dos blind indexes (`App\Services\BlindIndexService`) | `php artisan key:generate --show` |

Perder `FIELD_ENCRYPTION_KEY` é perder os dados cifrados de forma irreversível. Ver
`docs/protecao-de-dados.md`. Nenhuma entidade de domínio usa essas chaves ainda — a
infraestrutura foi construída e testada com um model de exemplo só dentro dos testes.

## Auditoria

Todo login, logout e troca de senha é registrado via `spatie/laravel-activitylog` (nunca o
valor da senha). Para inspecionar:

```bash
cd backend && php artisan tinker --execute="dump(\Spatie\Activitylog\Models\Activity::latest()->first()->toArray());"
```

## Testes e qualidade

**A suíte Pest roda exclusivamente contra PostgreSQL** (ver `CLAUDE.md`, "Armadilhas
conhecidas") — como todo o resto do projeto. Um bug real de busca (`LIKE` sensível a
maiúsculas, que o Postgres respeita e o SQLite ignora) passou pela suíte inteira sem ser
notado enquanto ela rodava em `:memory:`; rodar contra o mesmo banco de produção é o que
garante que "verde localmente" signifique "verde de verdade".

O banco de teste (`lar_analia_franco_test`) é **separado do banco de desenvolvimento**
(`lar_analia_franco`), no mesmo Postgres do `docker-compose.yml` — `php artisan test` nunca
apaga dado de desenvolvimento, mesmo rodando `migrate:fresh` internamente
(`Illuminate\Foundation\Testing\RefreshDatabase`).

```bash
# backend/ — precisa do Postgres do compose no ar (docker compose up -d postgres), mesmo que
# o resto do stack rode sem Docker (ver "Setup local — sem Docker")
php artisan test
./vendor/bin/pint
./vendor/bin/phpstan analyse --memory-limit=512M   # 128M (padrão do PHP) estoura com o volume atual de código

# frontend-admin/
npm run lint
npm run build

# frontend-site/
npm run build      # SSR
npm run generate   # SSG
```

### Ponta a ponta (Playwright)

O painel administrativo é coberto por uma bateria de ponta a ponta contra a pilha real — API,
painel e site público em modo de produção, num Firefox de verdade:

```bash
cd e2e && npm install && npm run test:e2e
```

Ela sobe os três serviços sozinha (portas 8100/5175/3100, distintas das de desenvolvimento) e
usa um **terceiro banco**, `lar_analia_franco_e2e`, recriado a cada execução. Só exige o
Postgres e o Redis do compose no ar. Detalhes, regras de escrita dos testes e o que fazer
quando o CI falha: `e2e/README.md`.

**Pest, os scripts de concorrência e a bateria de e2e não devem rodar ao mesmo tempo onde
compartilham banco** — Pest e os scripts de concorrência dividem `lar_analia_franco_test`, e os
dois rodam `migrate:fresh`. A bateria de e2e tem banco próprio e pode rodar em paralelo com
qualquer um dos dois.

A configuração de teste vive em `backend/.env.testing` (commitado — só valores fictícios,
nunca dado real), carregado automaticamente por `APP_ENV=testing`
(`backend/phpunit.xml`) em vez de `backend/.env`. Para preparar o banco pela primeira vez, ou
depois de uma migration nova:

```bash
cd backend && php artisan migrate:fresh --env=testing --force
```

`docker/postgres/init-test-db.sql` cria `lar_analia_franco_test` automaticamente no primeiro
boot do container `postgres` (volume de dados vazio). Num volume já existente de antes desta
mudança, criar o banco manualmente uma vez:

```bash
docker compose exec postgres psql -U lar -d postgres -c "CREATE DATABASE lar_analia_franco_test;"
```

O banco de e2e (`lar_analia_franco_e2e`) tem o mesmo arranjo — `docker/postgres/init-e2e-db.sql`
no primeiro boot —, mas não precisa de nenhum passo manual num volume já existente:
`php artisan e2e:prepare` cria o banco sozinho quando ele não existe.

CI (`.github/workflows/ci.yml`) roda os mesmos comandos a cada push/PR, com um container de
serviço Postgres na mesma versão principal do `docker-compose.yml` (16).

## Convenções

Ver `CLAUDE.md` na raiz e os quatro documentos em `docs/` antes de qualquer alteração —
especialmente `docs/protecao-de-dados.md` antes de criar coluna com dado pessoal.

## Divergências desta fatia em relação ao pedido original

Registradas com justificativa nos commits correspondentes; resumo:

- **Nuxt 4, não Nuxt 3** — `CLAUDE.md` fixa Nuxt 3, mas essa major está sem patches de
  segurança há mais de um ano. Confirmado com o solicitante; usar a mesma lógica já aplicada
  ao Laravel (versão estável atual, não uma major específica).
- **Cast de criptografia customizado** (`App\Casts\FieldEncrypted`), não o `encrypted`
  nativo do Eloquent — o cast nativo usa sempre `APP_KEY`; a chave de campo precisa ser
  separada.
- **Troca de senha autenticada implementada; recuperação de senha ("esqueci minha senha")
  não** — o pedido original listava só a troca autenticada nos endpoints, mas mencionava rate
  limit também para "recuperação de senha". Ficou fora do escopo desta fatia.
- **`sitemap.xml`/`robots.txt` como rotas Nitro dinâmicas**, não arquivos estáticos nem
  módulo de terceiros. O `sitemap.xml` monta a lista a cada requisição, a partir das páginas
  publicadas (`GET /api/v1/public/pages`) e dos documentos de transparência publicados —
  publicar pelo painel aparece no sitemap sem novo deploy. Módulo de terceiro
  (`@nuxtjs/sitemap`) continua fora: a rota inteira tem menos de 120 linhas.
- **`docker/php/Dockerfile` usa `php -S` direto no `CMD`, não `php artisan serve`** —
  descoberto validando o compose de ponta a ponta: `artisan serve` spawna um subprocesso PHP
  para o servidor embutido que **não herda o ambiente do container** (só repassa `APP_ENV` e
  `PATH`), então `DB_HOST`/`REDIS_HOST`/`MAIL_HOST` injetados via `environment:` do compose
  desapareciam silenciosamente e a app caía de volta em `127.0.0.1`. `php -S` roda como PID 1
  e herda o ambiente real do container. Também removi `opcache` da lista de extensões do
  Dockerfile — o build quebra nesta combinação PHP 8.5-alpine/PECL; não é necessário para
  `php -S` em dev (só importa na imagem de produção com php-fpm).

## Troubleshooting

- **Tela do painel admin em branco, sem erro visível na UI** — alguma extensão de navegador
  (bloqueador de rastreamento, "anti-fingerprint") pode fazer `window.localStorage` **lançar
  exceção** em vez de simplesmente não existir. Isso derruba bibliotecas de terceiros
  (`@vue/devtools-kit`, via Pinia) antes do Vue montar. `frontend-admin/index.html` já tem um
  shim silencioso para esse caso — se ainda assim a tela ficar em branco, olha o console do
  navegador antes de mais nada.
- **"CSRF token mismatch" no login, mesmo com a senha certa** — cookie de sessão antigo,
  cifrado com uma `APP_KEY` que não é mais a atual (acontece sempre que `APP_KEY` é trocada,
  o backend reinicia com uma chave nova, ou o `docker compose down -v` recria o volume do
  Postgres/sessão). Limpa os cookies de `localhost` (DevTools → Application → Storage →
  Clear site data) e tenta de novo.
- **`SecurityError` de `localStorage` ao rodar `npm run dev` do site público, no Chrome com
  extensões de privacidade instaladas** — vem do próprio cliente de dev do Vite (HMR),
  não do código do site, e não acontece em produção. Para QA visual do site público em dev,
  usar Firefox ou testar o build gerado (`npm run build && npm run preview`, ou `npm run
  generate` e servir `.output/public`).

## Pendências conhecidas

- 2FA: só as colunas existem em `users`; fluxo de setup/desafio/recovery codes fica para
  quando houver papel com acesso a dado de assistido (ver `docs/arquitetura.md`).
- Domínio de assistidos (`assisted_minors`, `guardians`, `consents` etc.) ainda não
  implementado — preencher `docs/lgpd/inventario-de-dados.md` com a instituição antes de
  começar.
