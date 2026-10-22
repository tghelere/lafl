# Relatório da sessão 41 — migração para São Paulo, Etapa 3: base do servidor

Etapa 3 de 6. O VPS foi reinstalado na região de São Paulo com Ubuntu 24.04 limpo. Esta sessão
preparou **só a base**: pacotes, usuários, configurações de sistema, firewall, `fail2ban` e
endurecimento do SSH. Ela **não** criou banco da aplicação, não restaurou dado, não escreveu
`.env`, não publicou release e não habilitou site no Nginx. Tudo isso é da Etapa 4.

Fonte da verdade: `infra/` e `docs/deploy.md`. Conferência: o backup da Etapa 1
(`~/backups/laf/laf-backup-etapa1-20261022-051124/`, ver `docs/relatorio-sessao-39.md`). Os
extratos do backup usados para comparar foram apagados do scratchpad ao final.

## Servidor novo

| | |
|---|---|
| IP | **`200.141.22.161`** (antes: `2.25.223.146`, Boston) |
| Hostname | `srv1982616` (o mesmo de antes) |
| Sistema | Ubuntu 24.04.5 LTS, kernel 6.8.0-147-generic |
| Máquina | disco de 96 GB, 7,8 GB de RAM (igual a Boston) |
| Fuso | `Etc/UTC` (o backup não registra o de Boston; os relatórios 39 e 40 citam horários do servidor em UTC) |
| **Chave de host ed25519** | **`SHA256:yKeeVzuADiEPpDaGJTfRqamdfwQmUYTO/JF/MdSOkeo`** |
| Chave de host ECDSA | `SHA256:u74rHPk9yF93nX+Ti18CPURoPJSjUgDty5rl+8FJZTI` |
| Chave de host RSA | `SHA256:beVAdsnlgcRDg7iP3oDDLCV3Ahf7cRfIkF4WKsxMhkM` |
| Chave de host ed25519 de Boston, para comparar | `SHA256:fa1rvwngCQlX+adJBh/3GJ2GDYxJaWnyBAn/VgwKA/I` |

A chave ed25519 do `known_hosts` local, que já estava lá no início da sessão, bate com a do
arquivo `/etc/ssh/ssh_host_ed25519_key.pub` lido no servidor. **O segredo
`DEPLOY_KNOWN_HOSTS` do GitHub ainda tem a chave de Boston** e precisa ser trocado antes da
primeira publicação. O mesmo vale para `DEPLOY_HOST`, se ele guardar o IP.

## Versões, Boston × São Paulo

| Componente | Boston (sessão 39) | São Paulo | |
|---|---|---|---|
| Ubuntu | 24.04.5 LTS | 24.04.5 LTS | = |
| Kernel | 6.8.0-147-generic | 6.8.0-147-generic | = (depois do `full-upgrade`; a imagem veio com 142) |
| PHP (CLI e FPM, PPA `ondrej/php`) | 8.5.10 | **8.5.12** | patch novo, ver divergência 1 |
| Extensões PHP (`php -m`) | `sistema/php-modulos.txt` | idêntico (`diff` vazio) | = |
| PostgreSQL (distro) | 16.15 | 16.15 | = |
| Node.js (NodeSource) | v24.21.0 | **v24.22.1** | patch novo, ver divergência 1 |
| Redis (distro) | 7.0.15 | 7.0.15 | = |
| Nginx (distro) | 1.24.0 | 1.24.0 | = |
| Certbot | 2.9.0 | 2.9.0 | = |
| fail2ban / ufw / unattended-upgrades | 1.0.2 / 0.36.2 / 2.9.1 | os mesmos pacotes da distro | = |
| Composer | não instalado | não instalado | = |

## O que foi aplicado, na ordem

1. **`apt-get full-upgrade` e reboot**, com o servidor ainda vazio. Isso levou o kernel de
   6.8.0-142 a 6.8.0-147, o mesmo de Boston, e deixou os pacotes base no nível de patches
   atual.
