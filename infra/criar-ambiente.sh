#!/usr/bin/env bash
#
# Cria (ou reconcilia) um ambiente do Lar Anália Franco no servidor — idempotente.
#
# Roda COMO ROOT, no servidor, DEPOIS de provisionar.sh. Um ambiente é: banco e usuário de
# banco próprios, índices de Redis próprios, `.env` próprio, três hosts no Nginx com TLS,
# pool do PHP-FPM próprio, três serviços systemd e backup diário. Nada é compartilhado entre
# staging e production além do sistema operacional (ver docs/deploy.md §2).
#
# Uso:
#   ./criar-ambiente.sh --ambiente staging    --dominio exemplo.org.br --email-tls voce@exemplo.org \
#                       [--email-de-teste teste@exemplo.org] [--hosts-na-raiz]
#   ./criar-ambiente.sh --ambiente production --dominio exemplo.org.br --email-tls voce@exemplo.org
#
# --hosts-na-raiz (só staging): o domínio passado JÁ É o domínio da homologação, e os três
# hosts saem dele direto — `exemplo.org.br`, `api.exemplo.org.br`, `painel.exemplo.org.br` —
# em vez do prefixo `homologacao.`. É o caso de quem hospeda a homologação num domínio
# próprio (ver docs/deploy.md §2), e não num subdomínio do domínio de produção.
#
# O que este script NUNCA sobrescreve, mesmo rodando de novo:
#   - o `.env` do ambiente (guarda as três chaves; regerar é perder o dado cifrado)
#   - o arquivo de senha da autenticação básica
#   - releases e `current`
#
# O que ele imprime UMA vez, no fim, e não guarda em lugar nenhum recuperável: as três
# chaves, a senha do banco e a senha da autenticação básica. Vão para o cofre de senhas.

set -euo pipefail

AMBIENTE=''
DOMINIO=''
EMAIL_TLS=''
EMAIL_DE_TESTE=''
SEM_TLS=0
HOSTS_NA_RAIZ=0

while [[ $# -gt 0 ]]; do
  case "$1" in
    --ambiente)       AMBIENTE="$2"; shift 2 ;;
    --dominio)        DOMINIO="$2"; shift 2 ;;
    --email-tls)      EMAIL_TLS="$2"; shift 2 ;;
    --email-de-teste) EMAIL_DE_TESTE="$2"; shift 2 ;;
    --sem-tls)        SEM_TLS=1; shift ;;
    --hosts-na-raiz)  HOSTS_NA_RAIZ=1; shift ;;
    -h|--help) sed -n '2,31p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *) echo "Opção desconhecida: $1" >&2; exit 2 ;;
  esac
done

passo()  { printf '\n\033[1m==> %s\033[0m\n' "$1"; }
ok()     { printf '  ok  %s\n' "$1"; }
aviso()  { printf '\033[33m  !   %s\033[0m\n' "$1"; }
erro()   { printf '\033[31mERRO: %s\033[0m\n' "$1" >&2; }

[[ "$(id -u)" -eq 0 ]] || { erro 'este script roda como root.'; exit 1; }
[[ -n "$DOMINIO" ]]    || { erro 'falta --dominio.'; exit 1; }
[[ -n "$EMAIL_TLS" ]]  || { erro 'falta --email-tls (para onde o Let'"'"'s Encrypt avisa de expiração).'; exit 1; }

RAIZ_DO_SCRIPT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MODELOS="${RAIZ_DO_SCRIPT}/modelos"
[[ -d "$MODELOS" ]] || { erro "não achei ${MODELOS} — envie o infra/ inteiro para o servidor."; exit 1; }

VERSAO_PHP='8.5'
USUARIO_DEPLOY='deploy'

