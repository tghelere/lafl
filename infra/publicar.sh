#!/usr/bin/env bash
#
# Publica um pacote no servidor, com troca atômica e reversão automática.
#
# Roda NO SERVIDOR, como o usuário `deploy`. Quem o chama é o GitHub Actions
# (.github/workflows/deploy.yml), que antes envia o pacote por rsync — e também dá para
# chamar à mão, quando for preciso publicar sem passar pelo CI.
#
# Uso:
#   publicar.sh --ambiente staging --pacote /var/www/laf/staging/incoming/<nome>
#
#   --forcar-falha-de-saude   faz a checagem de saúde falhar de propósito, para exercitar a
#                             reversão automática sem quebrar nada de verdade
#
# O desenho: a release nova é montada INTEIRA ao lado da que está no ar, e só no fim o link
# `current` muda. Até a troca, o site continua servindo a release anterior; depois dela, se a
# checagem de saúde falhar, o link volta sozinho. O que este script não desfaz é migration —
# ver o aviso na seção 8.

set -euo pipefail

AMBIENTE=''
PACOTE=''
FORCAR_FALHA=0

while [[ $# -gt 0 ]]; do
  case "$1" in
    --ambiente) AMBIENTE="$2"; shift 2 ;;
    --pacote)   PACOTE="$2"; shift 2 ;;
    --forcar-falha-de-saude) FORCAR_FALHA=1; shift ;;
    -h|--help) sed -n '2,20p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *) echo "Opção desconhecida: $1" >&2; exit 2 ;;
  esac
done

passo()  { printf '\n\033[1m==> %s\033[0m\n' "$1"; }
ok()     { printf '  ok  %s\n' "$1"; }
aviso()  { printf '\033[33m  !   %s\033[0m\n' "$1"; }
erro()   { printf '\033[31mERRO: %s\033[0m\n' "$1" >&2; }

[[ -n "$AMBIENTE" ]] || { erro 'falta --ambiente.'; exit 2; }
[[ -n "$PACOTE" ]]   || { erro 'falta --pacote.'; exit 2; }

CONF="/etc/laf/${AMBIENTE}.conf"
[[ -f "$CONF" ]] || { erro "${CONF} não existe — rode infra/criar-ambiente.sh antes."; exit 1; }
# shellcheck source=/dev/null
. "$CONF"

COMPARTILHADO="${BASE}/shared"
RELEASES="${BASE}/releases"
ATUAL="${BASE}/current"
PHP="/usr/bin/php${VERSAO_PHP}"

# ---------------------------------------------------------------------------
# 1. Conferências antes de tocar em qualquer coisa
# ---------------------------------------------------------------------------
passo "Publicando em ${AMBIENTE}"

[[ -d "$PACOTE" ]] || { erro "pacote não encontrado: ${PACOTE}"; exit 1; }
for exigido in backend/artisan backend/public/index.php site/server/index.mjs painel/index.html; do
  [[ -e "${PACOTE}/${exigido}" ]] || { erro "pacote incompleto: falta ${exigido}"; exit 1; }
done
[[ -f "${COMPARTILHADO}/.env" ]] || { erro "${COMPARTILHADO}/.env não existe — rode criar-ambiente.sh."; exit 1; }
ok 'pacote e ambiente conferidos'

# A release anterior é o destino da reversão. Guardada ANTES de qualquer mudança, porque
# depois da troca `current` já aponta para a nova.
RELEASE_ANTERIOR=''
if [[ -L "$ATUAL" ]]; then
  RELEASE_ANTERIOR="$(readlink -f "$ATUAL")"
  ok "release no ar: $(basename "$RELEASE_ANTERIOR")"
else
  aviso 'não há release anterior — este é o primeiro deploy (não há para onde reverter)'
fi

CARIMBO="$(date +%Y%m%d-%H%M%S)"
COMMIT_CURTO='sem-commit'
if [[ -f "${PACOTE}/RELEASE" ]]; then
  COMMIT_CURTO="$(grep -E '^commit:' "${PACOTE}/RELEASE" | awk '{print substr($2,1,7)}')"
fi
NOVA="${RELEASES}/${CARIMBO}-${COMMIT_CURTO}"

# ---------------------------------------------------------------------------
# 2. Montagem da release, ao lado da que está no ar
# ---------------------------------------------------------------------------
passo "Montando ${CARIMBO}-${COMMIT_CURTO}"
mkdir -p "$RELEASES"
rm -rf "$NOVA"
cp -a "$PACOTE" "$NOVA"

# Qualquer falha daqui até a troca do link apaga a release nova e vai embora sem mexer no
# que está no ar. A reversão DEPOIS da troca é outra coisa, e está na seção 9.
trap 'erro "falha antes da troca — removendo ${NOVA}, nada mudou no ar."; rm -rf "$NOVA"' ERR

