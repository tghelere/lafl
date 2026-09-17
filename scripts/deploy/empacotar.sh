#!/usr/bin/env bash
#
# Gera o pacote de deploy do Lar Anália Franco a partir de uma árvore limpa.
#
# O que sai daqui é SÓ o que o servidor precisa para rodar. O servidor pertence à
# instituição; o código-fonte dos frontends, a documentação, os testes e as decisões de
# arquitetura são propriedade da Softhing e não são copiados para lá — ver
# docs/decisoes/0014-pacote-de-deploy-minimo.md e docs/deploy.md.
#
# Uso:
#   scripts/deploy/empacotar.sh [--saida DIR] [--permitir-arvore-suja] [--sem-tar]
#
# Variáveis de build do PAINEL (Vite grava o valor DENTRO do bundle, ver a ADR):
#   VITE_API_URL, VITE_SITE_URL, VITE_SESSION_IDLE_TIMEOUT_MINUTES
# O site (Nuxt) não precisa de nenhuma: as NUXT_PUBLIC_* são lidas em tempo de execução.

set -euo pipefail

REPO_RAIZ="$(git -C "$(dirname "${BASH_SOURCE[0]}")" rev-parse --show-toplevel)"
SAIDA="${REPO_RAIZ}/.pacotes"
PERMITIR_ARVORE_SUJA=0
GERAR_TAR=1

while [[ $# -gt 0 ]]; do
  case "$1" in
    --saida) SAIDA="$2"; shift 2 ;;
    --permitir-arvore-suja) PERMITIR_ARVORE_SUJA=1; shift ;;
    --sem-tar) GERAR_TAR=0; shift ;;
    -h|--help) sed -n '2,20p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *) echo "Opção desconhecida: $1" >&2; exit 2 ;;
  esac
done

passo() { printf '\n\033[1m==> %s\033[0m\n' "$1"; }
erro()  { printf '\033[31mERRO: %s\033[0m\n' "$1" >&2; }

# ---------------------------------------------------------------------------
# 1. Árvore limpa
# ---------------------------------------------------------------------------
# O pacote é montado a partir de `git archive`, não do diretório de trabalho: assim nada
# que não esteja versionado (arquivo de teste esquecido, .env local, dump de banco, saída
# de ferramenta) pode entrar por acidente. A checagem abaixo é sobre o que é PUBLICADO —
# empacotar com a árvore suja gera um pacote que não corresponde a commit nenhum e que
# ninguém consegue reproduzir depois.
passo 'Conferindo a árvore de trabalho'
if [[ -n "$(git -C "$REPO_RAIZ" status --porcelain)" ]]; then
  if [[ "$PERMITIR_ARVORE_SUJA" -eq 0 ]]; then
    erro 'a árvore de trabalho tem alteração não commitada.'
    echo '  O pacote sai de `git archive HEAD` — o que não está commitado não entra nele.' >&2
    echo '  Commite antes, ou use --permitir-arvore-suja para gerar assim mesmo (só para conferência local).' >&2
    exit 1
  fi
  echo '  Árvore suja, seguindo assim mesmo (--permitir-arvore-suja).'
  echo '  O pacote vai conter o conteúdo de HEAD, NÃO o que está no diretório de trabalho.'
fi

COMMIT="$(git -C "$REPO_RAIZ" rev-parse HEAD)"
COMMIT_CURTO="$(git -C "$REPO_RAIZ" rev-parse --short HEAD)"
CARIMBO="$(date -u +%Y%m%d-%H%M%S)"
NOME="lar-analia-franco-${CARIMBO}-${COMMIT_CURTO}"

TRABALHO="$(mktemp -d)"
trap 'rm -rf "$TRABALHO"' EXIT

FONTE="${TRABALHO}/fonte"
PACOTE="${TRABALHO}/${NOME}"
mkdir -p "$FONTE" "$PACOTE"

passo "Exportando HEAD (${COMMIT_CURTO}) para uma árvore limpa"
git -C "$REPO_RAIZ" archive HEAD | tar -x -C "$FONTE"

