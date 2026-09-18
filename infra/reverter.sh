#!/usr/bin/env bash
#
# Volta um ambiente para uma release anterior, à mão.
#
# Roda NO SERVIDOR, como o usuário `deploy`. infra/publicar.sh já reverte sozinho quando a
# checagem de saúde falha; este script é para o outro caso — o deploy passou na checagem e o
# defeito apareceu depois, olhando a tela.
#
# Uso:
#   reverter.sh --ambiente staging                  # volta uma release
#   reverter.sh --ambiente staging --listar         # só mostra o que existe
#   reverter.sh --ambiente staging --para 20260918-142233-a1b2c3d
#
# A reversão devolve o CÓDIGO, não o BANCO. As migrations aplicadas continuam aplicadas.

set -euo pipefail

AMBIENTE=''
ALVO=''
LISTAR=0

while [[ $# -gt 0 ]]; do
  case "$1" in
    --ambiente) AMBIENTE="$2"; shift 2 ;;
    --para)     ALVO="$2"; shift 2 ;;
    --listar)   LISTAR=1; shift ;;
    -h|--help) sed -n '2,16p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *) echo "Opção desconhecida: $1" >&2; exit 2 ;;
  esac
done

passo() { printf '\n\033[1m==> %s\033[0m\n' "$1"; }
ok()    { printf '  ok  %s\n' "$1"; }
erro()  { printf '\033[31mERRO: %s\033[0m\n' "$1" >&2; }

[[ -n "$AMBIENTE" ]] || { erro 'falta --ambiente.'; exit 2; }

CONF="/etc/laf/${AMBIENTE}.conf"
[[ -f "$CONF" ]] || { erro "${CONF} não existe."; exit 1; }
# shellcheck source=/dev/null
. "$CONF"

RELEASES="${BASE}/releases"
ATUAL="${BASE}/current"
PHP="/usr/bin/php${VERSAO_PHP}"

mapfile -t existentes < <(find "$RELEASES" -mindepth 1 -maxdepth 1 -type d -printf '%f\n' | sort -r)
[[ ${#existentes[@]} -gt 0 ]] || { erro "nenhuma release em ${RELEASES}."; exit 1; }

NO_AR=''
[[ -L "$ATUAL" ]] && NO_AR="$(basename "$(readlink -f "$ATUAL")")"

if [[ "$LISTAR" -eq 1 ]]; then
  passo "Releases em ${AMBIENTE} (mais recente primeiro)"
  for release in "${existentes[@]}"; do
    marca='   '
    [[ "$release" == "$NO_AR" ]] && marca=' → '
    printf '%s%s' "$marca" "$release"
    if [[ -f "${RELEASES}/${release}/RELEASE" ]]; then
      printf '   %s' "$(grep -E '^commit:' "${RELEASES}/${release}/RELEASE" | awk '{print substr($2,1,7)}')"
    fi
    printf '\n'
  done
  exit 0
fi

if [[ -z "$ALVO" ]]; then
  # Sem --para: a release imediatamente anterior à que está no ar. Não é simplesmente "a
  # segunda da lista": se `current` já estiver numa release antiga (uma reversão anterior),
  # a segunda da lista seria uma MAIS NOVA que a atual, e "reverter" avançaria.
  anterior=''
  encontrou_atual=0
  for release in "${existentes[@]}"; do
    if [[ "$encontrou_atual" -eq 1 ]]; then
      anterior="$release"
      break
    fi
    [[ "$release" == "$NO_AR" ]] && encontrou_atual=1
  done
  [[ -n "$anterior" ]] || { erro 'não há release anterior à que está no ar.'; exit 1; }
  ALVO="$anterior"
fi

DESTINO="${RELEASES}/${ALVO}"
[[ -d "$DESTINO" ]] || { erro "release não encontrada: ${ALVO} (use --listar)"; exit 1; }
[[ "$ALVO" != "$NO_AR" ]] || { erro "${ALVO} já é a release no ar."; exit 1; }

passo "Revertendo ${AMBIENTE}: ${NO_AR:-nenhuma} → ${ALVO}"

# Mesma troca atômica de publicar.sh: `ln -sfn` sobre link existente apaga e recria, e o
# Nginx devolve 404 no intervalo. `mv -T` é renomeação, atômica.
ln -sfn "$DESTINO" "${BASE}/current.novo"
mv -Tf "${BASE}/current.novo" "$ATUAL"
ok "current -> ${ALVO}"

sudo systemctl reload "php${VERSAO_PHP}-fpm"
( cd "${ATUAL}/backend" && "$PHP" artisan queue:restart --no-interaction ) || true
sudo systemctl restart "laf-site@${AMBIENTE}"
sudo systemctl restart "laf-queue@${AMBIENTE}"
sudo systemctl restart "laf-scheduler@${AMBIENTE}"
ok 'serviços reiniciados'

passo 'Checagem de saúde'
falhas=0
for alvo_saude in "https://${HOST_API}/up" "http://127.0.0.1:${PORTA_SITE}/"; do
  codigo='000'
  for _ in 1 2 3 4 5 6 7 8 9 10; do
    codigo="$(curl -fsS -o /dev/null -w '%{http_code}' --max-time 10 "$alvo_saude" || echo '000')"
    if [[ "$codigo" == '200' ]]; then
      break
    fi
    sleep 3
  done
  if [[ "$codigo" == '200' ]]; then
    ok "${alvo_saude} → 200"
  else
    erro "${alvo_saude} → ${codigo}"
    falhas=$((falhas + 1))
  fi
done

if [[ "$falhas" -gt 0 ]]; then
  cat >&2 <<AVISO

  A release para onde se reverteu TAMBÉM não responde. Quase sempre é o banco: a reversão
  devolve o código, não o schema, e as migrations da release com defeito continuam
  aplicadas. Ver ${BASE}/shared/storage/logs/laravel.log e
  'journalctl -u laf-site@${AMBIENTE} -n 100 --no-pager'.
AVISO
  exit 1
fi

passo 'Revertido'
echo "  ${AMBIENTE} está servindo ${ALVO}."
echo '  Lembrete: o banco NÃO voltou. As migrations aplicadas continuam aplicadas.'