# ---------------------------------------------------------------------------
# Perfil do ambiente
# ---------------------------------------------------------------------------
# Tudo o que distingue staging de production está AQUI, num lugar só. Espalhar essas
# diferenças pelo script é como um ambiente acaba com meia configuração do outro.
case "$AMBIENTE" in
  staging)
    # Com --hosts-na-raiz o domínio passado já é o da homologação e não leva prefixo; sem
    # ele, a homologação mora em `homologacao.` dentro do domínio de produção. Os dois
    # arranjos mantêm os três hosts sob um mesmo domínio raiz, que é o que o modo cookie do
    # Sanctum exige (ver docs/decisoes/0003-sanctum-cookie-mode.md).
    if [[ "$HOSTS_NA_RAIZ" -eq 1 ]]; then
      RAIZ_DE_HOSTS="${DOMINIO}"
    else
      RAIZ_DE_HOSTS="homologacao.${DOMINIO}"
    fi
    HOST_SITE="${RAIZ_DE_HOSTS}"
    HOST_API="api.${RAIZ_DE_HOSTS}"
    HOST_PAINEL="painel.${RAIZ_DE_HOSTS}"
    DOMINIO_COOKIE=".${RAIZ_DE_HOSTS}"
    BANCO='lar_analia_franco_staging'
    USUARIO_BANCO='laf_staging'
    PORTA_SITE=3101
    REDIS_DB=1
    REDIS_CACHE_DB=2
    FPM_MAX_CHILDREN=5
    NUXT_ENVIRONMENT='staging'   # liga o X-Robots-Tag e o robots.txt bloqueado
    AUTENTICACAO_BASICA=1
    RETENCAO_BACKUP_DIAS=7
    ;;
  production)
    # Em produção os hosts já saem da raiz do domínio. Aceitar --hosts-na-raiz aqui, calado,
    # daria a impressão de que a opção muda alguma coisa neste ambiente.
    [[ "$HOSTS_NA_RAIZ" -eq 0 ]] || { erro '--hosts-na-raiz só vale para --ambiente staging.'; exit 1; }
    HOST_SITE="${DOMINIO}"
    HOST_API="api.${DOMINIO}"
    HOST_PAINEL="painel.${DOMINIO}"
    DOMINIO_COOKIE=".${DOMINIO}"
    BANCO='lar_analia_franco'
    USUARIO_BANCO='laf_production'
    PORTA_SITE=3001
    REDIS_DB=3
    REDIS_CACHE_DB=4
    FPM_MAX_CHILDREN=10
    NUXT_ENVIRONMENT=''          # vazio: em produção o site É indexável
    AUTENTICACAO_BASICA=0
    # Produção precisa de política de retenção decidida com a instituição antes de receber
    # dado real (ver docs/deploy.md §9). 30 dias é o mínimo enquanto isso não acontece.
    RETENCAO_BACKUP_DIAS=30
    ;;
  *)
    erro "--ambiente precisa ser 'staging' ou 'production' (recebi: '${AMBIENTE:-vazio}')."
    exit 1
    ;;
esac

BASE="/var/www/laf/${AMBIENTE}"
COMPARTILHADO="${BASE}/shared"
CONF_AMBIENTE="/etc/laf/${AMBIENTE}.conf"
HTPASSWD="/etc/nginx/laf-${AMBIENTE}.htpasswd"

# `openssl rand -base64 32` mais o prefixo `base64:` é exatamente o que
# `php artisan key:generate` produz — e não exige uma release no servidor para rodar.
chave_nova() { printf 'base64:%s' "$(openssl rand -base64 32)"; }
senha_nova() { openssl rand -base64 24 | tr -d '/+=' | head -c 28; }

passo "Ambiente ${AMBIENTE} em ${DOMINIO}"
cat <<RESUMO
  site    https://${HOST_SITE}
  api     https://${HOST_API}
  painel  https://${HOST_PAINEL}
  banco   ${BANCO} (usuário ${USUARIO_BANCO})
  redis   db ${REDIS_DB} (sessão/fila) e ${REDIS_CACHE_DB} (cache)
  nitro   127.0.0.1:${PORTA_SITE}
RESUMO

