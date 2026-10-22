# Relatório da sessão 42 — migração para São Paulo, Etapa 4: aplicação restaurada

Etapa 4 de 6. Esta sessão restaurou a aplicação no servidor novo (`200.141.22.161`, base
preparada na sessão 41) **a partir do backup**, como estava em Boston, e testou tudo pelo IP
novo.

O que ficou de fora, por regra da etapa:
- **não** houve mudança de DNS, push na `main` nem deploy pelo GitHub;
- **não** se usou `criar-ambiente.sh`;
- **não** se rodou o certbot para emitir ou renovar.

Acesso: `ssh sysadmin@200.141.22.161` + `sudo -n`. Os arquivos do backup foram copiados para
`~sysadmin/etapa4` (`700`), conferidos por sha256 lá e **apagados ao final**. Nenhum valor de
`.env` nem dado pessoal aparece neste relatório.

> **O site público está fora do ar desde a reinstalação do VPS.** Durante o passo 8, Boston
> (`2.25.223.146`) não respondeu nem do servidor novo nem da máquina local: a reinstalação foi
> do mesmo VPS (o hostname continua `srv1982616`), então o servidor antigo não existe mais.
> O DNS ainda aponta para lá. Por isso a Etapa 5 (virar o DNS e trocar os segredos do GitHub)
> é urgente.

## Passo a passo

### 1. Integridade do backup

`sha256sum -c SHA256SUMS`: **20/20 OK** na máquina local. Depois da cópia, os 12 arquivos
usados foram conferidos de novo **no servidor**: 12/12 OK.

### 2. Banco

- `pg-globals.sql` aplicado com `ON_ERROR_STOP=1`. A única linha omitida foi
  `CREATE ROLE postgres;`, porque o papel já existe; o `ALTER ROLE postgres` que vem depois
  rodou normalmente.
- O papel `laf_staging` foi criado com login e sem superusuário, com o hash de senha do backup.
- `pg_restore --create --exit-on-error` rodou com código 0. O banco ficou com
  `UTF8 / C.UTF-8 / libc`, os mesmos parâmetros do dump, com dono `laf_staging`. As 27
  tabelas são do `laf_staging`.

| Tabela | Sessão 40 | São Paulo | |
|---|---:|---:|---|
| activity_log | 196 | 196 | = |
| contact_messages | 3 | 3 | = |
| media | 32 | 32 | = |
| migrations | 29 | 29 | = |
| model_has_roles | 12 | 12 | = |
| page_images | 26 | 26 | = |
| pages | 26 | 26 | = |
| partners | 1 | 1 | = |
| roles | 7 | 7 | = |
| transparency_documents | 18 | 18 | = |
| users | 7 | 7 | = |
| as outras 16 tabelas | 0 | 0 | = |

**27/27 contagens idênticas** (comparadas por `diff` contra a tabela da sessão 40).

### 3. Estrutura do ambiente

| Item | Origem | Dono / modo |
|---|---|---|
| `/var/www/laf/staging`, `releases/`, `shared/` | criados | `deploy:www-data 755` (como em `criar-ambiente.sh`) |
| `shared/storage` | `storage.tar.gz`, extraído com `-p --same-owner` | os do tar |
| `shared/.env` | `env/backend.env` | `deploy:www-data 640` (sessão 38) |
| `shared/site.env` | `env/site.env` | `deploy:www-data 640` |
| `shared/painel-config.js` | `env/painel-config.js` | `deploy:www-data 644` |
| `/etc/laf/staging.conf` | `env/etc-laf-staging.conf`, com `PUBLICO=1` | `root:root 644` (servicos.tar.gz) |
| `releases/20261020-201933-3e2eb33` | `release-…tar.gz`, extraído com `-p --same-owner` | os do tar |
| `current` | link, criado como `deploy` | → `…/releases/20261020-201933-3e2eb33` |
| pool `laf-staging.conf` do FPM | `servicos.tar.gz` | `root:root 644`; `php-fpm8.5 -t` ok antes do reload |
| `/var/log/php` | criado | `root:root 755` |
| `/usr/local/bin/laf-backup-banco` | `infra/backup-banco.sh` | `root:root 755` (ver divergência 2) |

**sha256 instalado × backup** (conferido logo depois da cópia e de novo no fim da sessão):

| Arquivo | |
|---|---|
| `shared/.env` | idêntico a `env/backend.env` |
| `shared/site.env` | idêntico a `env/site.env` |
| `shared/painel-config.js` | idêntico a `env/painel-config.js` |
| `/etc/laf/staging.conf` | idêntico a `env/etc-laf-staging.conf` |

