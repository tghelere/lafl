# infra/ — o servidor, descrito em script

O que existe na VPS do Lar Anália Franco está descrito aqui, em script idempotente, e não na
memória de quem provisionou. Rodar de novo devolve a máquina ao estado descrito nestes
arquivos; se alguém mexeu à mão, a próxima execução desfaz.

**`infra/` é versionado e NÃO entra no pacote de deploy** (ver
`docs/decisoes/0014-pacote-de-deploy-minimo.md`). Os scripts são copiados para o servidor na
hora de usar — o repositório nunca é clonado lá.

| Arquivo | Onde roda | Para quê |
|---|---|---|
| `provisionar.sh` | servidor, como root | base comum: Nginx, PHP-FPM, Node, PostgreSQL, Redis, Certbot, firewall, `fail2ban`, usuários `deploy` e `sysadmin` |
| `criar-ambiente.sh` | servidor, como root | um ambiente (`staging` ou `production`): banco, `.env`, hosts do Nginx, TLS, serviços, backup |
| `publicar.sh` | servidor, como `deploy` | recebe um pacote, cria a release, troca o link, confere a saúde e **reverte sozinho** se falhar |
| `reverter.sh` | servidor, como `deploy` | volta para a release anterior à mão |
| `backup-banco.sh` | servidor, como root | dump diário do banco do ambiente, com retenção |
| `modelos/` | — | Nginx, unidades `systemd` e pool do PHP-FPM, com marcadores `__ASSIM__` |

## Ordem, na primeira vez

```bash
# Na sua máquina — envie infra/ para o servidor (o repositório NÃO vai junto).
rsync -av --delete infra/ root@IP:/root/laf-infra/

# 1. Base do servidor. Cria `deploy` (chave do GitHub Actions) e `sysadmin` (sua chave).
ssh root@IP '/root/laf-infra/provisionar.sh \
  --chave-publica       "'"$(cat ~/.ssh/laf-deploy.pub)"'" \
  --chave-publica-admin "'"$(cat ~/.ssh/id_ed25519.pub)"'"'

# 2. CONFIRME os dois acessos novos antes de trancar a porta.
ssh deploy@IP   'echo entrou'
ssh sysadmin@IP 'sudo -n whoami'      # precisa imprimir "root"

# 3. Ambiente de homologação. (--hosts-na-raiz quando a homologação mora em domínio próprio.)
ssh root@IP '/root/laf-infra/criar-ambiente.sh --ambiente staging --dominio SEUDOMINIO \
  --hosts-na-raiz --email-tls voce@exemplo.org --email-de-teste teste@exemplo.org'

# 4. POR ÚLTIMO: desativa login de root e senha por SSH.
ssh root@IP '/root/laf-infra/provisionar.sh --trancar-ssh --eu-confirmo'
```

Trancar vem depois do passo 2 porque desativar senha e root antes de confirmar que a chave
do usuário novo funciona é trancar a porta com a chave do lado de dentro, e recuperar isso
numa VPS depende do console da hospedagem. E vem depois do passo 3 porque o passo 3 roda
como root — daí em diante, `criar-ambiente.sh` se roda por `ssh sysadmin@IP` + `sudo`.

## O que fica onde, no servidor

```
/var/www/laf/<ambiente>/
  releases/<data-hora>-<commit>/   backend/  site/  painel/  RELEASE
  shared/
    .env                 backend — criado NO servidor, nunca sai de lá
    site.env             NUXT_PUBLIC_* lidas pelo systemd do Nitro
    painel-config.js     configuração do painel em tempo de execução (ADR 0015)
    storage/             backend/storage persistente (PDFs de transparência)
  current -> releases/<a que está no ar>

/etc/laf/<ambiente>.conf     variáveis do ambiente, lidas por publicar.sh e backup-banco.sh
/var/backups/laf/<ambiente>/ dumps do banco
/var/www/laf/bin/publicar.sh enviado pelo GitHub Actions a cada publicação
```

## As três chaves

`APP_KEY`, `FIELD_ENCRYPTION_KEY` e `BLIND_INDEX_KEY` são geradas **no servidor**, uma por
ambiente, e nunca saem de lá por nenhum caminho automatizado. `criar-ambiente.sh` as imprime
uma única vez, na saída, para irem para o cofre de senhas — **em lugar distinto do backup do
banco**. Perder as duas últimas é irreversível: o dado cifrado vira lixo e nenhum backup o
traz de volta, porque o backup guarda o texto cifrado, não a chave. Ver
`docs/protecao-de-dados.md`.
