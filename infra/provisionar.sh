#!/usr/bin/env bash
#
# Provisionamento do servidor do Lar Anália Franco — idempotente.
#
# Roda COMO ROOT, dentro da VPS, via SSH. Instala e configura a base comum aos dois
# ambientes (staging e production): Nginx, PHP-FPM, Node, PostgreSQL, Redis, Certbot e a
# segurança básica da máquina. NÃO cria ambiente nenhum — isso é `criar-ambiente.sh`.
#
# Idempotente de propósito: rodar de novo depois de mexer em alguma coisa à mão devolve o
# servidor ao estado descrito aqui, sem quebrar o que já está no ar. É o que faz deste
# arquivo, e não da memória de quem provisionou, a descrição do servidor.
#
# Uso, na ordem:
#
#   1)  ./provisionar.sh --chave-publica       "ssh-ed25519 AAAA... deploy@laf" \
#                          --chave-publica-admin "ssh-ed25519 AAAA... voce@sua-maquina"
#       Instala tudo e cria os DOIS usuários (sem senha, só com chave).
#
#   2)  De OUTRA máquina: ssh deploy@IP e ssh sysadmin@IP  — os dois precisam funcionar.
#
#   3)  ./provisionar.sh --trancar-ssh
#       Só depois do passo 2: desativa login de root e senha por SSH.
#
# O passo 3 é separado de propósito. Desativar senha e root antes de confirmar que a chave
# do usuário novo funciona é trancar a porta com a chave do lado de dentro — e, numa VPS,
# recuperar isso depende do console da hospedagem.
#
# Por que DOIS usuários. `deploy` carrega a chave que vive num segredo do GitHub, e por isso
# tem `sudo` restrito a uma lista fechada de recarregamentos: sudo irrestrito ali
# transformaria qualquer vazamento daquele segredo em root na máquina da instituição. Mas
# com o login de root desativado alguém precisa continuar podendo administrar o servidor —
# rodar `criar-ambiente.sh`, reprovisionar, investigar. Esse alguém é `sysadmin`, com sudo
# completo e só a chave pessoal de quem administra. Um usuário só não dá conta dos dois
# papéis sem desfazer um dos dois.
#
# As versões abaixo são as mesmas do docker-compose.yml e do .github/workflows/ci.yml.
# Divergir aqui reabre o buraco que fez o projeto abandonar o SQLite: "verde no CI" só
# significa alguma coisa quando o CI roda contra o que o servidor roda. Ver docs/deploy.md §1.

set -euo pipefail

VERSAO_PHP='8.5'
VERSAO_NODE='24'
VERSAO_POSTGRES='16'

EXTENSOES_PHP=(pgsql mbstring intl gd bcmath zip redis curl xml)

USUARIO_DEPLOY='deploy'
# `sysadmin`, e não `admin`: o Ubuntu já traz um grupo `admin` (legado) e o /etc/sudoers da
# distribuição dá `%admin ALL=(ALL) ALL` a ele. Um usuário chamado `admin` colide na criação
# do grupo primário e passaria a ter sudo por um caminho que não é o nosso — duas surpresas
# num lugar onde a regra precisa ser exatamente a que está escrita aqui.
USUARIO_ADMIN='sysadmin'
RAIZ='/var/www/laf'

CHAVE_PUBLICA=''
CHAVE_PUBLICA_ADMIN=''
TRANCAR_SSH=0
EU_CONFIRMO=0

while [[ $# -gt 0 ]]; do
  case "$1" in
    --chave-publica) CHAVE_PUBLICA="$2"; shift 2 ;;
    --chave-publica-arquivo) CHAVE_PUBLICA="$(cat "$2")"; shift 2 ;;
    --chave-publica-admin) CHAVE_PUBLICA_ADMIN="$2"; shift 2 ;;
    --chave-publica-admin-arquivo) CHAVE_PUBLICA_ADMIN="$(cat "$2")"; shift 2 ;;
    --trancar-ssh) TRANCAR_SSH=1; shift ;;
    --eu-confirmo) EU_CONFIRMO=1; shift ;;
    -h|--help) sed -n '2,40p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *) echo "Opção desconhecida: $1" >&2; exit 2 ;;
  esac
