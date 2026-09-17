> **Modelo recomendado: Sonnet**

# 05 — Correções de código apontadas na revisão

Leia `docs/tarefas/README.md`. Três defeitos, cada um com teste que falha antes da correção.

## Etapa 1 — Link do e-mail de notificação quebrado

`App\Actions\Forms\NotifyFormSubmissionReceived` monta
`{ADMIN_BASE_URL}/{slug}/{uuid}` (ex.: `/pickup-requests/…`), mas a rota do painel é
`/admin/:resource/:uuid`. Corrigir sem mudar `ADMIN_BASE_URL` (o link de definição de senha usa
a mesma base na raiz, `/definir-senha`). Teste Pest que afirme o caminho exato.

## Etapa 2 — Painel sem página de "não encontrado"

URL desconhecida no painel renderiza tela em branco (não há rota coringa), e um `:resource`
inexistente em `/admin/:resource` cai no estado `unmapped`. Criar uma tela de "Página não
encontrada" dentro do layout (para autenticado) com link para o Início; recurso fora do mapa de
acesso também leva a ela. E2e cobrindo as duas URLs.

## Etapa 3 — 500 em vez de 401 na API

O `laravel.log` registra `Route [login] not defined`: requisição não autenticada à API sem
`Accept: application/json` (navegador, curl, robô) faz o middleware de autenticação tentar
redirecionar para uma rota `login` inexistente. Reproduzir com
`curl -i http://localhost:8000/api/v1/users`. Corrigir em `bootstrap/app.php` (ex.:
`redirectGuestsTo` devolvendo `null`, deixando o `shouldRenderJsonWhen` responder 401 JSON).
Teste Pest sem cabeçalho `Accept` esperando 401 JSON.

## Etapa 4 — Comentários desatualizados

`config/forms.php` diz que a tela do painel e a página de política de privacidade não existem.
Corrigir esses e qualquer outro comentário que afirme inexistência de algo que já existe
(`grep -rniE "ainda não existe|não existe ainda|quando existir"` em `backend/app`,
`backend/config`, `frontend-site`, `frontend-admin/src`).

## Verificação

Pest, Pint, Larastan, lint e build do painel, e2e completo.