O mtime dos quatro é o momento da instalação: nenhum foi editado depois. As chaves
`APP_KEY`, `FIELD_ENCRYPTION_KEY` e `BLIND_INDEX_KEY` são, portanto, as de Boston.

**Links da release:** `backend/.env`, `backend/storage` e `painel/config.js` apontam para
`/var/www/laf/staging/shared/…` e resolvem. O `RELEASE` registra o commit `3e2eb33`, gerado
com PHP 8.5.11 e Node v24.21.0.

**Storage**, igual à sessão 40:

| Pasta | Arquivos | Bytes |
|---|---:|---:|
| `app/private/media` | 134 | 17 361 173 |
| `app/private/transparency-documents` | 18 | 16 147 046 |
| `framework/views` | 24 | 56 090 |
| `logs` | 1 | 15 296 |

### 4. Backups antigos

`backups-existentes.tar.gz` foi extraído em `/` com `-p --same-owner`. Isso recriou
`/var/backups/laf/staging/` (8 dumps diários, de 15 a 22/10),
`/var/backups/laf/migracao-dominio-20261020/` e `/var/tmp/laf-midia-20260930-131326/`.

### Conferência de donos e permissões, passos 3 a 6

Cada entrada de cada tar (`tar -tvzf`) foi comparada com `stat` no disco:

| Tar | Entradas | Diferentes |
|---|---:|---:|
| storage.tar.gz | 253 | 0 |
| release-20261020-201933-3e2eb33.tar.gz | 9 054 | 0 |
| backups-existentes.tar.gz | 188 | 0 |
| letsencrypt.tar.gz | 96 | 0 |
| nginx.tar.gz (os 8 membros restaurados) | 8 | 0 |
| servicos.tar.gz (o pool) | 1 | 0 |

### 5. Certificados

`letsencrypt.tar.gz` foi extraído em `/` com `-p --same-owner`, o que preservou links
simbólicos, `renewal/` e o hook `renewal-hooks/deploy/laf-recarregar-nginx.sh`. **Nenhuma
emissão nem renovação foi feita.** `certbot certificates` reconhece os 6:

| Certificado | Vence | sha256 (início) |
|---|---|---|
| laranaliafrancolondrina.org.br (+ www) | 2027-01-18 (88 dias) | `BC:5A:98:B0…` |
| api.laranaliafrancolondrina.org.br | 2027-01-18 (88 dias) | `6C:3D:CB:8B…` |
| painel.laranaliafrancolondrina.org.br | 2027-01-18 (88 dias) | `AF:E0:80:81…` |
| homologacao-laf.softhing.com.br | 2026-12-17 (56 dias) | `DB:10:61:38…` |
| api.homologacao-laf.softhing.com.br | 2026-12-17 (56 dias) | `B6:85:5E:48…` |
| painel.homologacao-laf.softhing.com.br | 2026-12-17 (56 dias) | `87:76:2F:E1…` |

O `certbot.timer` do pacote está ativo, como em Boston. Ele só renova a 30 dias do vencimento,
isto é, a partir de 17/11 para os do domínio antigo. Para renovar, o DNS já precisa apontar
para cá, senão o desafio `--webroot` falha.

### 6. Nginx

Os quatro arquivos `sites-available/laf-staging-{site,api,painel,dominio-antigo}.conf` e os
quatro links em `sites-enabled` vieram de `nginx.tar.gz`. `nginx -t` passou antes do reload.
Os snippets `laf-*` já eram iguais aos de Boston (sessão 41), e nada mais de `/etc/nginx`
foi tocado.

### 7. Serviços

`systemctl enable --now` em `laf-site@staging`, `laf-queue@staging`, `laf-scheduler@staging`
e `laf-backup@staging.timer`.

| Unit | Estado | Reinícios | Log inicial |
|---|---|---:|---|
| laf-site@staging | active / enabled | 0 | `Listening on http://127.0.0.1:3101` |
| laf-queue@staging | active / enabled | 0 | iniciou, sem erro |
| laf-scheduler@staging | active / enabled | 0 | `No scheduled commands are ready to run.` |
| laf-backup@staging.timer | active / enabled | — | próximo disparo: 23/10, por volta de 03:26 UTC |