# ---------------------------------------------------------------------------
# 3. Ligações com o que é do ambiente, não da release
# ---------------------------------------------------------------------------
passo 'Ligando .env, storage e configuração do painel'
ln -sfn "${COMPARTILHADO}/.env" "${NOVA}/backend/.env"

# storage/ inteiro vem do shared: é onde ficam os PDFs de transparência enviados pelo
# painel, o único dado em disco que precisa sobreviver à troca de release.
rm -rf "${NOVA}/backend/storage"
ln -sfn "${COMPARTILHADO}/storage" "${NOVA}/backend/storage"

# O painel lê /config.js em tempo de execução (ADR 0015). Sem esta ligação ele cairia no
# valor gravado no bundle e falaria com a API de outro ambiente, sem sinal nenhum na tela.
ln -sfn "${COMPARTILHADO}/painel-config.js" "${NOVA}/painel/config.js"
ok 'ligado'

# ---------------------------------------------------------------------------
# 4. Migrations
# ---------------------------------------------------------------------------
# ANTES da troca, de propósito: se a migration falhar, `current` nem chegou a mudar e o que
# está no ar continua intacto. O preço é a janela em que o código VELHO roda contra o schema
# NOVO — é o motivo de as migrations deste projeto serem aditivas (ver docs/convencoes.md).
passo 'Migrations'
( cd "${NOVA}/backend" && "$PHP" artisan migrate --force --no-interaction )
ok 'aplicadas'

# ---------------------------------------------------------------------------
# 5. Caches do Laravel
# ---------------------------------------------------------------------------
# Também antes da troca: gerar o cache de config depois significaria servir requisição sem
# cache de config nenhum por alguns segundos. Os caches são da RELEASE (bootstrap/cache), não
# do shared — cada release tem o seu.
passo 'Caches'
( cd "${NOVA}/backend" \
  && "$PHP" artisan config:clear --no-interaction \
  && "$PHP" artisan config:cache --no-interaction \
  && "$PHP" artisan route:cache  --no-interaction \
  && "$PHP" artisan view:cache   --no-interaction )
ok 'config, rotas e views'

# ---------------------------------------------------------------------------
# 6. Permissões
# ---------------------------------------------------------------------------
passo 'Permissões'
chmod -R u=rwX,g=rX,o= "${NOVA}/backend"
# O Nginx (www-data) precisa ATRAVESSAR os diretórios até public/ e painel/ e LER o que
# serve. o=rX em vez de o= nesses dois caminhos, e nada além deles.
chmod -R a+rX "${NOVA}/painel" "${NOVA}/site" "${NOVA}/backend/public"
chmod a+x "$NOVA" "${NOVA}/backend"
ok 'ajustadas'

# ---------------------------------------------------------------------------
# 7. Troca atômica
# ---------------------------------------------------------------------------
# `ln -sfn` num link que já existe NÃO é atômico: ele apaga e recria, e existe um instante
# em que `current` não aponta para nada — bastante para o Nginx devolver 404. `mv -T` de um
# link temporário por cima do antigo é uma renomeação, que é atômica no mesmo sistema de
# arquivos: em nenhum momento `current` deixa de apontar para uma release válida.
passo 'Trocando o link current'
trap - ERR
ln -sfn "$NOVA" "${BASE}/current.novo"
mv -Tf "${BASE}/current.novo" "$ATUAL"
ok "current -> $(basename "$NOVA")"

# ---------------------------------------------------------------------------
# 8. Recarregar quem serve
# ---------------------------------------------------------------------------
recarregar_servicos() {
  # PHP-FPM: `reload` e não `restart`. O nginx resolve `$realpath_root` a cada requisição,
  # mas o opcache do FPM guarda o arquivo pelo caminho REAL da release — sem recarregar, o
  # código antigo continua servindo até o opcache expirar.
  sudo systemctl reload "php${VERSAO_PHP}-fpm"

  # queue:restart avisa os workers a saírem no fim do job atual, sem matar job em execução;
  # o restart do systemd logo abaixo é o que garante que eles voltem já na release nova
  # mesmo que estejam ociosos.
  ( cd "${ATUAL}/backend" && "$PHP" artisan queue:restart --no-interaction ) || true

  sudo systemctl restart "laf-site@${AMBIENTE}"
  sudo systemctl restart "laf-queue@${AMBIENTE}"
  sudo systemctl restart "laf-scheduler@${AMBIENTE}"
}

passo 'Recarregando serviços'
# Sem `set -e` aqui: um `systemctl restart` que falhe precisa CAIR NA CHECAGEM DE SAÚDE, que
# reverte. Abortar direto deixaria o link `current` já trocado apontando para uma release que
# ninguém conferiu — o pior dos dois mundos.
if recarregar_servicos; then
  ok 'php-fpm, site, fila e agendador'
else
  aviso 'algum serviço não recarregou — a checagem de saúde decide se reverte'
fi

