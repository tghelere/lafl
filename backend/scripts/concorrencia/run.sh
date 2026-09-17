#!/usr/bin/env bash
# Reprodução da corrida do último super_admin — ver README.md nesta pasta antes de rodar.
#
# Uso:
#   ./run.sh papel   # cenário 1: dois super_admins removendo o papel um do outro
#   ./run.sh misto   # cenário 2: um remove papel, o outro desativa, ao mesmo tempo
set -u

DIR="$(cd "$(dirname "$0")" && pwd)"
CENARIO="${1:-}"

case "$CENARIO" in
  papel) P2_SCRIPT="p2-remove-papel.php" ;;
  misto) P2_SCRIPT="p2-desativa.php" ;;
  *)
    echo "Uso: $0 papel|misto" >&2
    exit 1
    ;;
esac

# Apaga os usuários de teste, não importa como o script termina — inclusive se a corrida
# falhar no meio ou for interrompida (Ctrl+C).
cleanup() {
  echo
  echo "==================== LIMPEZA ===================="
  php "$DIR/cleanup.php"
}
trap cleanup EXIT

echo "==================== SETUP (cenário: $CENARIO) ===================="
php "$DIR/setup.php" || exit 1

echo
echo "==================== CORRIDA ===================="
php "$DIR/p1.php" 2>&1 | sed 's/^/  /' &
P1=$!
php "$DIR/$P2_SCRIPT" 2>&1 | sed 's/^/  /' &
P2=$!

wait "$P1"
wait "$P2"

echo
echo "==================== ESTADO FINAL (antes da limpeza) ===================="
php "$DIR/final.php"
