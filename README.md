# Lar Anália Franco

Monorepo do site institucional e do sistema administrativo do Lar Anália Franco. Contexto,
regras invioláveis e convenções de código estão em `CLAUDE.md` e `docs/`.

## Estrutura

```
backend/            # Laravel — API REST, /api/v1
frontend-site/      # Nuxt 3 — site público (SSR/SSG)
frontend-admin/      # Vue 3 SPA — painel administrativo
docker/              # imagens de desenvolvimento
docs/                # arquitetura, proteção de dados, domínio, convenções
```

## Setup local — com Docker

Pré-requisito: Docker com Compose v2 (`docker compose version`).

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

Mailpit (e-mails capturados em dev): http://localhost:8025
Documentação OpenAPI (Scramble): http://localhost:8000/docs/api

## Setup local — sem Docker

Útil quando a integração do Docker com o WSL/host não está disponível. Requer PHP 8.3+,
Composer, PostgreSQL e Redis instalados localmente (ou SQLite como fallback só para rodar a
suíte de testes — nunca para dados reais).

```bash
cp backend/.env.example backend/.env
# editar backend/.env: DB_HOST, DB_PORT, REDIS_HOST etc. para os serviços locais

cd backend
composer install
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve                                   # http://localhost:8000

cd ../frontend-admin && npm install && npm run dev   # http://localhost:5173
cd ../frontend-site  && npm install && npm run dev   # http://localhost:3000
```

## Chaves de criptografia

`backend/.env` precisa de três chaves **distintas**, nenhuma delas reutilizada:

| Variável | Uso | Gerar com |
|---|---|---|
| `APP_KEY` | Encrypter padrão do Laravel (sessão, cookies) | `php artisan key:generate` |
| `FIELD_ENCRYPTION_KEY` | Cifra de campo pessoal (`App\Casts\FieldEncrypted`) | `php artisan key:generate --show` |
| `BLIND_INDEX_KEY` | HMAC dos blind indexes | `php artisan key:generate --show` |

Perder `FIELD_ENCRYPTION_KEY` é perder os dados cifrados de forma irreversível. Ver
`docs/protecao-de-dados.md`.

## Testes e qualidade

```bash
# backend/
php artisan test
./vendor/bin/pint
./vendor/bin/phpstan analyse

# frontend-admin/ e frontend-site/
npm run lint      # frontend-admin
npm run build      # ambos
```

## Convenções

Ver `CLAUDE.md` na raiz e os quatro documentos em `docs/` antes de qualquer alteração —
especialmente `docs/protecao-de-dados.md` antes de criar coluna com dado pessoal.