done

passo()  { printf '\n\033[1m==> %s\033[0m\n' "$1"; }
ok()     { printf '  ok  %s\n' "$1"; }
aviso()  { printf '\033[33m  !   %s\033[0m\n' "$1"; }
erro()   { printf '\033[31mERRO: %s\033[0m\n' "$1" >&2; }

[[ "$(id -u)" -eq 0 ]] || { erro 'este script roda como root.'; exit 1; }

# ---------------------------------------------------------------------------
# --trancar-ssh: segunda passada, depois de confirmado o acesso por chave
# ---------------------------------------------------------------------------
if [[ "$TRANCAR_SSH" -eq 1 ]]; then
  passo 'Desativando login de root e senha por SSH'

  # Os DOIS usuários precisam de chave antes de root sair de cena. Trancar com só o
  # `deploy` no ar deixaria a máquina sem administrador: o sudo dele é uma lista fechada de
  # recarregamentos, e nada em `criar-ambiente.sh` ou num reprovisionamento cabe ali.
  for usuario in "$USUARIO_DEPLOY" "$USUARIO_ADMIN"; do
    autorizadas="/home/${usuario}/.ssh/authorized_keys"
    if [[ ! -s "$autorizadas" ]]; then
      erro "${autorizadas} não existe ou está vazio — o usuário ${usuario} não tem chave nenhuma."
      echo '  Rode primeiro a passada normal, com --chave-publica e --chave-publica-admin.' >&2
      exit 1
    fi
  done

  # Sem sudo completo, `sysadmin` não substitui o root que está prestes a ser desativado.
  if ! id -nG "$USUARIO_ADMIN" | grep -qw sudo; then
    erro "${USUARIO_ADMIN} não está no grupo sudo — desativar root deixaria a máquina sem administrador."
    exit 1
  fi

  # A confirmação de que a chave FUNCIONA não pode ser deduzida de dentro da máquina: o
  # arquivo estar lá não prova que o cliente consegue entrar com ela. Ou existe uma sessão
  # SSH do `sysadmin` aberta agora (prova viva), ou alguém confirma explicitamente. É o
  # `sysadmin` que importa aqui: é ele que herda o papel do root.
  if ! who | grep -qE "^${USUARIO_ADMIN}\b" && [[ "$EU_CONFIRMO" -eq 0 ]]; then
    erro "nenhuma sessão de ${USUARIO_ADMIN} aberta — não dá para confirmar daqui que a chave funciona."
    echo "  Abra 'ssh ${USUARIO_ADMIN}@IP' em outro terminal e rode de novo," >&2
    echo '  ou passe --eu-confirmo se você JÁ testou e sabe que funciona.' >&2
    exit 1
  fi

  # Arquivo 00-*: no OpenSSH vale o PRIMEIRO valor obtido para cada palavra-chave, não o
  # último. O sshd_config da distro faz `Include /etc/ssh/sshd_config.d/*.conf` no topo e o
  # cloud-init costuma deixar ali um 50-cloud-init.conf com `PasswordAuthentication yes`.
  # Um 99-*.conf nosso PERDERIA para ele, em silêncio, e o servidor continuaria aceitando
  # senha com o script dizendo que trancou.
  cat > /etc/ssh/sshd_config.d/00-laf-seguranca.conf <<'CONF'
# Lar Anália Franco — ver infra/provisionar.sh. Prefixo 00 de propósito: no OpenSSH o
# primeiro valor obtido vence, então este arquivo precisa ser lido antes do 50-cloud-init.
PermitRootLogin no
PasswordAuthentication no
KbdInteractiveAuthentication no
PermitEmptyPasswords no
CONF
  chmod 644 /etc/ssh/sshd_config.d/00-laf-seguranca.conf

  # `sshd -t` antes de recarregar: configuração inválida + restart = servidor sem SSH.
  sshd -t
  # reload, nunca restart: reload não derruba as sessões abertas. Se alguma coisa der
  # errado na configuração nova, a sessão atual continua sendo o caminho de volta.
  systemctl reload ssh 2>/dev/null || systemctl reload sshd
  ok 'root e senha desativados (sessões abertas preservadas)'

  passo 'Conferindo o que ficou valendo'
  sshd -T | grep -E '^(permitrootlogin|passwordauthentication|kbdinteractiveauthentication) '
  exit 0