# ---------------------------------------------------------------------------
# 1. Diretórios
# ---------------------------------------------------------------------------
passo 'Diretórios'
install -d -m 755 -o "$USUARIO_DEPLOY" -g www-data "$BASE" "${BASE}/releases" "$COMPARTILHADO"
# storage/ guarda os PDFs de transparência enviados pelo painel: é o único dado do disco que
# não está no pacote nem no repositório, e o único que precisa sobreviver às releases.
install -d -m 775 -o "$USUARIO_DEPLOY" -g www-data \
  "${COMPARTILHADO}/storage" \
  "${COMPARTILHADO}/storage/app/private" \
  "${COMPARTILHADO}/storage/app/public" \
  "${COMPARTILHADO}/storage/framework/cache/data" \
  "${COMPARTILHADO}/storage/framework/sessions" \
  "${COMPARTILHADO}/storage/framework/views" \
  "${COMPARTILHADO}/storage/logs"
install -d -m 750 -o root -g root "/var/backups/laf/${AMBIENTE}"
install -d -m 755 -o root -g root /var/log/php
ok "$BASE"

# ---------------------------------------------------------------------------
# 2. Banco e usuário
# ---------------------------------------------------------------------------
passo 'PostgreSQL'
existe_papel="$(sudo -u postgres psql -tAc "SELECT 1 FROM pg_roles WHERE rolname='${USUARIO_BANCO}'")"
existe_banco="$(sudo -u postgres psql -tAc "SELECT 1 FROM pg_database WHERE datname='${BANCO}'")"

SENHA_BANCO=''
SENHA_BANCO_NOVA=0
if [[ -f "${COMPARTILHADO}/.env" ]]; then
  # `.env` já existe: a senha de lá é a verdade. Trocar aqui derrubaria a aplicação no ar.
  SENHA_BANCO="$(grep -E '^DB_PASSWORD=' "${COMPARTILHADO}/.env" | head -1 | cut -d= -f2- | tr -d '"')"
fi
if [[ -z "$SENHA_BANCO" ]]; then
  SENHA_BANCO="$(senha_nova)"
  SENHA_BANCO_NOVA=1
fi

if [[ "$existe_papel" != '1' ]]; then
  sudo -u postgres psql -qc "CREATE ROLE ${USUARIO_BANCO} LOGIN PASSWORD '${SENHA_BANCO}'"
  ok "papel ${USUARIO_BANCO} criado"
elif [[ "$SENHA_BANCO_NOVA" -eq 1 ]]; then
  # Papel existe mas não há .env de onde tirar a senha: a senha antiga é irrecuperável, então
  # define-se uma nova. Só acontece num ambiente meio criado — nunca num que esteja no ar.
  sudo -u postgres psql -qc "ALTER ROLE ${USUARIO_BANCO} PASSWORD '${SENHA_BANCO}'"
  aviso "papel ${USUARIO_BANCO} já existia e a senha foi redefinida (não havia .env)"
else
  ok "papel ${USUARIO_BANCO} mantido"
fi

if [[ "$existe_banco" != '1' ]]; then
  sudo -u postgres createdb -O "$USUARIO_BANCO" -E UTF8 "$BANCO"
  ok "banco ${BANCO} criado"
else
  ok "banco ${BANCO} mantido"
fi

# O papel não precisa poder criar banco nem ler o dos outros ambientes. Sem esta linha, o
# usuário de staging enxerga o schema public de production por herança do papel `public`.
sudo -u postgres psql -qd "$BANCO" -c "REVOKE ALL ON DATABASE ${BANCO} FROM PUBLIC"
sudo -u postgres psql -qd "$BANCO" -c "GRANT ALL ON DATABASE ${BANCO} TO ${USUARIO_BANCO}"
sudo -u postgres psql -qd "$BANCO" -c "ALTER SCHEMA public OWNER TO ${USUARIO_BANCO}"

# ---------------------------------------------------------------------------
# 3. .env do backend — criado UMA vez, nunca sobrescrito
# ---------------------------------------------------------------------------
passo 'Arquivo .env do backend'
CHAVES_NOVAS=0
if [[ -f "${COMPARTILHADO}/.env" ]]; then
  ok '.env já existe — mantido intacto (é onde moram as três chaves)'
  APP_KEY="$(grep -E '^APP_KEY=' "${COMPARTILHADO}/.env" | head -1 | cut -d= -f2-)"
  FIELD_KEY='(inalterada)'
  BLIND_KEY='(inalterada)'
