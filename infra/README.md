# infra/ — o servidor, descrito em script

O que existe na VPS do Lar Anália Franco está descrito aqui, em script idempotente, e não na
memória de quem provisionou. Rodar de novo devolve a máquina ao estado descrito nestes
arquivos; se alguém mexeu à mão, a próxima execução desfaz.

**`infra/` é versionado e NÃO entra no pacote de deploy** (ver
`docs/decisoes/0014-pacote-de-deploy-minimo.md`). Os scripts são copiados para o servidor na
hora de usar — o repositório nunca é clonado lá.

| Arquivo | Onde roda | Para quê |
|---|---|---|
| `provisionar.sh` | servidor, como root | base comum: Nginx, PHP-FPM, Node, PostgreSQL, Redis, Certbot, firewall, `fail2ban`, usuário `deploy` |
| `criar-ambiente.sh` | servidor, como root | um ambiente (`staging` ou `production`): banco, `.env`, hosts do Nginx, TLS, serviços, backup |
| `publicar.sh` | servidor, como `deploy` | recebe um pacote, cria a release, troca o link, confere a saúde e **reverte sozinho** se falhar |
| `reverter.sh` | servidor, como `deploy` | volta para a release anterior à mão |
| `backup-banco.sh` | servidor, como root | dump diário do banco do ambiente, com retenção |
| `modelos/` | — | Nginx, unidades `systemd` e pool do PHP-FPM, com marcadores `__ASSIM__` |

## Ordem, na primeira vez

```bash
# Na sua máquina — envie infra/ para o servidor (o repositório NÃO vai junto).
rsync -av --delete infra/ root@IP:/root/laf-infra/

# 1. Base do servidor. Cria o usuário `deploy` com a sua chave.
ssh root@IP '/root/laf-infra/provisionar.sh --chave-publica "'"$(cat ~/.ssh/id_ed25519.pub)"'"'

# 2. CONFIRME o acesso do usuário novo antes de trancar a porta.
ssh deploy@IP 'echo entrou'

# 3. Só agora: desativa login de root e senha por SSH.
ssh root@IP '/root/laf-infra/provisionar.sh --trancar-ssh --eu-confirmo'

# 4. Ambiente de homologação.
ssh root@IP '/root/laf-infra/criar-ambiente.sh --ambiente staging --dominio SEUDOMINIO --email-tls voce@exemplo.org'
```

O passo 3 é separado do 1 de propósito: desativar senha e root antes de confirmar que a
chave do usuário novo funciona é trancar a porta com a chave do lado de dentro, e recuperar
isso numa VPS depende do console da hospedagem.

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