fi

# ---------------------------------------------------------------------------
# 1. Sistema
# ---------------------------------------------------------------------------
passo 'Conferindo a distribuição'
. /etc/os-release
if [[ "${ID:-}" != 'ubuntu' ]]; then
  erro "este script foi escrito para Ubuntu; aqui é ${PRETTY_NAME:-desconhecido}."
  echo '  Os nomes de pacote, o PPA do PHP e a unidade do SSH mudam em outra distribuição.' >&2
  exit 1
fi
ok "${PRETTY_NAME}"

export DEBIAN_FRONTEND=noninteractive

passo 'Pacotes base'
apt-get update -qq
apt-get install -y -qq \
  ca-certificates curl gnupg lsb-release software-properties-common \
  ufw fail2ban unattended-upgrades rsync git jq acl apache2-utils
ok 'utilitários'

# ---------------------------------------------------------------------------
# 2. PHP-FPM
# ---------------------------------------------------------------------------
# O Ubuntu 24.04 traz PHP 8.3; o projeto exige 8.5 (o composer.lock foi resolvido em 8.5 e
# trava uma dependência em ">= 8.4.1"). O PPA do ondrej é a origem usada por praticamente
# todo servidor Debian/Ubuntu com PHP fora da versão da distro.
passo "PHP ${VERSAO_PHP} (FPM)"
# `compgen -G` e não `[[ -f padrao* ]]`: dentro de [[ ]] o glob NÃO é expandido, o teste
# compararia com a string literal e o PPA seria adicionado de novo a cada execução.
if ! compgen -G '/etc/apt/sources.list.d/ondrej-ubuntu-php-*' >/dev/null; then
  add-apt-repository -y ppa:ondrej/php
  apt-get update -qq
fi

pacotes_php=("php${VERSAO_PHP}-fpm" "php${VERSAO_PHP}-cli")
for ext in "${EXTENSOES_PHP[@]}"; do
  pacotes_php+=("php${VERSAO_PHP}-${ext}")
done
apt-get install -y -qq "${pacotes_php[@]}"

# `exif` e `pdo_pgsql` não têm pacote próprio: vêm dentro de php-cli/php-common e de
# php-pgsql. Conferir pela extensão carregada, não pelo nome do pacote — é a lista de
# docs/deploy.md §1 e do docker/php/Dockerfile que precisa bater.
passo 'Conferindo as extensões exigidas'
faltando=()
for ext in pdo_pgsql pgsql mbstring intl exif gd bcmath zip redis; do
  if "php${VERSAO_PHP}" -m | grep -qix "$ext"; then
    ok "$ext"
  else
    faltando+=("$ext")
  fi