2. **`infra/` copiado para `/root/laf-infra`** (`rsync`), dono `root:root`, diretório `700`.
3. **`provisionar.sh` sem `--trancar-ssh`**, com a chave do GitHub Actions em
   `--chave-publica` e a pessoal em `--chave-publica-admin`, as duas tiradas do backup e
   conferidas contra `~/.ssh/laf-deploy.pub` e `~/.ssh/id_ed25519.pub`. O script terminou
   com código 0 e conferiu as nove extensões exigidas e os formatos do GD (JPEG, PNG e WebP).
   Ele instalou e configurou:
   - PHP 8.5 CLI e FPM, com `conf.d/99-laf.ini` nos dois;
   - Node 24;
   - PostgreSQL 16, com `conf.d/99-laf.conf` (só localhost);
   - Redis, só em localhost;
   - Nginx e Certbot, com os snippets `laf-comum`, `laf-tls` e `laf-acme`, e o `default`
     fora de `sites-enabled`;
   - usuários `deploy` e `sysadmin`, sem senha, com `sudoers.d/laf-deploy` e
     `laf-sysadmin`;
   - ufw e `fail2ban/jail.d/laf.local`;
   - `20auto-upgrades` e `51laf-unattended`;
   - `/var/www/laf`, `/var/www/laf/bin`, `/var/www/laf-acme` e `/etc/laf`.

   A saída completa está em `/root/provisionar-etapa3.log` no servidor (não há segredo nela).
4. **`authorized_keys` do `deploy` substituído por uma cópia exata do de Boston**: chave
   pessoal mais a do `github-actions@laf`. Ver divergência 3.
5. **`/var/www/laf/bin/{publicar,reverter}.sh`** instalados a partir de `infra/`, dono
   `deploy:deploy`, modo `755`, como em Boston.
6. **Units `laf-site@`, `laf-queue@`, `laf-scheduler@`, `laf-backup@.service` e
   `laf-backup@.timer`** copiadas para `/etc/systemd/system`, seguidas de `daemon-reload`.
   **Nenhuma foi habilitada nem iniciada**: não há links em `*.wants`,
   `systemctl list-units 'laf-*'` está vazio e não há timer `laf` em `list-timers`.
7. **Endurecimento do SSH, por último:**
   - uma conexão nova confirmou `ssh sysadmin@…` + `sudo -n whoami` → `root`;
   - outra confirmou `ssh deploy@…` com a chave pessoal e com a do GitHub Actions. O
     `sudo -n -l` do `deploy` lista exatamente as seis linhas da lista fechada. A chave do
     GitHub Actions é **recusada** no `sysadmin`;
   - em seguida, abri uma conexão mestre de root (`ControlMaster`), que ficou aberta durante
     o endurecimento;
   - `sshd -t` passou;
   - rodei `provisionar.sh --trancar-ssh --eu-confirmo`. O script escreve `00-laf-seguranca.conf`,
     roda `sshd -t` de novo e faz `reload`, não `restart`;
   - depois do reload, conexões novas confirmaram `sysadmin` + `sudo -n` e `deploy`.
     **Root foi recusado com a chave certa**, e quem tenta sem chave recebe
     `Permission denied (publickey)`, ou seja, nem senha nem keyboard-interactive são
     oferecidos;
   - só então fechei a sessão de root e apaguei o socket.
8. **Reboot de prova**, pedido pelo `sysadmin`. Depois do boot:
   - os sete serviços e o `ssh.socket` voltaram `active`, sem nenhuma unit em `--failed`;
   - as units `laf-*` continuaram desligadas;
   - o `sshd -T` efetivo continuou com `permitrootlogin no` e `passwordauthentication no`;
   - `deploy` entrou normalmente.

## Conferência byte a byte contra Boston

sha256 de cada arquivo no servidor novo × o mesmo arquivo no backup:

| Arquivo | |
|---|---|
| `etc/php/8.5/{fpm,cli}/conf.d/99-laf.ini` | = |
| `etc/php/8.5/fpm/pool.d/www.conf` | = |
| `etc/postgresql/16/main/{conf.d/99-laf.conf,pg_hba.conf,postgresql.conf}` | = |
| `etc/redis/redis.conf` | = |
| `etc/fail2ban/jail.d/{laf.local,defaults-debian.conf}` | = |
| `etc/apt/apt.conf.d/{20auto-upgrades,51laf-unattended}` | = |
| `etc/sudoers.d/{laf-deploy,laf-sysadmin}` | = |
| `etc/systemd/system/laf-*` (5 units) | = |
| `var/www/laf/bin/{publicar,reverter}.sh` | = |
| `etc/ufw/{ufw.conf,user.rules,user6.rules}` | = |
| `etc/ssh/sshd_config.d/{50-cloud-init,60-cloudimg-settings}.conf` | = |
| `etc/nginx/nginx.conf`, `etc/nginx/snippets/laf-{acme,comum,tls}.conf` | = |
| `home/deploy/.ssh/authorized_keys` | = (mesmas 2 impressões digitais) |
| `home/sysadmin/.ssh/authorized_keys` | = (mesma impressão digital) |