# ---------------------------------------------------------------------------
# 2. Builds
# ---------------------------------------------------------------------------
passo 'Backend — composer install --no-dev --optimize-autoloader'
(cd "${FONTE}/backend" && composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-progress)

passo 'Site público — npm ci && npm run build'
(cd "${FONTE}/frontend-site" && npm ci --no-audit --no-fund && npm run build)

passo 'Painel administrativo — npm ci && npm run build'
(cd "${FONTE}/frontend-admin" && npm ci --no-audit --no-fund && npm run build)

# ---------------------------------------------------------------------------
# 3. Montagem
# ---------------------------------------------------------------------------
# Três diretórios, um por processo do servidor (ver docs/deploy.md):
#   backend/  → PHP-FPM, worker e scheduler
#   site/     → `node site/server/index.mjs` (Nitro)
#   painel/   → arquivos estáticos servidos pelo Nginx
passo 'Montando o pacote'
cp -a "${FONTE}/backend" "${PACOTE}/backend"
cp -a "${FONTE}/frontend-site/.output" "${PACOTE}/site"
cp -a "${FONTE}/frontend-admin/dist" "${PACOTE}/painel"

# ---------------------------------------------------------------------------
# 4. Poda
# ---------------------------------------------------------------------------
passo 'Removendo o que não roda em servidor'

# Ferramenta de desenvolvimento do backend. `scripts/` sai inteiro de propósito: os scripts
# de concorrência rodam `migrate:fresh` (ver CLAUDE.md) e não têm o que fazer num servidor.
rm -rf "${PACOTE}/backend/tests" \
       "${PACOTE}/backend/scripts" \
       "${PACOTE}/backend/stubs" \
       "${PACOTE}/backend/phpunit.xml" \
       "${PACOTE}/backend/phpstan.neon" \
       "${PACOTE}/backend/pint.json" \
       "${PACOTE}/backend/.editorconfig" \
       "${PACOTE}/backend/.gitignore" \
       "${PACOTE}/backend/.gitattributes"

# Nenhum .env vai no pacote, em hipótese alguma: o .env de cada ambiente é criado no
# servidor e fica lá (ver docs/deploy.md). Os .env.testing/.env.e2e são versionados e
# entrariam junto pelo `git archive` se não fossem apagados aqui.
find "$PACOTE" \( -name '.env' -o -name '.env.*' \) -type f -delete

# Log e cache de uma máquina de desenvolvimento não têm o que fazer no servidor.
find "${PACOTE}/backend/storage/logs" -type f -delete 2>/dev/null || true
find "${PACOTE}/backend/bootstrap/cache" -type f ! -name '.gitignore' -delete 2>/dev/null || true

# Esqueleto de `storage/`. `git archive` não exporta diretório vazio, e `storage/logs/` não
# tem nenhum arquivo versionado dentro — o pacote saía SEM ele, e o Laravel só não quebrava
# porque o Monolog cria o diretório sozinho na primeira escrita. Depender disso é apostar que
# o usuário do PHP-FPM tem permissão de escrita no pai; criar aqui custa uma linha.
mkdir -p "${PACOTE}/backend/storage/logs" \
         "${PACOTE}/backend/storage/framework/cache/data" \
         "${PACOTE}/backend/storage/framework/sessions" \
         "${PACOTE}/backend/storage/framework/views" \
         "${PACOTE}/backend/storage/app/private" \
         "${PACOTE}/backend/storage/app/public" \
         "${PACOTE}/backend/bootstrap/cache"

# Só existe para a suíte Pest, que não vai no pacote.
rm -rf "${PACOTE}/backend/storage/framework/testing"