else
  APP_KEY="$(chave_nova)"
  FIELD_KEY="$(chave_nova)"
  BLIND_KEY="$(chave_nova)"
  CHAVES_NOVAS=1

  # As linhas de FORM_RECIPIENT_* ficam COMENTADAS de propósito. Definida e vazia, o Laravel
  # usa a string vazia como destinatário em vez de cair no padrão de config/forms.php — bug
  # real deste projeto, registrado em docs/deploy.md §4.
  cat > "${COMPARTILHADO}/.env" <<ENV
# Lar Anália Franco — ${AMBIENTE}
#
# Criado por infra/criar-ambiente.sh. Este arquivo NÃO existe no repositório, não vai para o
# GitHub e não entra no pacote de deploy: ele é do servidor e fica no servidor.
# Lista completa e comentada das variáveis: backend/.env.example. Checklist do que precisa de
# decisão humana: docs/deploy.md §4.
#
# Depois de QUALQUER alteração aqui:
#   cd ${BASE}/current/backend && php${VERSAO_PHP} artisan config:cache
#   sudo systemctl restart laf-queue@${AMBIENTE} laf-scheduler@${AMBIENTE}

APP_NAME="Lar Anália Franco"
APP_ENV=${AMBIENTE}
APP_KEY=${APP_KEY}
APP_DEBUG=false
APP_TIMEZONE=America/Sao_Paulo
APP_URL=https://${HOST_API}

APP_LOCALE=pt_BR
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=pt_BR

LOG_CHANNEL=stack
LOG_LEVEL=info

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=${BANCO}
DB_USERNAME=${USUARIO_BANCO}
DB_PASSWORD="${SENHA_BANCO}"

CACHE_STORE=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
# Índices e prefixo PRÓPRIOS do ambiente. O prefixo padrão do projeto é derivado de APP_NAME
# e seria idêntico nos dois ambientes; com o mesmo Redis, staging e production disputariam
# as mesmas chaves de sessão e de cache — e o efeito disso é gente deslogando sozinha em
# produção quando alguém testa homologação.
REDIS_DB=${REDIS_DB}
REDIS_CACHE_DB=${REDIS_CACHE_DB}
REDIS_PREFIX=laf-${AMBIENTE}-

# --- E-mail: PREENCHER com o SMTP real antes de usar ---
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="nao-responda@${DOMINIO}"
MAIL_FROM_NAME="\${APP_NAME}"
$(if [[ "$AMBIENTE" == 'staging' ]]; then cat <<STAGING
# SÓ em homologação: todo e-mail da aplicação é reendereçado para cá e cc/bcc são
# descartados. É o que permite a instituição testar formulário sem que chegue mensagem na
# caixa de ninguém. VAZIA EM PRODUÇÃO — preenchida lá, ninguém recebe as notificações.
MAIL_ALWAYS_TO=${EMAIL_DE_TESTE}
STAGING
else cat <<PRODUCAO
# MAIL_ALWAYS_TO fica AUSENTE em produção, de propósito. Ver docs/deploy.md §4.
PRODUCAO
fi)

SANCTUM_STATEFUL_DOMAINS=${HOST_SITE},${HOST_PAINEL}
SESSION_DOMAIN=${DOMINIO_COOKIE}
CORS_ALLOWED_ORIGINS=https://${HOST_SITE},https://${HOST_PAINEL}
# O Nitro do site faz proxy servidor-a-servidor dos formulários, do mesmo host.
TRUSTED_PROXIES=127.0.0.1,::1

# --- As três chaves. Perder as duas últimas é irreversível (docs/protecao-de-dados.md). ---
FIELD_ENCRYPTION_KEY=${FIELD_KEY}
FIELD_ENCRYPTION_PREVIOUS_KEYS=
BLIND_INDEX_KEY=${BLIND_KEY}
BLIND_INDEX_PREVIOUS_KEYS=

# --- Formulários: descomentar linha a linha quando houver endereço real confirmado ---
# FORM_RECIPIENT_PROGRAM_APPLICATION=
# FORM_RECIPIENT_PICKUP_REQUEST=
# FORM_RECIPIENT_VOLUNTEER_APPLICATION=
# FORM_RECIPIENT_PARTNERSHIP_INQUIRY=
# FORM_RECIPIENT_CONTACT_MESSAGE=

