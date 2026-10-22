# Relatório da sessão 39 — migração para São Paulo, Etapa 1: backup

O VPS da Hostinger em Boston (`2.25.223.146`, `srv1982616`) vai ser reinstalado na região de
São Paulo, e a reinstalação apaga tudo, inclusive backups e snapshots do provedor. Esta sessão
fez **só a Etapa 1 de 6**: inventário somente leitura e backup completo, baixado para a máquina
local. Nada no servidor foi parado, reiniciado ou alterado; o único efeito colateral foi criar
`/var/tmp/laf-backup-etapa1-20261022-051124/` e apagá-lo depois de conferido o download. Ao
final, os sete serviços continuavam `active` e o site respondia 200.

Acesso usado: `ssh sysadmin@2.25.223.146` + `sudo -n` (root por SSH está desativado).

## Inventário

### Versões

| Componente | Versão |
|---|---|
| Ubuntu | 24.04.5 LTS, kernel 6.8.0-147-generic |
| PHP | 8.5.10 (CLI e FPM, PPA `ondrej/php`) |
| Extensões PHP | bcmath calendar ctype curl date dom exif FFI fileinfo filter ftp gd gettext hash iconv igbinary intl json lexbor libxml mbstring openssl pcntl pcre PDO pdo_pgsql pgsql Phar posix random readline redis Reflection session shmop SimpleXML sockets sodium SPL standard sysvmsg sysvsem sysvshm tokenizer uri xml xmlreader xmlwriter xsl OPcache zip zlib |
| PostgreSQL | 16.15 (pacote da distro) |
| Node.js | v24.21.0 (NodeSource) |
| Redis | 7.0.15 |
| Nginx | 1.24.0 (distro) |
| Certbot | 2.9.0 |
| Composer | **não instalado** — correto, só roda no CI (`docs/deploy.md` §1) |

Máquina: disco 96 GB (2,6 GB usados), 7,8 GB de RAM.

### O que existe e precisa ser recriado

| Item | Onde | Observação |
|---|---|---|
| Banco do site/painel | `lar_analia_franco_staging` (9,4 MB, 27 tabelas), papel `laf_staging` | o único banco da aplicação; `postgres` está vazio |
| **Umami** | — | **não existe no servidor**: sem banco, sem serviço, sem Docker; `NUXT_PUBLIC_UMAMI_URL` e `..._WEBSITE_ID` estão vazias em `site.env`. Não há o que migrar |
| Storage do Laravel | `/var/www/laf/staging/shared/storage` (34 MB) | `app/private/media` (32 pastas por UUID) e `app/private/transparency-documents` (18 arquivos); `app/public` vazio |
| Configuração da aplicação | `shared/.env`, `shared/site.env`, `shared/painel-config.js`, `/etc/laf/staging.conf` | só os nomes das chaves foram lidos; os valores não foram exibidos |
| Nginx | `sites-available/laf-staging-{site,api,painel,dominio-antigo}.conf`, `snippets/laf-{acme,comum,tls}.conf` | raiz + `www` (301), `api.`, `painel.`, e os três hosts de `homologacao-laf.softhing.com.br` em 301 para o domínio novo |
| Certificados | `/etc/letsencrypt` — 6 certificados | os 3 do domínio novo vencem em 2027-01-18; os 3 do domínio antigo em 2026-12-17 |
| ACME webroot | `/var/www/laf-acme` | vazio, usado pelo Certbot `--webroot` |
| systemd | `laf-site@staging`, `laf-queue@staging`, `laf-scheduler@staging`, `laf-backup@staging.timer` | sem supervisor; a fila é a unit `laf-queue@` |
| PHP-FPM | pool `laf-staging.conf`, `conf.d/99-laf.ini` (FPM e CLI) | |
| PostgreSQL | `conf.d/99-laf.conf`, `pg_hba.conf` padrão (peer + scram em localhost) | escuta só em localhost |
| Redis | `/etc/redis/redis.conf`, bind localhost, RDB ligado, AOF desligado | guarda **uma** chave (`illuminate:queue:restart`) — nada persistente de valor; cache, sessão e fila são descartáveis |
| Cron | `/etc/cron.d`: `certbot`, `e2scrub_all`, `php`, `sysstat`, `monarx-update` | nenhum crontab de usuário. `monarx-*` é o agente de segurança da Hostinger, não nosso |
| Firewall | `ufw` ativo: deny entrada, 22/80/443 liberados (v4 e v6) | `fail2ban` com `jail.d/laf.local` |
| SSH | `sshd_config.d/00-laf-seguranca.conf`: `PermitRootLogin no`, `PasswordAuthentication no` | |
| Usuários com shell | `root`, `postgres`, `deploy`, `sysadmin` | `sudoers.d/laf-deploy` e `laf-sysadmin` |
| Chaves autorizadas | `root`, `deploy`, `sysadmin`: chave pessoal `ed25519 SHA256:ZlhH2m…dLlk`; `deploy` também `github-actions@laf` `SHA256:1NkdrT…m4c` | só impressões digitais, chaves públicas |
| Atualizações | `apt.conf.d/51laf-unattended`, `20auto-upgrades` | |
| Scripts | `/var/www/laf/bin/{publicar,reverter}.sh`, `/root/laf-infra/` | vêm de `infra/` do repositório |
| Backups antigos | `/var/backups/laf/staging` (8 dumps diários, 15–22/10), `/var/backups/laf/migracao-dominio-20261020/` (29 MB), `/var/tmp/laf-midia-*-20260930-131326` (12 MB) | também somem na reinstalação; entraram no backup |
| Releases | `/var/www/laf/staging/releases` — 5 releases, 476 MB; atual `20261020-201933-3e2eb33` | **não** entraram: o pacote é reproduzível pelo CI |
| Portas | 22, 80, 443 públicas; 5432, 6379, 3101 (Nitro) só em localhost | |

