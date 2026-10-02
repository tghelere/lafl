#!/usr/bin/env bash
# Decide quais jobs do CI precisam rodar, comparando o commit com a última base TESTADA.
# Escreve backend/admin/site/e2e=true|false em $GITHUB_OUTPUT.
#
# Base da comparação:
#   - pull_request: o merge-base com a branch de destino.
#   - push na main: o commit da última execução de Deploy BEM-SUCEDIDA disparada por push, e
#     não `github.event.before`. Um deploy bem-sucedido significa que todo o código daquele
#     commit passou no CI (por indução, mesmo que alguns jobs tenham sido pulados), então só
#     o que mudou desde ele precisa ser testado. Com `before`, um push vermelho em backend/
#     seguido de um push só em docs/frontend deixaria o backend sem teste para sempre.
#   - qualquer outra coisa (tag, execução manual) ou base inalcançável: tudo roda.
#
# Equivalente local ao dorny/paths-filter, sem dependência de terceiros no caminho do deploy.
set -euo pipefail

EVENTO="${EVENTO:-}"
base=''

if [ "$EVENTO" = 'pull_request' ]; then
  base="$(git merge-base "origin/${BASE_REF}" HEAD || true)"
elif [ "$EVENTO" = 'push' ] && [ "${REF_TYPE:-}" = 'branch' ]; then
  base="$(gh run list --workflow deploy.yml --branch "${REF_NAME}" --event push \
            --status success --limit 1 --json headSha --jq '.[0].headSha // empty' || true)"
fi

todos=false
if [ -z "$base" ] || ! git cat-file -e "${base}^{commit}" 2>/dev/null; then
  todos=true
  echo "Sem base testada alcançável: tudo roda."
else
  arquivos="$(git diff --name-only "$base" HEAD)"
  echo "Base: $base"
  echo "Arquivos alterados desde a base:"
  echo "$arquivos" | sed 's/^/  /'
fi

# Mudar o próprio CI (ou este script) revalida tudo: é ele quem define o que "passou" quer dizer.
# Mudar só scripts/workflow de deploy não toca em nenhum filtro abaixo.
casa() { [ "$todos" = true ] || echo "$arquivos" | grep -Eq "$1"; }

proprio='^(\.github/workflows/ci\.yml|scripts/ci/detectar-mudancas\.sh)$'
backend=false; admin=false; site=false; e2e=false
casa "^backend/|$proprio"        && backend=true
casa "^frontend-admin/|$proprio" && admin=true
casa "^frontend-site/|$proprio"  && site=true
{ [ "$backend" = true ] || [ "$admin" = true ] || [ "$site" = true ] || casa '^e2e/'; } && e2e=true

echo "backend=$backend admin=$admin site=$site e2e=$e2e"
{
  echo "backend=$backend"
  echo "admin=$admin"
  echo "site=$site"
  echo "e2e=$e2e"
} >> "${GITHUB_OUTPUT:-/dev/stdout}"
