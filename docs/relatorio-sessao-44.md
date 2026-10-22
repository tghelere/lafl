# Relatório da sessão 44 — CVE-2026-108082 em symfony/http-foundation

O workflow "Auditoria" da `main` estava vermelho por `composer audit`: advisory
PKSA-xmwy-rmx5-m7h4 / CVE-2026-108082 (ReDoS em `HeaderUtils::split()`, alcançável pelo
cabeçalho de requisição `Cache-Control`). Corrigido nas versões 8.1.7 em diante da linha 8.1.

## Versões

| Pacote | Antes | Depois |
|---|---|---|
| symfony/http-foundation | v8.1.4 | v8.1.9 |
| symfony/polyfill-mbstring (dependência transitiva) | v1.38.2 | v1.43.0 |

`composer update symfony/http-foundation --with-dependencies`. Nenhuma restrição do
`composer.json` foi alterada; só o `composer.lock` mudou.

## Verificações (backend)

| Verificação | Resultado |
|---|---|
| `composer audit` | passou — nenhum advisory |
| `pint --test` | passou |
| `phpstan analyse` (Larastan) | passou — 0 erros |
| `php artisan test` (Pest) | passou — 684/684 testes, 2768 asserções |

## Workflows (commit na `main`)

| Workflow | Resultado |
|---|---|
| Auditoria | success (14 s) |
| Deploy | success (6 min 24 s) |