São 29 arquivos comparados por sha256 e os 2 `authorized_keys` por impressão digital, todos
iguais. `00-laf-seguranca.conf` não entrou na tabela porque o script o
regenera com o mesmo texto que estava em Boston (conferido lendo o arquivo).

### Impressões digitais das chaves autorizadas

| Usuário | Chave | Impressão digital | Sessão 39 |
|---|---|---|---|
| `sysadmin` | pessoal | `SHA256:ZlhH2m+c7mYTS9Tzn3KfeBC4eLh2UdXwhVNWIH5dLlk` | `ZlhH2m…dLlk` = |
| `deploy` | pessoal | `SHA256:ZlhH2m+c7mYTS9Tzn3KfeBC4eLh2UdXwhVNWIH5dLlk` | = |
| `deploy` | `github-actions@laf` | `SHA256:1NkdrTJ/bkhOO/3QOPTvJkJTL2CS8eZ/9bOFEof6m4c` | `1NkdrT…m4c` = |
| `root` | pessoal (veio da reinstalação) | `SHA256:ZlhH2m…dLlk` | inócua: root não entra por SSH |

## Estado final (critério de conclusão)

| Critério | Resultado |
|---|---|
| `sysadmin` entra e tem `sudo -n` | sim, `sudo -n whoami` → `root`, antes e depois do reboot |
| `deploy` entra | sim, com a chave pessoal e com a do GitHub Actions |
| root recusado | sim, `Permission denied (publickey)` mesmo com a chave certa |
| senha recusada | sim, o servidor só oferece `publickey`; `sshd -T`: `passwordauthentication no`, `kbdinteractiveauthentication no`, `permitemptypasswords no` |
| versões | batem com Boston; PHP e Node estão um patch à frente (divergência 1) |
| PHP-FPM ativo e vazio | ativo, só o pool `www` (o `laf-staging` é da Etapa 4) |
| Postgres ativo e vazio | ativo, só `postgres`, `template0` e `template1`, e só o papel `postgres` |
| Redis ativo e vazio | ativo, `dbsize` = 0 |
| Nginx ativo e vazio | ativo, `sites-enabled` vazio |
| ufw ativo | `deny (incoming)`, 22/80/443 em v4 e v6, igual a `sistema/ufw-status.txt` |
| fail2ban ativo | jail `sshd` ativa; 3 falhas contadas, todas das recusas provocadas de propósito neste teste; nenhum ban |
| Portas | públicas: só 22. Em localhost: 5432, 6379 e 65529 (`monarx-agent`) |

## Divergências encontradas

1. **PHP 8.5.12 e Node 24.22.1, contra 8.5.10 e 24.21.0 em Boston.**
   - O PPA `ondrej/php` só publica a versão mais recente, então a 8.5.10 não está mais lá.
   - Rebaixar de propósito para um patch mais antigo seria trocar correções de segurança por
     igualdade cosmética. Principal e secundária (8.5, 24) são as de `docs/deploy.md` §1.
   - A release no ar já tinha sido gerada no CI com PHP 8.5.11 (`docs/relatorio-sessao-40.md`
     §6), então Boston também rodava com um patch diferente do CI.
2. **`/root/laf-infra` de Boston era mais antigo que `infra/`.** A cópia lá não tinha:
   - a trava `PUBLICO=1` em `criar-ambiente.sh`;
   - o `FORM_CONSENT_TERMS_VERSION` novo;
   - a conferência dos formatos do GD em `provisionar.sh`;
   - o trinco de uma vez só do `--forcar-falha-de-saude` em `publicar.sh`;
   - o README atualizado.

   Aqui foi copiada a versão do repositório. Isso **não** é seguir Boston, de propósito:
   `/root/laf-infra` não é configuração em uso, é um depósito de scripts que só rodam na mão. A
   trava `PUBLICO=1` é justamente a proteção que a Etapa 4 precisa contra recriar o ambiente
   como homologação. `/var/www/laf/bin`, que é o que roda de verdade, era igual ao repositório
   nos dois lados.
3. **O `deploy` tem também a chave pessoal, além da do GitHub Actions.**
   - `docs/deploy.md` §1 e `provisionar.sh` descrevem o `deploy` só com a chave do segredo
     `DEPLOY_SSH_KEY`, e o script só aceita uma chave por usuário.
   - Boston tinha as duas, e o enunciado desta etapa pede as duas, então segui Boston e
     acrescentei a pessoal à mão.
   - Para o repositório passar a descrever isso, `provisionar.sh` precisaria aceitar mais de
     uma chave por usuário. Fica como pendência, sem alteração nesta sessão.