# ---------------------------------------------------------------------------
# 9. Checagem de saúde — e reversão automática
# ---------------------------------------------------------------------------
# A API é conferida pela URL PÚBLICA: é o caminho inteiro (TLS, Nginx, FPM, .env, banco), e
# é o que o visitante percorre. O site é conferido pela porta de loopback do Nitro, e não
# pelo host público, por um motivo concreto: em homologação o host público exige autenticação
# básica, e uma checagem que precisasse da senha faria o segredo circular pelo log do deploy.
# O que interessa aqui é "o Nitro desta release responde", e isso a porta responde.
conferir_saude() {
  local falhas=0 codigo

  if [[ "$FORCAR_FALHA" -eq 1 ]]; then
    erro 'checagem de saúde falhada de propósito (--forcar-falha-de-saude)'
    return 1
  fi

  for tentativa in 1 2 3 4 5 6 7 8 9 10; do
    codigo="$(curl -fsS -o /dev/null -w '%{http_code}' --max-time 10 "https://${HOST_API}/up" || echo '000')"
    if [[ "$codigo" == '200' ]]; then
      break
    fi
    sleep 3
  done
  if [[ "$codigo" == '200' ]]; then
    ok "API https://${HOST_API}/up → 200"
  else
    erro "API https://${HOST_API}/up → ${codigo}"
    falhas=$((falhas + 1))
  fi

  for tentativa in 1 2 3 4 5 6 7 8 9 10; do
    codigo="$(curl -fsS -o /dev/null -w '%{http_code}' --max-time 10 "http://127.0.0.1:${PORTA_SITE}/" || echo '000')"
    if [[ "$codigo" == '200' ]]; then
      break
    fi
    sleep 3
  done
  if [[ "$codigo" == '200' ]]; then
    ok "site 127.0.0.1:${PORTA_SITE}/ → 200"
  else
    erro "site 127.0.0.1:${PORTA_SITE}/ → ${codigo}"
    falhas=$((falhas + 1))
  fi

  return "$falhas"
}

passo 'Checagem de saúde'
if conferir_saude; then
  ok 'release saudável'
else
  erro 'a release nova não passou na checagem.'

  if [[ -z "$RELEASE_ANTERIOR" || ! -d "$RELEASE_ANTERIOR" ]]; then
    erro 'não há release anterior para onde voltar — o ambiente está NO AR e QUEBRADO.'
    echo '  Primeiro deploy, ou releases anteriores já expurgadas. Veja o que aconteceu com:' >&2
    echo "    journalctl -u laf-site@${AMBIENTE} -n 100 --no-pager" >&2
    echo "    tail -n 100 ${COMPARTILHADO}/storage/logs/laravel.log" >&2
    exit 1
  fi

  passo "Revertendo para $(basename "$RELEASE_ANTERIOR")"
  ln -sfn "$RELEASE_ANTERIOR" "${BASE}/current.novo"
  mv -Tf "${BASE}/current.novo" "$ATUAL"
  recarregar_servicos

  # A release que falhou fica no disco, e é de propósito: é o que sobrou para investigar.
  # O expurgo da seção 10 não roda neste caminho.
  if conferir_saude; then
    erro "revertido para $(basename "$RELEASE_ANTERIOR"). A release ${CARIMBO}-${COMMIT_CURTO} ficou em disco para investigação."
  else
    erro 'a reversão TAMBÉM não passou na checagem — o ambiente precisa de olho humano AGORA.'
  fi

  cat >&2 <<'AVISO'

  ATENÇÃO: a reversão devolve o CÓDIGO, não o BANCO. As migrations aplicadas no passo 4
  continuam aplicadas — é por isso que migration deste projeto é aditiva e reversível (ver
  CLAUDE.md, regra 9). Se a migration desta release removeu ou renomeou coluna, a release
  anterior pode não funcionar com o schema novo, e aí o caminho é restaurar o backup.
AVISO
  exit 1
fi

# ---------------------------------------------------------------------------
# 10. Expurgo das releases antigas
# ---------------------------------------------------------------------------
# Só depois da checagem passar: expurgar antes é apagar o destino da reversão.
passo "Mantendo as ${RELEASES_MANTIDAS} últimas releases"
mapfile -t antigas < <(find "$RELEASES" -mindepth 1 -maxdepth 1 -type d -printf '%f\n' \
  | sort -r | tail -n "+$((RELEASES_MANTIDAS + 1))")
for antiga in "${antigas[@]:-}"; do
  [[ -n "$antiga" ]] || continue
  # Cinto e suspensório: nunca apagar a que está no ar, aconteça o que acontecer com a
  # ordenação por nome.
  [[ "${RELEASES}/${antiga}" == "$(readlink -f "$ATUAL")" ]] && continue
  rm -rf "${RELEASES:?}/${antiga}"
  ok "removida ${antiga}"
done

passo 'Publicado'
cat <<RESUMO
  ambiente  ${AMBIENTE}
  release   $(basename "$NOVA")
  site      https://${HOST_SITE}
  api       https://${HOST_API}/up
  painel    https://${HOST_PAINEL}
RESUMO