## Arquivos do backup

| Arquivo | Tamanho | sha256 |
|---|---|---|
| `backups-existentes.tar.gz` | 40 MB | `1566b89c6e6e3c15764bdca8858e4a11026c999f97fd4477d09ab20595562b04` |
| `cron-firewall-ssh.tar.gz` | 6,9 KB | `e889eb31d06903e35310b08c6b4f7b95e052fa514f3208a4806e72e078b87316` |
| `db-lar_analia_franco_staging.dump` | 207 KB | `59cb0a3d511a98535ae8b6016f5ff7b69830ef212a1a1ecd0dbd1b874aa2dea3` |
| `db-postgres.dump` | 2,0 KB | `6b80f7760cd083e37c53d2038144c22586e7a3242bab2c0ffee5444ad7af310e` |
| `env/backend.env` | 3,8 KB | `6c5fdfb2189b73c583d18f1d3fb58b5509fd0b2682eafe543131ccaf19857b9f` |
| `env/etc-laf-staging.conf` | 829 B | `a6bd7dbf41532ce3f4e5b2c8f1936a7cfe7f41e63b90332d5933d5f3d1aa681a` |
| `env/painel-config.js` | 399 B | `cf298e106e2d26a28005e35eb99e56f9533b2e2ec7b0ac1610ac8600ead0e345` |
| `env/site.env` | 1,1 KB | `261391fbba55881d9d709a936a25392eb73d6af1d3d1194513008daca628b643` |
| `letsencrypt.tar.gz` | 15 KB | `0d90a19a79feba0ebbad8b265b8856f48efaa9bafe035300c5e86b96cddb3e0d` |
| `nginx.tar.gz` | 10 KB | `5d8519c5bec5d4d4ada8ae1d5105711d71c9fc58fc1e12b8790d0bc936a2bdf2` |
| `pg-globals.sql` | 801 B | `b5ed8b22934c1b86bdea8e710072e9c2e9004358ef60aa554e5457cbebaff797` |
| `servicos.tar.gz` | 90 KB | `b5571555b885c0de5212b9fb2bf900459181e57682d7b45c1484aefa25b7805e` |
| `sistema/crontabs-usuarios.txt` | 58 B | `f92ad4e96c4eefbf872d198b28fddf9883551bfb2b49999d0443882943312fc0` |
| `sistema/pacotes-dpkg.txt` | 88 KB | `d0f5ed0658da96ece08c1a39bd42eaff832d3a8b8ebb33dfcb278b6c154ffdfc` |
| `sistema/php-modulos.txt` | 401 B | `209245986845b3e8e911f7182f3ac6839cb900cbcdc1cefa2f6c92550eb1b2aa` |
| `sistema/servicos-rodando.txt` | 2,4 KB | `d4675c022d460c625708ea266b10d3e51c8a1169d53e7c2ac473f505fb61bef0` |
| `sistema/timers.txt` | 2,2 KB | `9c250beab21b1fbff733846fa04c266a9a566e8be4e0d23ba04cc5fec51c146c` |
| `sistema/ufw-status.txt` | 600 B | `8496a3890045b238970b6e5693544db9312b44b00acd1407fa253ef409fe3d9d` |
| `storage.tar.gz` | 28 MB | `4c76440d74252422b4e00f5925b10f3412ec08f06fa14a832635a27795c057ad` |
| `MANIFESTO.md` | — | `4b6207e53da89799f2358543d9f479214d68299c75247e7726443028d6bf200d` (escrito localmente, depois do download) |