# Documentação e arquivo de editor, em qualquer nível — inclusive o README de cada pacote do
# vendor, que é documentação de terceiro que ninguém lê no servidor.
find "$PACOTE" \( -name '*.md' -o -name '*.markdown' \) -type f -delete
find "$PACOTE" \( -name '.DS_Store' -o -name 'Thumbs.db' -o -name '*.swp' \) -type f -delete
find "$PACOTE" \( -name '.idea' -o -name '.vscode' -o -name '.github' -o -name '.claude' \) -type d -prune -exec rm -rf {} +

# Source map não é executado por ninguém e só serve para reconstruir a estrutura do código
# no navegador de quem inspecionar o servidor da instituição.
find "$PACOTE" -name '*.map' -type f -delete

# O classmap otimizado foi gerado ANTES da poda e ainda aponta para arquivos que não existem
# mais. Sem este passo, `class_exists()` em cima de uma classe podada vira erro fatal em
# produção — e só em produção.
passo 'Regerando o autoload depois da poda'
(cd "${PACOTE}/backend" && composer dump-autoload --no-dev --optimize --no-interaction)

# ---------------------------------------------------------------------------
# 5. Verificação
# ---------------------------------------------------------------------------
# Esta é a parte que dá sentido ao script. A lista abaixo é a mesma da ADR, e o script FALHA
# se qualquer item aparecer dentro do pacote — de nada adianta a regra estar escrita se o
# pacote pode violá-la em silêncio.
passo 'Verificando o que não pode estar no pacote'

FALHAS=0

acusar() {
  local descricao="$1" encontrados="$2"

  if [[ -n "$encontrados" ]]; then
    erro "${descricao}:"
    echo "$encontrados" | sed "s|^${PACOTE}/|    |" >&2
    FALHAS=$((FALHAS + 1))
  else
    printf '  ok  %s\n' "$descricao"
  fi
}

# Vale para o pacote INTEIRO, dependência de terceiro incluída: nada disto tem uso em
# servidor e nada disto pode vazar para a máquina da instituição.
#
# Os argumentos vêm agrupados em \( ... \) porque `-o` tem precedência menor que a ação:
# `find P -name a -o -name b -print` imprimiria SÓ os `b`, e metade da verificação seria
# decorativa sem ninguém notar.
proibido_em_tudo() {
  local descricao="$1"; shift

  acusar "$descricao" "$(find "$PACOTE" \( "$@" \) -print 2>/dev/null | head -20)"
}

# Vale só para o código DESTE repositório. `backend/vendor/` e o `node_modules` que o Nitro
# embute ficam de fora de propósito: o Laravel carrega os `stubs/` do próprio framework em
# `make:*`, os polyfills do Symfony resolvem classe a partir de `Resources/stubs/`, e vários
# pacotes trazem o próprio `pint.json`/`phpstan.neon`. Podar isso quebraria a dependência
# para economizar bytes — a regra da ADR é sobre o que é NOSSO e não pertence ao servidor.
proibido_no_projeto() {
  local descricao="$1"; shift

  acusar "$descricao" "$(find "$PACOTE" \
    \( -path "${PACOTE}/backend/vendor" -o -path "${PACOTE}/site/server/node_modules" \) -prune \
    -o \( "$@" \) -print 2>/dev/null | head -20)"
}

proibido_em_tudo   'nenhum .md'                       -name '*.md' -o -name '*.markdown'
proibido_em_tudo   'nenhum .env'                      -name '.env' -o -name '.env.*'
proibido_em_tudo   'nenhum código-fonte Vue'          -name '*.vue'
proibido_em_tudo   'nenhum código-fonte TypeScript'   -name '*.ts' -o -name '*.tsx'
proibido_em_tudo   'nenhum source map'                -name '*.map'
proibido_no_projeto 'nenhuma config de build de front' -name 'nuxt.config.*' -o -name 'vite.config.*' -o -name 'tsconfig*.json' -o -name 'eslint.config.*'
proibido_no_projeto 'nenhuma ferramenta de teste/QA'   -name 'phpunit.xml' -o -name 'phpunit.xml.dist' -o -name 'phpstan.neon' -o -name 'pint.json' -o -name 'playwright.config.*'
proibido_no_projeto 'nenhum diretório do repositório'  -type d \( -name 'docs' -o -name 'e2e' -o -name 'docker' -o -name '.github' -o -name '.claude' -o -name '.git' -o -name 'stubs' -o -name 'tests' -o -name 'scripts' \)