ADMIN_BASE_URL=https://${HOST_PAINEL}
SITE_BASE_URL=https://${HOST_SITE}
FORM_CONSENT_TERMS_VERSION=2026-09-18

TELESCOPE_ENABLED=false
ENV
  ok '.env criado'
fi
chown "${USUARIO_DEPLOY}:www-data" "${COMPARTILHADO}/.env"
chmod 640 "${COMPARTILHADO}/.env"

# ---------------------------------------------------------------------------
# 4. Ambiente do site (Nitro) e configuração do painel
# ---------------------------------------------------------------------------
# Os dois são REESCRITOS a cada execução, e podem ser: não guardam segredo nenhum, só
# endereço. É o contrário do .env — e é o que faz "rodar de novo conserta" valer para eles.
passo 'Ambiente do site e configuração do painel'
cat > "${COMPARTILHADO}/site.env" <<ENV
# Lar Anália Franco — ${AMBIENTE}. Lido pelo systemd (laf-site@${AMBIENTE}).
# As NUXT_PUBLIC_* são lidas em tempo de EXECUÇÃO pelo Nitro: o mesmo site/ do pacote
# responde como homologação ou como produção só trocando este arquivo (ver docs/deploy.md).
NODE_ENV=production
NITRO_PORT=${PORTA_SITE}
NITRO_HOST=127.0.0.1
NUXT_PUBLIC_API_URL=https://${HOST_API}
NUXT_PUBLIC_SITE_URL=https://${HOST_SITE}
# Só 'staging' tem efeito: liga o X-Robots-Tag: noindex, nofollow em TODA resposta e faz o
# robots.txt bloquear o site inteiro. Em produção precisa ficar VAZIA — preenchida lá, o
# site sai da busca, de que a captação da instituição depende.
NUXT_PUBLIC_ENVIRONMENT=${NUXT_ENVIRONMENT}
# Umami é cookieless e não exige banner de consentimento. Sem as duas, o script não é
# injetado (ver docs/decisoes/0006-umami-em-vez-de-google-analytics.md).
NUXT_PUBLIC_UMAMI_WEBSITE_ID=
NUXT_PUBLIC_UMAMI_URL=
ENV
chown "${USUARIO_DEPLOY}:www-data" "${COMPARTILHADO}/site.env"
chmod 640 "${COMPARTILHADO}/site.env"
ok 'site.env'

cat > "${COMPARTILHADO}/painel-config.js" <<CONFIG
/* Lar Anália Franco — ${AMBIENTE}. Gerado por infra/criar-ambiente.sh.
 * infra/publicar.sh aponta painel/config.js de cada release para este arquivo — ver
 * docs/decisoes/0015-painel-configurado-em-tempo-de-execucao.md. */
window.__LAF_CONFIG__ = {
  apiUrl: 'https://${HOST_API}',
  siteUrl: 'https://${HOST_SITE}',
  sessionIdleTimeoutMinutes: '15',
}
CONFIG
chown "${USUARIO_DEPLOY}:www-data" "${COMPARTILHADO}/painel-config.js"
chmod 644 "${COMPARTILHADO}/painel-config.js"
ok 'painel-config.js'

# ---------------------------------------------------------------------------
# 5. PHP-FPM
# ---------------------------------------------------------------------------
passo 'Pool do PHP-FPM'
sed -e "s|__AMBIENTE__|${AMBIENTE}|g" \
    -e "s|__FPM_MAX_CHILDREN__|${FPM_MAX_CHILDREN}|g" \
    "${MODELOS}/php-fpm-pool.conf" > "/etc/php/${VERSAO_PHP}/fpm/pool.d/laf-${AMBIENTE}.conf"
systemctl reload "php${VERSAO_PHP}-fpm"
ok "/run/php/laf-${AMBIENTE}.sock"