Nenhuma unit ficou em `systemctl --failed`. No fim da sessão: zero erros no journal da fila e
do agendador, zero linhas novas nos `error.log` do Nginx e no `laravel.log`, e nenhum
`/var/log/php/laf-staging.error.log`. O journal do site tem **um** stack trace, às 06:30:33:
o 503 do diagnóstico descrito no passo 8, antes do ajuste temporário. Depois dele não houve
nenhum.

Observação, que não vem desta etapa: o systemd avisa
`Unknown key name 'StartLimitIntervalSec' in section 'Service', ignoring` nas três units. A
diretiva pertence a `[Unit]`, então a intenção de "nunca desistir de reiniciar" não está
valendo. As units são idênticas às de Boston e de `infra/modelos/`, então o defeito vem do
repositório. Com `RestartSec=3`, o limite padrão (5 partidas em 10 s) não é alcançado, por
isso não há efeito prático hoje. Fica como pendência para `infra/modelos/`.

### 8. Testes pelo IP novo, sem DNS

Do cliente, com `curl --resolve <host>:443:200.141.22.161` e `--resolve <host>:80:…`. Os
certificados foram conferidos com Python `ssl`, que valida cadeia e nome com SNI, e
comparados pela impressão digital sha256 com `/etc/letsencrypt/live/*/cert.pem`. O PDF foi
baixado com User-Agent de robô (`LafMigracaoBot/1.0`), para não somar `download_count`: o
contador estava em 1 antes e continuou em 1 depois.

| Teste | Esperado | Obtido | |
|---|---|---|---|
| https://laranaliafrancolondrina.org.br/ | 200, HTML do Nuxt | 200 text/html, `__nuxt` presente, `<title>Lar Anália Franco — creche, contraturno e bazar em Londrina</title>` | ok |
| https://laranaliafrancolondrina.org.br/bazar (SSR com dado do banco) | 200, título e meta_description da API no HTML | 200, os dois presentes | ok |
| https://www.laranaliafrancolondrina.org.br/quem-somos?x=1 | 301 → https://laranaliafrancolondrina.org.br/quem-somos?x=1 | igual | ok |
| http://laranaliafrancolondrina.org.br/bazar | 301 → https | 301 → https://laranaliafrancolondrina.org.br/bazar | ok |
| http://www.laranaliafrancolondrina.org.br/ | 301 → https | 301 → https://laranaliafrancolondrina.org.br/ | ok |
| https://api.laranaliafrancolondrina.org.br/up | 200 | 200 | ok |
| https://api.laranaliafrancolondrina.org.br/api/v1/public/pages | 200 com dados | 200, 23 páginas publicadas | ok |
| https://api.laranaliafrancolondrina.org.br/api/v1/public/transparency-documents | 200 com dados | 200, 18 documentos | ok |
| http://api.laranaliafrancolondrina.org.br/up | 301 → https | 301 → https://api.laranaliafrancolondrina.org.br/up | ok |
| https://painel.laranaliafrancolondrina.org.br/ | 200, app Vue | 200 text/html, app presente | ok |
| https://painel.laranaliafrancolondrina.org.br/config.js | 200, aponta para a API certa | 200, `https://api.laranaliafrancolondrina.org.br` (e o site) | ok |
| http://painel.laranaliafrancolondrina.org.br/ | 301 → https | 301 → https://painel.laranaliafrancolondrina.org.br/ | ok |
| https://homologacao-laf.softhing.com.br/algum/caminho?q=1 | 301 → https://laranaliafrancolondrina.org.br/algum/caminho?q=1 | igual | ok |
| http://homologacao-laf.softhing.com.br/algum/caminho?q=1 | 301 → https | 301 → https://laranaliafrancolondrina.org.br/algum/caminho?q=1 | ok |
| https://api.homologacao-laf.softhing.com.br/algum/caminho?q=1 | 301 → https://api.laranaliafrancolondrina.org.br/algum/caminho?q=1 | igual | ok |
| http://api.homologacao-laf.softhing.com.br/algum/caminho?q=1 | 301 → https | 301 → https://api.laranaliafrancolondrina.org.br/algum/caminho?q=1 | ok |
| https://painel.homologacao-laf.softhing.com.br/algum/caminho?q=1 | 301 → https://painel.laranaliafrancolondrina.org.br/algum/caminho?q=1 | igual | ok |
| http://painel.homologacao-laf.softhing.com.br/algum/caminho?q=1 | 301 → https | 301 → https://painel.laranaliafrancolondrina.org.br/algum/caminho?q=1 | ok |
| certificado de laranaliafrancolondrina.org.br | live/laranaliafrancolondrina.org.br | `BC:5A:98:B0…`, nome confere | ok |
| certificado de www.laranaliafrancolondrina.org.br | live/laranaliafrancolondrina.org.br | `BC:5A:98:B0…`, nome confere | ok |
| certificado de api.laranaliafrancolondrina.org.br | live/api.laranaliafrancolondrina.org.br | `6C:3D:CB:8B…`, nome confere | ok |
| certificado de painel.laranaliafrancolondrina.org.br | live/painel.laranaliafrancolondrina.org.br | `AF:E0:80:81…`, nome confere | ok |
| certificado de homologacao-laf.softhing.com.br | live/homologacao-laf.softhing.com.br | `DB:10:61:38…`, nome confere | ok |
| certificado de api.homologacao-laf.softhing.com.br | live/api.homologacao-laf.softhing.com.br | `B6:85:5E:48…`, nome confere | ok |
| certificado de painel.homologacao-laf.softhing.com.br | live/painel.homologacao-laf.softhing.com.br | `87:76:2F:E1…`, nome confere | ok |
| https://laranaliafrancolondrina.org.br/midia/f2a8ccc1…/640.webp | 200, sha256 igual ao do storage | 200 image/webp, `79b45798ee1a…` = storage | ok |
| https://laranaliafrancolondrina.org.br/transparencia/documentos/2026/…pdf | 200, sha256 igual ao do storage | 200 application/pdf, `4676db2bfbc5…` = storage | ok |