# O contrário da lista de proibidos: o que PRECISA estar lá. Um pacote que passa na
# verificação de proibidos mas não tem o `artisan` não é um pacote bom, é um pacote vazio.
passo 'Verificando o que precisa estar no pacote'

exigido() {
  if [[ -e "${PACOTE}/$1" ]]; then
    printf '  ok  %s\n' "$1"
  else
    erro "faltando no pacote: $1"
    FALHAS=$((FALHAS + 1))
  fi
}

exigido 'backend/artisan'
exigido 'backend/public/index.php'
exigido 'backend/vendor/autoload.php'
exigido 'backend/app/Console/Commands/ImportInitialContent.php'
exigido 'backend/database/migrations'
exigido 'backend/database/seeders/RoleSeeder.php'
exigido 'backend/resources/views/vendor/mail'
exigido 'backend/storage/logs'
exigido 'backend/storage/framework/views'
exigido 'backend/bootstrap/cache'
exigido 'site/server/index.mjs'
exigido 'site/public/_nuxt'
exigido 'painel/index.html'
exigido 'painel/assets'

# Marca: o que o site serve de fato. Os dois frontends leem shared/brand/ em tempo de BUILD
# (ver frontend-site/nuxt.config.ts), então o pacote não carrega `shared/` — o que precisa
# estar servido já foi copiado para dentro do build. Se um dia isso deixar de ser verdade,
# é aqui que aparece.
exigido 'site/public/brand'
exigido 'site/public/favicon.svg'
exigido 'site/public/apple-touch-icon.png'
exigido 'painel/favicon.svg'

if [[ "$FALHAS" -gt 0 ]]; then
  erro "${FALHAS} verificação(ões) falharam — pacote NÃO gerado."
  exit 1
fi

# ---------------------------------------------------------------------------
# 6. Entrega
# ---------------------------------------------------------------------------
# Arquivo de procedência, lido por quem estiver olhando uma release no servidor e quiser
# saber de qual commit ela saiu. Texto puro, não .md, por causa da regra acima.
cat > "${PACOTE}/RELEASE" <<RELEASE
pacote:  ${NOME}
commit:  ${COMMIT}
gerado:  $(date -u +'%Y-%m-%dT%H:%M:%SZ') (UTC)
origem:  $(git -C "$REPO_RAIZ" rev-parse --abbrev-ref HEAD)
php:     $(php -r 'echo PHP_VERSION;' 2>/dev/null || echo 'desconhecida')
node:    $(node --version 2>/dev/null || echo 'desconhecida')
RELEASE

mkdir -p "$SAIDA"
rm -rf "${SAIDA:?}/${NOME}"
cp -a "$PACOTE" "${SAIDA}/${NOME}"

passo 'Pacote gerado'
printf '  diretório: %s\n' "${SAIDA}/${NOME}"
printf '  tamanho:   %s\n' "$(du -sh "${SAIDA}/${NOME}" | cut -f1)"
printf '  arquivos:  %s\n' "$(find "${SAIDA}/${NOME}" -type f | wc -l)"

if [[ "$GERAR_TAR" -eq 1 ]]; then
  tar -czf "${SAIDA}/${NOME}.tar.gz" -C "$(dirname "$PACOTE")" "$NOME"
  printf '  tarball:   %s (%s)\n' "${SAIDA}/${NOME}.tar.gz" "$(du -sh "${SAIDA}/${NOME}.tar.gz" | cut -f1)"
fi

printf '\n  Conteúdo (dois primeiros níveis):\n'
find "${SAIDA}/${NOME}" -maxdepth 2 -mindepth 1 | sed "s|^${SAIDA}/${NOME}/|    |" | sort
