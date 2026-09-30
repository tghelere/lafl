#!/usr/bin/env bash
#
# Leva as fotos (biblioteca de imagens + capa e galeria das páginas) do ambiente de
# DESENVOLVIMENTO local para um ambiente do servidor, sem deploy e sem tocar no texto das
# páginas nem em nenhuma outra tabela. Ver docs/deploy.md §10.2.
#
#   backend/scripts/midia/levar-fotos.sh --ambiente staging --host 2.25.223.146 \
#       [--chave ~/.ssh/laf-deploy] [--api https://api.homologacao-laf.softhing.com.br] \
#       [--site https://homologacao-laf.softhing.com.br] [--pacote /caminho/conteudo-AAAAMMDD-HHMMSS]
#
# A autenticação básica do site de homologação vai por variável, nunca por argumento (não fica
# no histórico do shell): SITE_AUTH='homologacao:SENHA'.
#
# Roda da raiz do repositório, com o docker compose de desenvolvimento no ar (é de onde sai o
# pacote, via `conteudo:exportar`, a menos que --pacote seja passado).
#
# Passos: exporta → rsync do pacote para /var/tmp do servidor → backup (pg_dump só de
# `media` e `page_images`, e a lista de pastas de mídia) → `midia:importar --simular` →
# `midia:importar` → contagem + `midia:importar --verificar` → uma URL de imagem pela API
# pública. Se qualquer passo depois da importação falhar, restaura o backup (as duas tabelas
# numa transação só, e apaga as pastas de mídia que não existiam antes).
#
# SSH: uma conexão só, reaproveitada (ControlMaster, opção de linha de comando — nada no
# ~/.ssh/config nem no servidor muda). Se ela falhar, o script para: nada de insistir, o
# fail2ban do servidor bane o IP.

set -euo pipefail

AMBIENTE=''
HOST=''
CHAVE="${HOME}/.ssh/laf-deploy"
API=''
SITE=''
PACOTE=''

while [[ $# -gt 0 ]]; do
    case "$1" in
        --ambiente) AMBIENTE="$2"; shift 2 ;;
        --host) HOST="$2"; shift 2 ;;
        --chave) CHAVE="$2"; shift 2 ;;
        --api) API="$2"; shift 2 ;;
        --site) SITE="$2"; shift 2 ;;
        --pacote) PACOTE="$2"; shift 2 ;;
        *) echo "Opção desconhecida: $1" >&2; exit 2 ;;
    esac
done

[[ "$AMBIENTE" =~ ^(staging|production)$ ]] || { echo 'Use --ambiente staging|production' >&2; exit 2; }
[[ -n "$HOST" ]] || { echo 'Use --host IP_DO_SERVIDOR (o que está no known_hosts)' >&2; exit 2; }

CARIMBO="$(date +%Y%m%d-%H%M%S)"
TEMP_LOCAL="$(mktemp -d)"
CONTROLE="${TEMP_LOCAL}/ssh-controle"
REMOTO_PACOTE="/var/tmp/laf-midia-${CARIMBO}"
REMOTO_BACKUP="/var/tmp/laf-midia-backup-${CARIMBO}"
SSH_OPCOES=(-o BatchMode=yes -o ConnectTimeout=15 -o IdentitiesOnly=yes -i "$CHAVE"
    -o ControlMaster=auto -o ControlPath="$CONTROLE" -o ControlPersist=300)

encerrar() {
    ssh "${SSH_OPCOES[@]}" -O exit "deploy@${HOST}" 2>/dev/null || true
    rm -rf "$TEMP_LOCAL"
}
trap encerrar EXIT

passo() { printf '\n==> %s\n' "$*"; }