**27/27 ok.**

**Como o SSR foi testado — leia antes da Etapa 5.**
- Na primeira rodada, a home levou 21 s e `/bazar` deu **503**.
- O SSR do Nitro busca os dados em `NUXT_PUBLIC_API_URL` (`https://api.laranaliafrancolondrina.org.br`).
  **No próprio servidor**, esse nome resolve pelo DNS público, que ainda aponta para Boston,
  e Boston não existe mais. Em Boston isso funcionava porque o nome resolvia para a própria
  máquina. O mesmo vai acontecer aqui quando o DNS virar.
- Para testar antes disso, pus no servidor uma entrada **temporária** em `/etc/hosts`
  (`127.0.0.1 api.laranaliafrancolondrina.org.br`). É o equivalente, do lado do servidor, ao
  `--resolve` do cliente. Com ela, a home respondeu em 0,3 s e a tabela acima passou inteira.
- **A entrada foi removida ao fim dos testes**: o `/etc/hosts` original foi restaurado com
  `cp -p` de uma cópia feita antes, e `getent` voltou a resolver para `2.25.223.146`.
- **Por isso, neste momento o SSR do servidor novo responde 503 até o DNS virar.** Isso é
  esperado e não afeta ninguém, porque nenhum visitante chega a este IP ainda.

### 9. Dados cifrados

Um script PHP inicializou a aplicação real da release (`current/backend`, como `deploy`), com
a sessão do Postgres em `transaction_read_only = on`. Ele descobriu os campos `FieldEncrypted`
por introspecção dos casts de cada modelo, como na sessão 40, e imprimiu só quantidades.

| Modelo | Registros | Preenchidos | Decifrados | Falhas |
|---|---:|---:|---:|---:|
| ContactMessage | 3 | 9 | 9 | 0 |
| PartnershipInquiry | 0 | 0 | 0 | 0 |
| PickupRequest | 0 | 0 | 0 | 0 |
| ProgramApplication | 0 | 0 | 0 | 0 |
| VolunteerApplication | 0 | 0 | 0 | 0 |

**9/9 campos decifrados, nenhuma exceção.** O resultado é idêntico ao da sessão 40. O script
foi apagado do servidor logo depois de rodar.

### 10. Backup

`systemctl start laf-backup@staging.service` terminou com `Result=success` e status 0:

- **dump novo:** `lar_analia_franco_staging-20261022-063307.sql.gz`, `root:root 600`, 28 KB;
- **integridade:** `gzip -t` ok, com 27 blocos `COPY public.*`, um por tabela;
- **retenção:** manteve os 8 antigos mais o novo (9 no total);
- **timer:** próximo disparo agendado em 23/10.

Os 8 dumps restaurados mantêm o mtime original, então a retenção de 7 dias passa a apagá-los
a partir de amanhã, como teria feito em Boston.

## Divergências e decisões tomadas sem consulta

