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
docker/               # imagens de desenvolvimento
docs/                 # arquitetura, proteção de dados, domínio, convenções
.github/workflows/    # CI
```

## Setup local — com Docker

Pré-requisito: Docker Desktop rodando, com a **integração WSL ativa para a distro do
projeto** (Docker Desktop → Settings → Resources → WSL Integration → habilitar a distro →
Apply & Restart). Sem esse toggle específico, o Postgres/Redis/Mailpit sobem normalmente,
mas o container `app` falha ao montar `./backend` (erro de "distro mount service").

**Validado de ponta a ponta nesta sessão**, com o toggle ativado: os quatro serviços sobem,
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

## Setup local — sem Docker

Foi o caminho efetivamente usado e testado durante esta sessão (sem Postgres/Redis nativos
disponíveis). Requer PHP 8.3+, Composer e Node 20+.

```bash
cp backend/.env.example backend/.env
```

Editar `backend/.env` para rodar sem serviços externos:

```dotenv
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite   # relativo a backend/
CACHE_STORE=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
```

```bash
cd backend
composer install
php artisan key:generate
touch database/database.sqlite
php artisan migrate:fresh --seed
php artisan serve                                   # http://localhost:8000

cd ../frontend-admin && npm install && npm run dev   # http://localhost:5173
cd ../frontend-site  && npm install && npm run dev   # http://localhost:3000
```

Em produção/staging, `DB_CONNECTION=pgsql` e Redis são obrigatórios (ver `.env.example`) —
SQLite/file aqui são só para rodar esta fatia sem Docker, nunca para dado real.

## Login de desenvolvimento

Criado pelo `DevSuperAdminSeeder` (só roda em `local`/`testing`, nunca em produção — ver
`docs/protecao-de-dados.md`, regra de não usar dado real em seeder):

| Campo | Valor |
|---|---|
| E-mail | `dev@laranaliafranco.local` |
| Senha | `password` |
| Papel | `super_admin` |

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

```bash
# backend/
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

CI (`.github/workflows/ci.yml`) roda os mesmos comandos a cada push/PR, com o backend contra
Postgres real (não SQLite) para paridade com produção.

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
  módulo de terceiros — evita depender de conteúdo que ainda não existe (`pages`/`posts`) ou
  instalar um pacote novo sem necessidade real ainda.
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