# ---------------------------------------------------------------------------
# 6. Autenticação básica (só homologação)
# ---------------------------------------------------------------------------
SENHA_AUTH=''
BLOCO_AUTH=''
if [[ "$AUTENTICACAO_BASICA" -eq 1 ]]; then
  passo 'Autenticação básica do site de homologação'
  if [[ -f "$HTPASSWD" ]]; then
    ok 'já existe — senha mantida (não é recuperável; apague o arquivo para gerar outra)'
  else
    SENHA_AUTH="$(senha_nova)"
    htpasswd -bcB "$HTPASSWD" homologacao "$SENHA_AUTH" >/dev/null 2>&1
    chown root:www-data "$HTPASSWD"
    chmod 640 "$HTPASSWD"
    ok "usuário 'homologacao' criado"
  fi
  BLOCO_AUTH="    auth_basic           \"Lar Anália Franco — homologação\";
    auth_basic_user_file ${HTPASSWD};"
fi

# ---------------------------------------------------------------------------
# 7. Nginx e TLS
# ---------------------------------------------------------------------------
# Ordem obrigatória: o certificado não existe antes do certbot rodar, e o modelo final
# referencia o certificado. Um `nginx -t` com ssl_certificate apontando para arquivo
# inexistente FALHA, e um nginx que não recarrega deixa o servidor inteiro no ar com a
# configuração velha — inclusive os outros ambientes.
# `http2 on;` só existe a partir do nginx 1.25.1. O Ubuntu 24.04 traz o 1.24, onde HTTP/2 é
# parâmetro do `listen` — e esse parâmetro, por sua vez, fica OBSOLETO a partir do 1.25.1.
# Não existe forma única que sirva às duas versões, e `provisionar.sh` instala o nginx
# estável da distribuição, que muda a cada LTS. Escolher aqui, pela versão que está
# instalada, é o que impede o modelo de ficar certo numa máquina e errado na seguinte: na
# 1.24 um `http2 on;` derruba o `nginx -t` inteiro, e na 1.25+ o parâmetro do `listen` só
# avisa — mas avisa em todo reload, para sempre.
versao_pelo_menos() { [[ "$(printf '%s\n%s\n' "$2" "$1" | sort -V | head -1)" == "$2" ]]; }

VERSAO_NGINX="$(nginx -v 2>&1 | grep -oE 'nginx/[0-9.]+' | cut -d/ -f2)"
if versao_pelo_menos "$VERSAO_NGINX" '1.25.1'; then
  BLOCO_ESCUTA='    listen 443 ssl;
    listen [::]:443 ssl;
    http2 on;'
else
  BLOCO_ESCUTA='    listen 443 ssl http2;
    listen [::]:443 ssl http2;'
fi

gerar_site() {
  local modelo="$1" destino="$2"

  sed -e "s|__AMBIENTE__|${AMBIENTE}|g" \
      -e "s|__HOST_SITE__|${HOST_SITE}|g" \
      -e "s|__HOST_API__|${HOST_API}|g" \
      -e "s|__HOST_PAINEL__|${HOST_PAINEL}|g" \
      -e "s|__PORTA_SITE__|${PORTA_SITE}|g" \
      "$modelo" \
    | awk -v auth="$BLOCO_AUTH" -v escuta="$BLOCO_ESCUTA" '
        /^[[:space:]]*__AUTH_BASICA__[[:space:]]*$/ { if (auth != "") print auth; next }
        /^[[:space:]]*__ESCUTA_443__[[:space:]]*$/  { print escuta; next }
        { print }' \
    > "$destino"
}

passo 'Certificados TLS'
for host in "$HOST_SITE" "$HOST_API" "$HOST_PAINEL"; do
  if [[ "$SEM_TLS" -eq 1 ]]; then
    aviso "--sem-tls: pulando ${host}"
    continue
  fi
  if [[ -d "/etc/letsencrypt/live/${host}" ]]; then
    ok "${host} já tem certificado"
    continue
  fi

  # Bloco temporário só com a porta 80, para o desafio HTTP-01 poder ser respondido antes
  # de existir certificado nenhum.
  cat > "/etc/nginx/sites-available/laf-${AMBIENTE}-acme-${host}.conf" <<CONF