done
if [[ ${#faltando[@]} -gt 0 ]]; then
  erro "extensões PHP faltando: ${faltando[*]}"
  exit 1
fi

# Configuração de servidor, separada do php.ini da distro para sobreviver a upgrade de
# pacote. Upload de 20M por causa dos PDFs de transparência enviados pelo painel.
cat > "/etc/php/${VERSAO_PHP}/fpm/conf.d/99-laf.ini" <<'INI'
; Lar Anália Franco — ver infra/provisionar.sh
expose_php = Off
upload_max_filesize = 20M
post_max_size = 21M
memory_limit = 256M
max_execution_time = 60
date.timezone = America/Sao_Paulo
INI
cp "/etc/php/${VERSAO_PHP}/fpm/conf.d/99-laf.ini" "/etc/php/${VERSAO_PHP}/cli/conf.d/99-laf.ini"
systemctl enable --now "php${VERSAO_PHP}-fpm" >/dev/null
ok "php${VERSAO_PHP}-fpm ativo"

# ---------------------------------------------------------------------------
# 3. Node
# ---------------------------------------------------------------------------
# Só para rodar o Nitro do site (`node site/server/index.mjs`). Nada é buildado aqui — o
# pacote chega pronto do CI (ver docs/decisoes/0014-pacote-de-deploy-minimo.md).
passo "Node ${VERSAO_NODE}"
if ! command -v node >/dev/null || [[ "$(node --version)" != v${VERSAO_NODE}.* ]]; then
  curl -fsSL "https://deb.nodesource.com/setup_${VERSAO_NODE}.x" | bash -
  apt-get install -y -qq nodejs
fi
ok "$(node --version)"

# ---------------------------------------------------------------------------
# 4. PostgreSQL
# ---------------------------------------------------------------------------
passo "PostgreSQL ${VERSAO_POSTGRES}"
apt-get install -y -qq postgresql postgresql-client

instalada="$(psql --version | grep -oE '[0-9]+' | head -1)"
if [[ "$instalada" != "$VERSAO_POSTGRES" ]]; then
  erro "PostgreSQL ${instalada} instalado, mas o projeto roda em ${VERSAO_POSTGRES} (docker-compose e CI)."
  echo '  Ver docs/deploy.md §1 — divergir de versão principal aqui é o mesmo erro do SQLite.' >&2
  exit 1
fi

# Escutando SÓ em localhost. É o padrão do pacote Debian, mas o padrão de hoje não é
# garantia nenhuma: escrever explicitamente é o que faz a regra sobreviver a um upgrade.
conf_pg="/etc/postgresql/${VERSAO_POSTGRES}/main/conf.d/99-laf.conf"
mkdir -p "$(dirname "$conf_pg")"
cat > "$conf_pg" <<'CONF'
# Lar Anália Franco — ver infra/provisionar.sh
listen_addresses = 'localhost'
CONF
systemctl enable --now postgresql >/dev/null
systemctl reload postgresql
ok "postgresql ${instalada}, só em localhost"

# ---------------------------------------------------------------------------
# 5. Redis
# ---------------------------------------------------------------------------
passo 'Redis'
apt-get install -y -qq redis-server

# O pacote do Debian/Ubuntu não tem diretório de conf.d — o redis.conf é um arquivo só. O
# `include` no FIM do arquivo é o que garante que o nosso valor vença: no Redis, ao
# contrário do OpenSSH, vale a ÚLTIMA diretiva lida.
mkdir -p /etc/redis/redis.conf.d
cat > /etc/redis/redis.conf.d/99-laf.conf <<'CONF'
# Lar Anália Franco — ver infra/provisionar.sh
bind 127.0.0.1 -::1
protected-mode yes
CONF
chown redis:redis /etc/redis/redis.conf.d/99-laf.conf
grep -q '^include /etc/redis/redis.conf.d/99-laf.conf$' /etc/redis/redis.conf \
  || printf '\n# Lar Anália Franco — ver infra/provisionar.sh\ninclude /etc/redis/redis.conf.d/99-laf.conf\n' >> /etc/redis/redis.conf
systemctl enable --now redis-server >/dev/null
systemctl restart redis-server
ok "redis $(redis-server --version | grep -oE 'v=[0-9.]+' | cut -d= -f2), só em localhost"

# ---------------------------------------------------------------------------
# 6. Nginx e Certbot
# ---------------------------------------------------------------------------
passo 'Nginx e Certbot'
apt-get install -y -qq nginx certbot python3-certbot-nginx
rm -f /etc/nginx/sites-enabled/default

# Cabeçalhos e limites comuns aos três hosts de cada ambiente, num arquivo só: repetir
# isso em seis server{} é garantir que um dia um deles fique para trás.
cat > /etc/nginx/snippets/laf-comum.conf <<'CONF'
# Lar Anália Franco — ver infra/provisionar.sh e infra/modelos/nginx-*.conf
server_tokens off;
client_max_body_size 20M;

add_header X-Content-Type-Options   "nosniff"          always;
add_header X-Frame-Options          "SAMEORIGIN"       always;
add_header Referrer-Policy          "strict-origin-when-cross-origin" always;
CONF

# TLS num snippet NOSSO, e não nos arquivos que o plugin nginx do certbot instala em
# /etc/letsencrypt: aqueles só aparecem depois de uma execução com `--nginx`, e aqui o
# certbot roda com `--webroot` justamente para não tocar nos arquivos de site.
cat > /etc/nginx/snippets/laf-tls.conf <<'CONF'
# Lar Anália Franco — ver infra/provisionar.sh
ssl_protocols TLSv1.2 TLSv1.3;
ssl_prefer_server_ciphers off;
ssl_session_cache shared:LAF_TLS:10m;
ssl_session_timeout 1d;
ssl_session_tickets off;

# Sem ssl_dhparam de propósito: as suítes em uso são todas ECDHE, e o parâmetro só vale
# para DHE. Gerar um arquivo de 4096 bits para nada custa minutos no provisionamento.

# HSTS no snippet de TLS, e não em cada server{}: um host que ficasse de fora passaria a
# aceitar downgrade para HTTP sem ninguém notar. `always` para valer também em resposta de
# erro — é justamente no 5xx que o navegador não pode esquecer o HTTPS.
#
# SEM `includeSubDomains`: em produção este cabeçalho sai do domínio raiz, e a diretiva
# valeria para TODO subdomínio dele — inclusive os que a instituição venha a usar para
# outra coisa e que não estejam em HTTPS. O navegador guarda isso por um ano e não há como
# desfazer do lado dele. Cada host do sistema recebe o cabeçalho por si.
add_header Strict-Transport-Security "max-age=31536000" always;
CONF

# Webroot único do ACME, compartilhado pelos seis hosts. `certbot --webroot` em vez de
# `certbot --nginx` de propósito: o plugin nginx REESCREVE o arquivo do site, e aqui os
# arquivos de site são gerados a partir de infra/modelos/nginx-*.conf — deixar o certbot
# editá-los faria a próxima execução de criar-ambiente.sh desfazer o TLS em silêncio.
install -d -m 755 -o root -g www-data /var/www/laf-acme
cat > /etc/nginx/snippets/laf-acme.conf <<'CONF'
# Lar Anália Franco — ver infra/provisionar.sh
location ^~ /.well-known/acme-challenge/ {
    root /var/www/laf-acme;
    default_type "text/plain";
    auth_basic off;
}
CONF
nginx -t
systemctl enable --now nginx >/dev/null
systemctl reload nginx
ok 'nginx ativo'

# ---------------------------------------------------------------------------
# 7. Usuário de deploy
# ---------------------------------------------------------------------------
passo "Usuário ${USUARIO_DEPLOY}"
if ! id -u "$USUARIO_DEPLOY" >/dev/null 2>&1; then
  adduser --disabled-password --gecos '' "$USUARIO_DEPLOY"
fi
# Sem senha, nunca: `--disabled-password` deixa a conta sem senha definida, e o `!` no
# /etc/shadow impede login por senha mesmo que PasswordAuthentication volte a `yes`.
passwd -l "$USUARIO_DEPLOY" >/dev/null
usermod -aG www-data "$USUARIO_DEPLOY"

install -d -m 700 -o "$USUARIO_DEPLOY" -g "$USUARIO_DEPLOY" "/home/${USUARIO_DEPLOY}/.ssh"
autorizadas="/home/${USUARIO_DEPLOY}/.ssh/authorized_keys"
touch "$autorizadas"
if [[ -n "$CHAVE_PUBLICA" ]]; then
  # Idempotente: a mesma chave não entra duas vezes.
  grep -qxF "$CHAVE_PUBLICA" "$autorizadas" || echo "$CHAVE_PUBLICA" >> "$autorizadas"
  ok 'chave pública registrada'
elif [[ ! -s "$autorizadas" ]]; then
  aviso "nenhuma chave em ${autorizadas} — passe --chave-publica ou o usuário não entra."
fi
chmod 600 "$autorizadas"
chown "${USUARIO_DEPLOY}:${USUARIO_DEPLOY}" "$autorizadas"

# O `deploy` precisa recarregar PHP-FPM e reiniciar os serviços do ambiente no fim de cada
# publicação — e SÓ isso. sudo irrestrito para um usuário que aceita chave de um segredo do
# GitHub transformaria qualquer vazamento daquele segredo em root na máquina.
cat > /etc/sudoers.d/laf-deploy <<CONF
# Lar Anália Franco — ver infra/provisionar.sh. Lista fechada, sem senha.
${USUARIO_DEPLOY} ALL=(root) NOPASSWD: /usr/bin/systemctl reload php${VERSAO_PHP}-fpm
${USUARIO_DEPLOY} ALL=(root) NOPASSWD: /usr/bin/systemctl reload nginx
${USUARIO_DEPLOY} ALL=(root) NOPASSWD: /usr/bin/systemctl restart laf-site@*
${USUARIO_DEPLOY} ALL=(root) NOPASSWD: /usr/bin/systemctl restart laf-queue@*
${USUARIO_DEPLOY} ALL=(root) NOPASSWD: /usr/bin/systemctl restart laf-scheduler@*
${USUARIO_DEPLOY} ALL=(root) NOPASSWD: /usr/bin/systemctl status laf-*
CONF
chmod 440 /etc/sudoers.d/laf-deploy
visudo -c -f /etc/sudoers.d/laf-deploy >/dev/null
ok 'sudo restrito aos recarregamentos de deploy'

install -d -m 755 -o "$USUARIO_DEPLOY" -g www-data "$RAIZ"
install -d -m 755 -o "$USUARIO_DEPLOY" -g www-data "${RAIZ}/bin"
# Grupo `deploy`, não `root`: publicar.sh, reverter.sh e a checagem de saúde rodam COMO
# deploy e leem /etc/laf/<ambiente>.conf a cada publicação. Com o diretório fechado em
# root:root o arquivo lá dentro pode estar 644 e mesmo assim não ser alcançável — falta a
# travessia do diretório. Escrita continua só para root: quem descreve o ambiente é
# criar-ambiente.sh, e o conteúdo (hosts, caminhos, nome do banco) não guarda segredo.
install -d -m 750 -o root -g "$USUARIO_DEPLOY" /etc/laf
ok "$RAIZ"

# ---------------------------------------------------------------------------
# 7b. Usuário de administração
# ---------------------------------------------------------------------------
# Com `--trancar-ssh`, root deixa de entrar por SSH e este passa a ser o único caminho
# humano de administração da máquina: rodar `criar-ambiente.sh`, reprovisionar, investigar.
# É um usuário SEPARADO do `deploy` de propósito — ver a explicação no cabeçalho.
passo "Usuário ${USUARIO_ADMIN}"
if ! id -u "$USUARIO_ADMIN" >/dev/null 2>&1; then
  adduser --disabled-password --gecos '' "$USUARIO_ADMIN"
fi
passwd -l "$USUARIO_ADMIN" >/dev/null
usermod -aG sudo "$USUARIO_ADMIN"

install -d -m 700 -o "$USUARIO_ADMIN" -g "$USUARIO_ADMIN" "/home/${USUARIO_ADMIN}/.ssh"
autorizadas_admin="/home/${USUARIO_ADMIN}/.ssh/authorized_keys"
touch "$autorizadas_admin"
if [[ -n "$CHAVE_PUBLICA_ADMIN" ]]; then
  grep -qxF "$CHAVE_PUBLICA_ADMIN" "$autorizadas_admin" || echo "$CHAVE_PUBLICA_ADMIN" >> "$autorizadas_admin"
  ok 'chave pública registrada'
elif [[ ! -s "$autorizadas_admin" ]]; then
  aviso "nenhuma chave em ${autorizadas_admin} — passe --chave-publica-admin, ou --trancar-ssh vai se recusar a rodar."
fi
chmod 600 "$autorizadas_admin"
chown "${USUARIO_ADMIN}:${USUARIO_ADMIN}" "$autorizadas_admin"

# NOPASSWD é OBRIGATÓRIO aqui, não conveniência. A conta entra só com chave e tem a senha
# travada (`passwd -l`), e a regra padrão do grupo `sudo` do Ubuntu PEDE senha: sem esta
# linha, `sysadmin` estaria no grupo certo e mesmo assim não conseguiria virar root — um
# servidor sem administrador nenhum no instante em que `--trancar-ssh` desativa o root.
#
# O que isso significa, dito com todas as letras: quem tem a chave do `sysadmin` tem root
# na máquina. É o mesmo poder que `PermitRootLogin prohibit-password` daria, com três
# ganhos — a conta tem nome, o `sudo` registra cada comando, e revogar uma chave é apagar
# uma linha em vez de mexer no root. A chave do `deploy`, que é a que vive num segredo do
# GitHub, continua presa à lista fechada de /etc/sudoers.d/laf-deploy.
cat > /etc/sudoers.d/laf-sysadmin <<CONF
# Lar Anália Franco — ver infra/provisionar.sh. Administração humana, só com chave.
${USUARIO_ADMIN} ALL=(ALL) NOPASSWD: ALL
CONF
chmod 440 /etc/sudoers.d/laf-sysadmin
visudo -c -f /etc/sudoers.d/laf-sysadmin >/dev/null
ok 'sudo completo, só com chave'

# ---------------------------------------------------------------------------
# 8. Firewall, fail2ban, atualizações automáticas
# ---------------------------------------------------------------------------
passo 'Firewall (ufw)'
# Sem `ufw --force reset`: resetar derruba o firewall por um instante e apaga regra que
# alguém tenha acrescentado depois. As linhas abaixo já são idempotentes por si.
ufw default deny incoming >/dev/null
ufw default allow outgoing >/dev/null
ufw allow 22/tcp  >/dev/null
ufw allow 80/tcp  >/dev/null
ufw allow 443/tcp >/dev/null
ufw --force enable >/dev/null
ok 'entrada bloqueada, exceto 22/80/443'

passo 'fail2ban'
cat > /etc/fail2ban/jail.d/laf.local <<'CONF'
# Lar Anália Franco — ver infra/provisionar.sh
[sshd]
enabled  = true
maxretry = 5
bantime  = 1h
findtime = 10m
CONF
systemctl enable --now fail2ban >/dev/null
systemctl restart fail2ban
ok 'sshd protegido'

passo 'Atualizações automáticas de segurança'
cat > /etc/apt/apt.conf.d/20auto-upgrades <<'CONF'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
CONF
# Só o repositório de segurança, e sem reinício automático: derrubar o site sozinho de
# madrugada para aplicar kernel é pior que o atraso de aplicar na mão.
cat > /etc/apt/apt.conf.d/51laf-unattended <<'CONF'
Unattended-Upgrade::Allowed-Origins {
    "${distro_id}:${distro_codename}-security";
    "${distro_id}ESMApps:${distro_codename}-apps-security";
    "${distro_id}ESM:${distro_codename}-infra-security";
};
Unattended-Upgrade::Automatic-Reboot "false";
CONF
systemctl enable --now unattended-upgrades >/dev/null
ok 'só segurança, sem reinício automático'

# ---------------------------------------------------------------------------
# 9. Resumo
# ---------------------------------------------------------------------------
passo 'Servidor provisionado'
cat <<RESUMO
  php       $("php${VERSAO_PHP}" -r 'echo PHP_VERSION;')
  node      $(node --version)
  postgres  $(psql --version | awk '{print $3}')
  redis     $(redis-server --version | grep -oE 'v=[0-9.]+' | cut -d= -f2)
  nginx     $(nginx -v 2>&1 | grep -oE 'nginx/[0-9.]+' | cut -d/ -f2)
  certbot   $(certbot --version 2>&1 | awk '{print $2}')

  Próximos passos:
    1. De outra máquina: ssh ${USUARIO_DEPLOY}@<IP> e ssh ${USUARIO_ADMIN}@<IP>  (os dois precisam funcionar)
    2. Aqui:             ./provisionar.sh --trancar-ssh
    3. Como ${USUARIO_ADMIN}:      sudo ./criar-ambiente.sh --ambiente staging --dominio <DOMINIO> ...
RESUMO
