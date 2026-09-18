#!/usr/bin/env bash
#
# Backup do banco de um ambiente. Instalado em /usr/local/bin/laf-backup-banco por
# infra/criar-ambiente.sh e disparado pelo timer laf-backup@<ambiente>.timer.
#
# Uso: laf-backup-banco <ambiente>
#
# O dump guarda o dado PESSOAL JÁ CIFRADO — o que está no banco é texto cifrado, e a chave
# que o abre está no .env, não aqui. É por isso que docs/protecao-de-dados.md exige que as
# chaves fiquem em cofre de senhas, em lugar DISTINTO deste diretório: quem pegar os dois
# juntos pega tudo; quem pegar só um não pega nada.
#
# Restauração (destrutiva — confira o ambiente duas vezes antes):
#   systemctl stop laf-queue@<amb> laf-scheduler@<amb>
#   gunzip -c /var/backups/laf/<amb>/<arquivo>.sql.gz | sudo -u postgres psql -d <banco>
#   systemctl start laf-queue@<amb> laf-scheduler@<amb>
#
# Backup nunca restaurado não é backup: a restauração precisa ser testada pelo menos uma vez
# (ver docs/deploy.md §9).

set -euo pipefail

AMBIENTE="${1:-}"
[[ -n "$AMBIENTE" ]] || { echo 'Uso: laf-backup-banco <ambiente>' >&2; exit 2; }

CONF="/etc/laf/${AMBIENTE}.conf"
[[ -f "$CONF" ]] || { echo "ERRO: ${CONF} não existe — ambiente não criado." >&2; exit 1; }
# shellcheck source=/dev/null
. "$CONF"

DESTINO="/var/backups/laf/${AMBIENTE}"
install -d -m 750 -o root -g root "$DESTINO"

ARQUIVO="${DESTINO}/${BANCO}-$(date +%Y%m%d-%H%M%S).sql.gz"

# Escreve num arquivo temporário e só depois renomeia: um dump interrompido no meio (disco
# cheio, máquina reiniciada) deixaria um .sql.gz truncado com nome de backup válido, e a
# expurgação abaixo apagaria um backup bom para manter esse.
TEMPORARIO="${ARQUIVO}.parcial"
trap 'rm -f "$TEMPORARIO"' EXIT

sudo -u postgres pg_dump --format=plain --no-owner --no-privileges "$BANCO" | gzip -9 > "$TEMPORARIO"
# `gzip -t` confirma que o arquivo fecha: pipeline com `set -o pipefail` já pega erro do
# pg_dump, mas não pega dump que saiu vazio por outro motivo.
gzip -t "$TEMPORARIO"
[[ -s "$TEMPORARIO" ]] || { echo 'ERRO: dump vazio.' >&2; exit 1; }

mv "$TEMPORARIO" "$ARQUIVO"
chmod 600 "$ARQUIVO"
trap - EXIT

# Retenção. -mtime +N apaga o que tem MAIS de N dias; a política do ambiente está em
# /etc/laf/<ambiente>.conf (7 dias em homologação; produção precisa de política própria,
# decidida com a instituição — ver docs/deploy.md §9).
find "$DESTINO" -maxdepth 1 -name "${BANCO}-*.sql.gz" -type f -mtime "+${RETENCAO_BACKUP_DIAS}" -delete
find "$DESTINO" -maxdepth 1 -name '*.parcial' -type f -mtime +1 -delete

echo "backup: ${ARQUIVO} ($(du -h "$ARQUIVO" | cut -f1)); mantidos $(find "$DESTINO" -maxdepth 1 -name "${BANCO}-*.sql.gz" | wc -l) arquivo(s)"