server {
    listen 80;
    listen [::]:80;
    server_name ${host};
    include snippets/laf-acme.conf;
    location / { return 404; }
}
CONF
  ln -sfn "/etc/nginx/sites-available/laf-${AMBIENTE}-acme-${host}.conf" \
          "/etc/nginx/sites-enabled/laf-${AMBIENTE}-acme-${host}.conf"
  nginx -t && systemctl reload nginx

  if ! certbot certonly --webroot -w /var/www/laf-acme -d "$host" \
       --agree-tos --no-eff-email -m "$EMAIL_TLS" --non-interactive; then
    erro "certbot falhou para ${host}."
    echo '  Quase sempre é DNS: o registro A precisa apontar para o IP desta VPS e já ter propagado.' >&2
    echo "  Confira com: dig +short ${host}" >&2
    exit 1
  fi
  ok "${host}"
done

passo 'Hosts do Nginx'
for host in "$HOST_SITE" "$HOST_API" "$HOST_PAINEL"; do
  rm -f "/etc/nginx/sites-enabled/laf-${AMBIENTE}-acme-${host}.conf" \
        "/etc/nginx/sites-available/laf-${AMBIENTE}-acme-${host}.conf"
done

gerar_site "${MODELOS}/nginx-site.conf"   "/etc/nginx/sites-available/laf-${AMBIENTE}-site.conf"
gerar_site "${MODELOS}/nginx-api.conf"    "/etc/nginx/sites-available/laf-${AMBIENTE}-api.conf"
gerar_site "${MODELOS}/nginx-painel.conf" "/etc/nginx/sites-available/laf-${AMBIENTE}-painel.conf"
for parte in site api painel; do
  ln -sfn "/etc/nginx/sites-available/laf-${AMBIENTE}-${parte}.conf" \
          "/etc/nginx/sites-enabled/laf-${AMBIENTE}-${parte}.conf"
done

if [[ "$SEM_TLS" -eq 1 ]]; then
  aviso '--sem-tls: hosts gravados mas NÃO habilitados (referenciam certificado inexistente).'
  for parte in site api painel; do rm -f "/etc/nginx/sites-enabled/laf-${AMBIENTE}-${parte}.conf"; done
fi

nginx -t
systemctl reload nginx
ok 'três hosts no ar'

# Renovação: o timer do pacote do certbot já roda duas vezes ao dia. O que falta é o nginx
# reler o certificado novo — sem este gancho a renovação acontece e o navegador continua
# recebendo o certificado vencido até alguém recarregar à mão.
install -d -m 755 /etc/letsencrypt/renewal-hooks/deploy
cat > /etc/letsencrypt/renewal-hooks/deploy/laf-recarregar-nginx.sh <<'HOOK'
#!/bin/sh
# Lar Anália Franco — ver infra/criar-ambiente.sh
systemctl reload nginx
HOOK
chmod +x /etc/letsencrypt/renewal-hooks/deploy/laf-recarregar-nginx.sh
systemctl enable --now certbot.timer >/dev/null 2>&1 || true
ok 'renovação automática com recarga do nginx'

# ---------------------------------------------------------------------------
# 8. Serviços
# ---------------------------------------------------------------------------
passo 'Serviços systemd'
for unidade in 'laf-site@.service' 'laf-queue@.service' 'laf-scheduler@.service' \
               'laf-backup@.service' 'laf-backup@.timer'; do
  install -m 644 "${MODELOS}/${unidade}" "/etc/systemd/system/${unidade}"
done
install -m 755 "${RAIZ_DO_SCRIPT}/backup-banco.sh" /usr/local/bin/laf-backup-banco
install -m 755 -o "$USUARIO_DEPLOY" -g www-data "${RAIZ_DO_SCRIPT}/publicar.sh" /var/www/laf/bin/publicar.sh
install -m 755 -o "$USUARIO_DEPLOY" -g www-data "${RAIZ_DO_SCRIPT}/reverter.sh" /var/www/laf/bin/reverter.sh
systemctl daemon-reload