`servicos.tar.gz` inclui: units `laf-*` e seus links em `*.wants`, pool e ini do PHP-FPM,
`/etc/postgresql/16/main`, `redis.conf` e `/var/lib/redis`, `fail2ban/jail.d`,
`sshd_config.d`, `sudoers.d`, configuração de unattended-upgrades, fontes do apt,
`/etc/laf`, `/var/www/laf/bin` e `/root/laf-infra`. `nginx.tar.gz` é `/etc/nginx` inteiro mais
`/var/www/laf-acme`.

## Conferências

- `pg_restore -l` leu os dois dumps sem erro; o do banco da aplicação tem 27 `TABLE DATA`, o
  mesmo número de tabelas em `public` no banco vivo.
- `tar -tzf` leu os sete `.tar.gz` sem erro; o storage tem 152 arquivos entre `media` e
  `transparency-documents`.
- Os quatro arquivos de `env/` têm o mesmo sha256 que os originais no servidor (cópia exata).
- Depois do `scp`, `sha256sum -c SHA256SUMS` deu **19/19 OK** na máquina local.
- Só então o diretório temporário foi removido do servidor; `ls` confirmou que não sobrou nada.
- O restore completo **não** foi testado (fica para a etapa de restauração no servidor novo).

## Onde ficou

`~/backups/laf/laf-backup-etapa1-20261022-051124/` (`/home/tghelere/backups/laf/…`, WSL), fora
de qualquer repositório git, com permissões `700`/`600`. 68 MB no total.

**Este diretório junta o dump do banco e as três chaves do `.env`** — ou seja, junta o texto
cifrado e o que o abre, exatamente o que `docs/deploy.md` §14 pede para manter separado. Para a
migração isso é necessário, mas a cópia não deve ficar onde está depois da Etapa 6: guardar
cifrada (ou apagar), e nunca sincronizar com nuvem sem cifrar antes.

## Para as próximas etapas

- A troca de região muda a política pública: com o servidor no Brasil deixa de haver
  transferência internacional, e `/politica-de-privacidade` e `FORM_CONSENT_TERMS_VERSION`
  precisam acompanhar (`docs/deploy.md` §0).
- Os certificados podem ser restaurados do tar ou reemitidos; reemitir exige o DNS já
  apontando para o IP novo.
- O `known_hosts` local e o segredo `DEPLOY_KNOWN_HOSTS` do GitHub vão mudar com a chave de host
  nova.

## Veredito

**ETAPA 1 CONCLUÍDA.**
