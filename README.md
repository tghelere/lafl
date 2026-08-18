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

Pré-requisito: Docker com Compose v2 (`docker compose version`). **Não foi validado nesta
sessão** — o ambiente de desenvolvimento usado para construir esta fatia não tinha Docker
disponível (WSL sem a integração do Docker Desktop ativa). O `docker-compose.yml` e o
Dockerfile foram escritos e revisados, mas o primeiro `docker compose up` real ainda precisa
ser conferido por quem tiver Docker à mão.

```bash
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
./vendor/bin/phpstan analyse

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

## Pendências conhecidas

- `docker compose up` completo não foi validado nesta sessão (sem Docker no ambiente).
- Fluxo de login não foi verificado num navegador real (sem navegador disponível neste
  ambiente) — validado via testes automatizados (Pest) e via `curl` reproduzindo o fluxo
  completo do Sanctum SPA mode (csrf-cookie → login → rota protegida → logout → 401).
- 2FA: só as colunas existem em `users`; fluxo de setup/desafio/recovery codes fica para
  quando houver papel com acesso a dado de assistido (ver `docs/arquitetura.md`).
- Domínio de assistidos (`assisted_minors`, `guardians`, `consents` etc.) ainda não
  implementado — preencher `docs/lgpd/inventario-de-dados.md` com a instituição antes de
  começar.