# ---- 1. Pacote -------------------------------------------------------------------------
if [[ -z "$PACOTE" ]]; then
    passo 'Exportando o conteúdo do ambiente local (conteudo:exportar)'
    docker compose exec -T app sh -c 'rm -rf /tmp/laf-pacote-midia && php artisan conteudo:exportar /tmp/laf-pacote-midia'
    docker compose cp app:/tmp/laf-pacote-midia "${TEMP_LOCAL}/"
    PACOTE="$(ls -d "${TEMP_LOCAL}"/laf-pacote-midia/conteudo-*)"
fi
[[ -f "${PACOTE}/manifest.json" ]] || { echo "Não é um pacote: ${PACOTE}" >&2; exit 1; }

UUID_TESTE="$(python3 -c 'import json,sys; m=json.load(open(sys.argv[1])); print(m[0]["uuid"] if m else "")' "${PACOTE}/media.json")"
LARGURA_TESTE="$(python3 -c 'import json,sys; m=json.load(open(sys.argv[1])); print(m[0]["widths"][0] if m else "")' "${PACOTE}/media.json")"

# ---- 2. Conexão (uma tentativa) ----------------------------------------------------------
passo "Conectando em deploy@${HOST} (uma tentativa só)"
if ! ssh "${SSH_OPCOES[@]}" "deploy@${HOST}" true; then
    echo 'A conexão SSH falhou. Parando aqui, sem nova tentativa (fail2ban).' >&2
    exit 1
fi

passo "Enviando o pacote para ${REMOTO_PACOTE}"
rsync -a -e "ssh ${SSH_OPCOES[*]}" "${PACOTE}/" "deploy@${HOST}:${REMOTO_PACOTE}/"

# ---- 3. No servidor: backup, importação, verificação --------------------------------------
# O script remoto recebe um modo: `importar` ou `restaurar`.
REMOTO=$(cat <<'REMOTO_FIM'
set -euo pipefail
MODO="$1"; AMBIENTE="$2"; PACOTE="$3"; BACKUP="$4"
BASE="/var/www/laf/${AMBIENTE}"
cd "${BASE}/current/backend"
MIDIA="${BASE}/shared/storage/app/private/media"

env_valor() {
    grep -E "^$1=" "${BASE}/shared/.env" | tail -n1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//"
}
export PGHOST="$(env_valor DB_HOST)" PGPORT="$(env_valor DB_PORT)" PGDATABASE="$(env_valor DB_DATABASE)" \
       PGUSER="$(env_valor DB_USERNAME)" PGPASSWORD="$(env_valor DB_PASSWORD)"
sql() { psql -v ON_ERROR_STOP=1 -At -c "$1"; }

restaurar() {
    echo '!! Restaurando o backup de media e page_images'
    { echo 'DELETE FROM page_images; DELETE FROM media;'; pg_restore --data-only -f - "${BACKUP}/tabelas.dump"; } > "${BACKUP}/restaurar.sql"
    psql -v ON_ERROR_STOP=1 -q --single-transaction -f "${BACKUP}/restaurar.sql"
    # Só as pastas que esta execução criou (nome de uuid, ausentes da lista de antes).
    ls -1 "$MIDIA" 2>/dev/null | sort > "${BACKUP}/pastas-depois.txt" || true
    comm -13 "${BACKUP}/pastas-antes.txt" "${BACKUP}/pastas-depois.txt" \
        | grep -E '^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$' \
        | while read -r pasta; do rm -rf "${MIDIA:?}/${pasta}"; done
    echo "   media: $(sql 'SELECT count(*) FROM media') | page_images: $(sql 'SELECT count(*) FROM page_images')"
    echo '!! Backup restaurado.'
}

if [[ "$MODO" == restaurar ]]; then
    restaurar
    exit 0
fi

echo "==> Backup de media e page_images em ${BACKUP}"
install -d -m 700 "$BACKUP"
pg_dump --data-only --format=custom -t media -t page_images -f "${BACKUP}/tabelas.dump"
ls -1 "$MIDIA" 2>/dev/null | sort > "${BACKUP}/pastas-antes.txt" || true
MEDIA_ANTES="$(sql 'SELECT count(*) FROM media')"
VINCULOS_ANTES="$(sql 'SELECT count(*) FROM page_images')"
php8.5 -r 'foreach (json_decode(file_get_contents($argv[1]), true) as $m) { echo $m["uuid"], "\n"; }' "${PACOTE}/media.json" > "${BACKUP}/uuids.txt"
LISTA="$(paste -sd, "${BACKUP}/uuids.txt")"
JA_EXISTIAM="$(sql "SELECT count(*) FROM media WHERE uuid::text = ANY(string_to_array('${LISTA}', ','))")"
NO_PACOTE="$(wc -l < "${BACKUP}/uuids.txt")"
ESPERADO=$(( MEDIA_ANTES + NO_PACOTE - JA_EXISTIAM ))
echo "   antes: ${MEDIA_ANTES} imagem(ns), ${VINCULOS_ANTES} vínculo(s); no pacote: ${NO_PACOTE} (${JA_EXISTIAM} já existiam); esperado depois: ${ESPERADO}"

echo '==> Simulação'
php8.5 artisan midia:importar "$PACOTE" --simular

echo '==> Importação'
trap 'restaurar' ERR
php8.5 artisan midia:importar "$PACOTE"

echo '==> Verificação'
DEPOIS="$(sql 'SELECT count(*) FROM media')"
if [[ "$DEPOIS" != "$ESPERADO" ]]; then
    echo "!! Contagem de imagens: ${DEPOIS}, esperado ${ESPERADO}" >&2
    false
fi
echo "   contagem ok: ${DEPOIS} imagem(ns), $(sql 'SELECT count(*) FROM page_images') vínculo(s)"
php8.5 artisan midia:importar "$PACOTE" --verificar
echo "   arquivos no disco: $(find "$MIDIA" -type f | wc -l)"
echo '   vínculos por página:'
sql "SELECT '     ' || p.slug || ': ' || count(*) FROM page_images i JOIN pages p ON p.id = i.page_id GROUP BY p.slug ORDER BY p.slug"
trap - ERR
REMOTO_FIM
)