1. **Donos dos arquivos de `env/`.** O backup guardou só o conteúdo, sem dono nem
   permissão. Usei `deploy:www-data 640` para o `.env` (registrado na sessão 38) e os
   valores de `criar-ambiente.sh` para os outros. `/etc/laf/staging.conf` seguiu
   `servicos.tar.gz` (`root:root 644`).
2. **`/usr/local/bin/laf-backup-banco` e `/var/log/php` não estavam no backup**, e as units
   e o pool dependem deles. O primeiro foi instalado de `/root/laf-infra/backup-banco.sh`,
   que é idêntico ao que estava em Boston (sessão 41, comparação de `/root/laf-infra`). O
   segundo foi criado como em `criar-ambiente.sh`.
3. **`/etc/hosts` temporário**, explicado no passo 8, foi acrescentado e removido nesta
   sessão. O servidor terminou igual a Boston nesse ponto.
4. **`CREATE ROLE postgres;` omitido** do `pg-globals.sql`, porque o papel já existe.
5. **PDF baixado com User-Agent de robô**, para o teste não alterar `download_count`.

## Incidente local, corrigido nesta sessão

- **O que aconteceu.** O primeiro envio dos arquivos para o servidor usou
  `rsync … $H:etapa4/` no zsh. Ali, `:e` é modificador de variável (extensão), então o destino
  virou o diretório **local** `161tapa4/`, dentro do repositório. O comando não deu erro, e eu
  só notei no `git status` do fim da sessão.
- **O que vazou para lá.** Era uma cópia completa do backup: dump, `.env`, chaves TLS e
  storage.
- **O que foi feito.**
  - Os arquivos **nunca foram commitados** (`git log --all` não tem nenhum commit nesse
    caminho) nem saíram da máquina.
  - Apaguei o diretório.
  - Conferi que não há outra cópia em `~`.
  - O backup original continua 20/20 no `sha256sum -c`.
- **O que não foi afetado.** O envio real foi refeito com o endereço literal, e as conferências
  de sha256 no servidor são dessa segunda cópia.

## Para a Etapa 5

- **O site está fora do ar agora** (ver o aviso no topo). Virar o DNS é o que o traz de volta.
- **Antes de qualquer push que publique**, trocar no GitHub os segredos `DEPLOY_HOST` e
  `DEPLOY_KNOWN_HOSTS`. A chave de host ed25519 é
  `SHA256:yKeeVzuADiEPpDaGJTfRqamdfwQmUYTO/JF/MdSOkeo` (sessão 41).
- **Janela de cache de DNS no próprio servidor.** Logo depois da troca, o resolvedor do
  servidor (`systemd-resolved`) pode guardar o IP velho por até um TTL, e o SSR ficaria em 503
  até lá. Duas formas de evitar: baixar o TTL antes de virar, ou rodar
  `resolvectl flush-caches` no servidor logo depois da troca. Depois disso, conferir com
  `getent hosts api.laranaliafrancolondrina.org.br` no servidor.
- **O que testar de novo pelo DNS real depois da virada:**
  - a tabela do passo 8, sem `--resolve`;
  - um login no painel (as sessões antigas não migram: estavam no Redis de Boston);
  - um envio de teste do formulário de parceria, para exercitar o `tax_id_hash`
    (pendência da sessão 40).
- `docs/deploy.md` §0 (IP, "nos Estados Unidos"), a política de privacidade e
  `FORM_CONSENT_TERMS_VERSION` acompanham a troca de região (sessão 39).
- Pendência de repositório: mover `StartLimitIntervalSec` para `[Unit]` em
  `infra/modelos/laf-{site,queue,scheduler}@.service` (passo 7).

## Veredito

**ETAPA 4 CONCLUÍDA — PRONTO PARA VIRAR O DNS.**

O banco foi restaurado sem erro, com 27/27 contagens iguais às da sessão 40. Os quatro
arquivos de ambiente são idênticos ao backup por sha256, e as chaves não mudaram. A estrutura,
os backups antigos, os certificados e o Nginx batem com Boston em dono e permissão (9 600
entradas, nenhuma diferença), e os 6 certificados são reconhecidos. Os serviços estão ativos,
sem falhas. Os 27 testes pelo IP novo passaram, inclusive o sha256 de mídia e de PDF contra o
storage. Os 9 campos cifrados decifram com a aplicação real, e o backup diário gera dump
válido.

A ressalva está no passo 8: o SSR só funciona quando o nome da API resolve para este servidor.
Isso foi provado com a resolução local temporária e vale por si depois da virada do DNS,
contanto que se cuide do cache de DNS do servidor (Etapa 5).