4. **Dono de `/root/laf-infra`**: em Boston era `deploy:deploy`, aqui é `root:root`.
   - Em Boston isso era efeito colateral do `rsync -a`: ele preserva o uid local 1000, e no
     servidor o uid 1000 é o `deploy`. O mesmo aconteceu aqui na primeira cópia e foi
     corrigido com `chown -R root:root`.
   - Não dava acesso nenhum, porque `/root` é `700`. Mas um script que root executa não deve
     pertencer ao usuário cuja chave vive num segredo do GitHub.
   - Na próxima cópia, usar `rsync -a --chown=root:root` (ou `--no-o --no-g`).
5. **`/etc/redis/redis.conf.d/99-laf.conf` não estava no backup** (só o `redis.conf`, que
   termina com o `include` dele). Ele foi regenerado por `provisionar.sh`. O `redis.conf` está
   idêntico, e o próprio arquivo principal já traz `bind 127.0.0.1 -::1`.
6. **Módulos do PHP-FPM × `php-modulos.txt`**: no FPM aparece `cgi-fcgi` e falta `pcntl`. É
   próprio do SAPI, porque `php-modulos.txt` foi tirado do CLI. No CLI a lista é idêntica.
7. **O root tem senha definida (`passwd -S`: `P`)**, posta pela reinstalação da Hostinger. Ela
   não serve para SSH (recusada acima) e é o caminho de entrada pelo console do provedor. O
   backup não registra o estado da senha de root em Boston, então não há com o que comparar.
   `deploy` e `sysadmin` estão travados (`L`), como manda o script.
8. **`full-upgrade` e reboot antes de provisionar** não estão em `provisionar.sh` nem em
   `docs/deploy.md` §5. Foram feitos para igualar o kernel e o nível de patches a Boston.
9. **Itens da imagem da Hostinger que não são nossos**, presentes também em Boston e mantidos
   como vieram: `monarx-agent`/`monarx-protect`, `sysstat`, `sudoers.d/90-cloud-init-users` e
   `sites-available/default` (fora de `sites-enabled`).
10. **Correção de leitura, não divergência:** no meio da sessão eu li, por engano, uma chave
    `root@srv1982616` como se estivesse autorizada no `sysadmin` de Boston. Era a chave de
    host de Boston, listada no mesmo comando. Reextraído o `authorized_keys`, ele tem 102 bytes
    e uma chave só, a pessoal, como diz a sessão 39.

## Para a Etapa 4

- Atualizar os segredos `DEPLOY_KNOWN_HOSTS` (chave ed25519 acima) e `DEPLOY_HOST` no GitHub
  **antes** de qualquer push na `main`. Com os valores antigos, o deploy aponta para uma máquina
  que não existe mais.
- `criar-ambiente.sh` **não** serve para recriar o ambiente: ele montaria uma homologação
  (senha, noindex, hosts antigos). Banco, `.env`, `site.env`, `painel-config.js`,
  `/etc/laf/staging.conf` (com `PUBLICO=1`), pool do FPM e os quatro hosts do Nginx vêm do
  backup, como estavam em Boston (`docs/deploy.md` §0).
- Habilitar `laf-site@staging`, `laf-queue@staging`, `laf-scheduler@staging` e
  `laf-backup@staging.timer` só depois da release e do `.env` no lugar.
- `docs/deploy.md` §0 ainda descreve o servidor em Boston: IP e o aviso de transferência
  internacional. A política de privacidade e `FORM_CONSENT_TERMS_VERSION` acompanham a troca
  (`docs/relatorio-sessao-39.md`, "Para as próximas etapas").
- Os certificados dependem do DNS apontando para `200.141.22.161`, ou da restauração de
  `letsencrypt.tar.gz`.

## Veredito

**ETAPA 3 CONCLUÍDA.**

`sysadmin` (com `sudo -n`) e `deploy` entram; root e senha são recusados. As versões batem com
Boston, com PHP e Node um patch à frente pela razão da divergência 1. As 29 configurações
conferidas são byte a byte iguais às de Boston. PHP-FPM, PostgreSQL, Redis e Nginx estão
ativos e vazios. ufw e fail2ban estão ativos. As units `laf-*` estão no lugar e desligadas. Tudo
isso foi confirmado de novo depois de um reboot.