# `enable` sem `--now`: os três serviços precisam de uma release em `current` para subir, e
# ela ainda não existe na primeira execução. Quem os inicia é infra/publicar.sh.
systemctl enable "laf-site@${AMBIENTE}" "laf-queue@${AMBIENTE}" "laf-scheduler@${AMBIENTE}" >/dev/null
systemctl enable --now "laf-backup@${AMBIENTE}.timer" >/dev/null
ok 'laf-site, laf-queue, laf-scheduler habilitados; backup diário ativo'

if [[ -L "${BASE}/current" ]]; then
  systemctl restart "laf-site@${AMBIENTE}" "laf-queue@${AMBIENTE}" "laf-scheduler@${AMBIENTE}"
  ok 'serviços reiniciados (já havia release publicada)'
else
  aviso 'ainda não há release — os serviços só sobem no primeiro deploy.'
fi

# ---------------------------------------------------------------------------
# 9. Arquivo de ambiente lido pelos outros scripts
# ---------------------------------------------------------------------------
passo "${CONF_AMBIENTE}"
cat > "$CONF_AMBIENTE" <<CONF
# Lar Anália Franco — ${AMBIENTE}. Gerado por infra/criar-ambiente.sh.
# Lido por publicar.sh, reverter.sh e backup-banco.sh: é a fonte única do que este ambiente
# É no servidor. Editar à mão sem rodar criar-ambiente.sh de novo faz os scripts e o Nginx
# discordarem entre si.
AMBIENTE=${AMBIENTE}
DOMINIO=${DOMINIO}
HOST_SITE=${HOST_SITE}
HOST_API=${HOST_API}
HOST_PAINEL=${HOST_PAINEL}
BASE=${BASE}
PORTA_SITE=${PORTA_SITE}
BANCO=${BANCO}
USUARIO_BANCO=${USUARIO_BANCO}
VERSAO_PHP=${VERSAO_PHP}
RETENCAO_BACKUP_DIAS=${RETENCAO_BACKUP_DIAS}
RELEASES_MANTIDAS=5
CONF
chmod 644 "$CONF_AMBIENTE"
ok 'gravado'

# ---------------------------------------------------------------------------
# 10. O que precisa ir para o cofre de senhas
# ---------------------------------------------------------------------------
passo 'Ambiente pronto'
echo
echo '  ┌─────────────────────────────────────────────────────────────────────────┐'
echo '  │  ANOTE AGORA. Isto não é impresso de novo e não é recuperável daqui.    │'
echo '  └─────────────────────────────────────────────────────────────────────────┘'
echo
if [[ "$CHAVES_NOVAS" -eq 1 ]]; then
  cat <<CHAVES
  As três chaves (${AMBIENTE}) — cofre de senhas, em lugar DISTINTO do backup do banco:
    APP_KEY=${APP_KEY}
    FIELD_ENCRYPTION_KEY=${FIELD_KEY}
    BLIND_INDEX_KEY=${BLIND_KEY}

  Perder as duas últimas é irreversível: o dado cifrado vira lixo e nenhum backup do banco o
  traz de volta, porque o backup guarda o texto cifrado, não a chave.

CHAVES
else
  echo "  As três chaves não foram tocadas (o .env já existia)."
  echo
fi
if [[ "$SENHA_BANCO_NOVA" -eq 1 ]]; then
  echo "  Banco: ${USUARIO_BANCO} / ${SENHA_BANCO}"
  echo
fi
if [[ -n "$SENHA_AUTH" ]]; then
  echo "  Autenticação básica de https://${HOST_SITE}:"
  echo "    usuário: homologacao"
  echo "    senha:   ${SENHA_AUTH}"
  echo
fi
cat <<PROXIMOS
  Falta preencher à mão em ${COMPARTILHADO}/.env:
    - MAIL_HOST / MAIL_PORT / MAIL_USERNAME / MAIL_PASSWORD (SMTP real)
    - FORM_RECIPIENT_* (descomentar linha a linha, só com endereço real confirmado)

  Depois: publicar a primeira release pelo GitHub Actions (workflow "Deploy", botão
  "Run workflow", ambiente ${AMBIENTE}) — ver docs/deploy.md.
PROXIMOS