remoto() { ssh "${SSH_OPCOES[@]}" "deploy@${HOST}" bash -s -- "$@" <<< "$REMOTO"; }

passo "Backup, importação e verificação no servidor (${AMBIENTE})"
if ! remoto importar "$AMBIENTE" "$REMOTO_PACOTE" "$REMOTO_BACKUP"; then
    echo "Falhou no servidor (o backup foi restaurado se a importação chegou a começar). Backup em ${REMOTO_BACKUP}." >&2
    exit 1
fi

# ---- 4. Uma imagem pela URL pública -------------------------------------------------------
conferir_url() {
    local url="$1"; shift
    passo "Conferindo ${url}"
    local codigo
    codigo="$(curl -s -o /dev/null -w '%{http_code}' "$@" "$url" || true)"
    echo "   HTTP ${codigo}"
    if [[ "$codigo" != 200 ]]; then
        echo 'A imagem não respondeu 200. Restaurando o backup.' >&2
        remoto restaurar "$AMBIENTE" "$REMOTO_PACOTE" "$REMOTO_BACKUP"
        exit 1
    fi
}

if [[ -n "$UUID_TESTE" && -n "$API" ]]; then
    conferir_url "${API%/}/api/v1/public/media/${UUID_TESTE}/${LARGURA_TESTE}.webp"
fi
if [[ -n "$UUID_TESTE" && -n "$SITE" ]]; then
    AUTH=()
    [[ -n "${SITE_AUTH:-}" ]] && AUTH=(-u "$SITE_AUTH")
    conferir_url "${SITE%/}/midia/${UUID_TESTE}/${LARGURA_TESTE}.webp" "${AUTH[@]}"
fi

ssh "${SSH_OPCOES[@]}" "deploy@${HOST}" rm -rf "$REMOTO_PACOTE"

passo 'Pronto.'
echo "   Backup de antes da importação (apagar quando não for mais preciso): ${REMOTO_BACKUP}"
