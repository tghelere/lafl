# Sessão 19 — Homologação no ar

Execução de `docs/tarefas/07b-servidor-homologacao-e-deploy-automatico.md`. A sessão 17 tinha
escrito tudo isso sem poder rodar nada — não havia VPS nem domínio. Agora havia. **Esta sessão
é o que aconteceu quando aquele código encontrou um servidor de verdade.**

O resumo curto: homologação está no ar, o deploy automático funciona, e a execução encontrou
**seis defeitos** que nenhuma revisão de código tinha pego — um deles quebrava *todo* deploy,
outro deixaria o servidor sem administrador nenhum.

## O que está no ar

| | |
|---|---|
| VPS | Hostinger, `2.25.223.146`, Ubuntu 24.04 LTS, 8 GB RAM, 96 GB |
| Site | https://homologacao-laf.softhing.com.br (autenticação básica) |
| API | https://api.homologacao-laf.softhing.com.br |
| Painel | https://painel.homologacao-laf.softhing.com.br |
| Publicação | `DEPLOY_STAGING_HABILITADO=true` — push na `main` publica sozinho |
| Produção | não provisionada, como a tarefa pede |

## Os pré-requisitos, como chegaram

| Pré-requisito | Situação encontrada |
|---|---|
| SSH com chave | ok |
| `gh auth login` | ok (`tghelere`, escopos `repo` e `workflow`) |
| Três registros A no DNS | **faltavam** — o servidor autoritativo da zona (registro.br) devolvia NXDOMAIN para todas as variantes |

O DNS foi criado durante a sessão, depois de uma decisão que estava acoplada a ele: o bloco da
tarefa trazia `DOMINIO=homologacao-laf.softhing.com.br`, mas os scripts montavam os hosts de
`staging` como `homologacao.${DOMINIO}` — o que daria
`homologacao.homologacao-laf.softhing.com.br`. Decidido com o Thyago tratar o domínio informado
como a raiz da homologação, e os três hosts saem dele direto.

## Os seis defeitos que só a execução encontrou

**1. O job de publicação do workflow nunca fazia checkout — e isso quebrava TODO deploy.**
Ele envia `infra/publicar.sh` e `infra/reverter.sh` ao servidor a cada publicação, mas `infra/`
não entra no pacote (ADR 0014) e o job não clonava nada: o runner começava com o diretório de
trabalho vazio e o `rsync` morria em "No such file or directory". Não era um problema da
promoção por `run_id` — era de qualquer publicação, sempre. Nenhum deploy jamais teria
funcionado. O checkout entrou como primeiro passo, com `sparse-checkout` de `infra/`: rodá-lo
depois do download apagaria o pacote recém-baixado. (`4086a8a`)

**2. `--trancar-ssh` deixaria o servidor sem administrador nenhum.** Com `PermitRootLogin no`,
o único usuário restante era o `deploy` — cujo `sudo` é uma lista fechada de recarregamentos,
de propósito, porque a chave dele vive num segredo do GitHub. Nada em `criar-ambiente.sh` cabe
naquela lista, então criar produção depois deixaria de ser possível por SSH; o passo a passo do
`docs/deploy.md` mandava justamente isso, numa ordem impossível. Levado ao Thyago, que escolheu
um usuário de administração separado. Entrou o `sysadmin`, com sudo completo e só a chave
pessoal, e `--trancar-ssh` passou a se recusar a rodar se ele não tiver chave ou não estiver no
grupo `sudo`. (`4086a8a`)

Duas armadilhas apareceram ao montá-lo, e as duas teriam passado despercebidas numa leitura:

- O nome `admin` **colide** com um grupo legado do Ubuntu, e o `/etc/sudoers` da distribuição
  dá `%admin ALL=(ALL) ALL` a ele — o usuário ganharia sudo por um caminho que não é o nosso.
  Daí `sysadmin`.
- O `NOPASSWD` não é conveniência. A conta entra só com chave e tem a senha travada
  (`passwd -l`), enquanto a regra padrão do grupo `sudo` **pede senha**. Sem ele o usuário
  estaria no grupo certo e ainda assim não viraria root — exatamente o servidor sem
  administrador que ele existe para evitar. Descoberto testando: `sudo: a password is required`.

**3. `/etc/laf` era criado sem travessia para o `deploy`.** `publicar.sh` roda como `deploy` e
lê `/etc/laf/<ambiente>.conf` a cada publicação, mas o diretório saía `750 root:root`. O
arquivo lá dentro já era 644 — a intenção sempre foi ser legível; faltava a travessia. O deploy
morria com "/etc/laf/staging.conf não existe" logo depois de enviar o pacote. (`019a4b8`)

**4. `http2 on;` não existe no nginx do Ubuntu 24.04.** A diretiva só chegou no nginx 1.25.1; a
distribuição traz 1.24, onde HTTP/2 é parâmetro do `listen` — e esse parâmetro fica obsoleto
justamente na 1.25.1. Não há forma única que sirva às duas, e `provisionar.sh` instala o nginx
estável da distribuição, que muda a cada LTS. A escolha passou a ser feita pela versão
instalada. Na 1.24 o erro não é sutil: derruba o `nginx -t` inteiro, e foi onde a primeira
execução de `criar-ambiente.sh` parou. (`0bb63df`)

**5. O ensaio de reversão terminava gritando que o ambiente estava quebrado.**
`--forcar-falha-de-saude` derrubava todas as checagens da execução, inclusive a que roda
**depois** da reversão. O ensaio terminava sempre em *"a reversão TAMBÉM não passou na
checagem — o ambiente precisa de olho humano AGORA"*, com os três hosts em 200 e o `current` de
volta na release anterior: o alarme mais grave do script, disparado justamente quando tudo deu
certo. O trinco passou a valer uma vez e se desarmar — a checagem posterior à reversão virou
real, que é o que o ensaio existe para demonstrar. Sem ela, o exercício provava que a reversão
troca o link, não que ela devolve um ambiente saudável. (`715bc7a`)

**6. A ordem do `docs/deploy.md` era impossível.** Mandava trancar o SSH e só então rodar
`criar-ambiente.sh` como root — mas é o root por SSH que o trancamento desativa. Trancar virou
o último passo. (`86c0712`)

Fora da tarefa, mas bloqueando tudo: **o CI estava vermelho desde antes desta sessão**, por um
aviso moderado em `devalue` (dependência transitiva do Nitro). O `npm audit` do CI não passa
`--audit-level`, então qualquer severidade derruba o job — e com o CI vermelho o empacotamento
não roda e nada é publicado. Subido o lock de 5.9.0 para 5.9.2. (`71cef94`)

## Decisões tomadas com o Thyago

1. **Hosts na raiz do domínio de homologação**, em vez do prefixo `homologacao.` — com a opção
   `--hosts-na-raiz`, que vale só para `staging`.
2. **Usuário `sysadmin` separado**, em vez de root pelo console da hospedagem ou de
   `PermitRootLogin prohibit-password`.
3. **Sem SMTP por ora:** `MAIL_MAILER=log` em homologação. Nenhum e-mail sai da máquina; o
   reendereçamento por `MAIL_ALWAYS_TO` foi conferido no conteúdo renderizado.

## Decisões tomadas sem consulta

1. **`DEPLOY_STAGING_HABILITADO` foi ligada antes do primeiro deploy, não depois.** O pedido era
   ligá-la ao final, mas o job `publicar` exige a variável **também no disparo manual** — sem
   ela ligada, o botão não publica. Ligar deixou de ser um passo posterior e virou pré-requisito.
   Registrado no `docs/deploy.md`.
2. **Primeiro deploy por promoção (`run_id`)**, e não por um CI novo. O push anterior já tinha
   gerado e validado o pacote; promovê-lo evitou um segundo CI completo e, de quebra, exercitou
   o caminho de promoção — que é exatamente como produção vai ser publicada e que, de outro
   modo, ficaria sem prova nenhuma.
3. **Senha da autenticação básica regerada.** A primeira execução de `criar-ambiente.sh` criou o
   arquivo de senha e morreu depois, no `nginx -t`, sem chegar a imprimi-la — e o `htpasswd`
   guarda só o hash. Apagado o arquivo e rodado de novo, que é o caminho que o próprio script
   documenta.
4. **`LOG_LEVEL` baixado para `debug` e devolvido para `info`**, para conferir o destinatário do
   e-mail. Ver "o que ficou pendente".

## O que foi conferido, e como

Tudo abaixo foi rodado contra o ambiente real, não simulado.

| Verificação pedida | Resultado |
|---|---|
| `curl -I` nos três hosts | HTTP/2 nos três, TLS válido do Let's Encrypt (expira 17/12/2026) |
| Site pedindo autenticação básica | 401 sem credencial, 200 com |
| `X-Robots-Tag: noindex, nofollow` | presente na home **e no 404**; `robots.txt` com `Disallow: /` |
| Login no painel pelo link de definição de senha | entrou — o que prova o modo cookie do Sanctum entre `painel.` e `api.` no domínio real |
| Editar uma página e ver no site | título alterado apareceu na requisição **seguinte** ao site (a invalidação de cache do `SavePage` funciona no ambiente real), e foi revertido |
| Formulário de contato | aceito, job processado pela fila, `To:` **só** `thyagoghelere@hotmail.com`, sem `Cc` nem `Bcc` |
| Falha de saúde e reversão automática | `current` voltou para a release anterior, release defeituosa ficou em disco, três hosts em 200, workflow vermelho |
| Conteúdo de uma release | sem `docs/`, sem `.md`, sem `tests`, sem `e2e`, sem `infra/`, sem `.git`, **zero `.vue`**; o único `.env` é o link para o `shared/` e o `composer.json` é exigência de runtime do Laravel |

Além do que a tarefa pedia:

- **Push trivial na `main` publicou sozinho** (release `20260918-135403-715bc7a`).
- **SSH trancado e conferido do lado de fora:** root recusado (`Permission denied (publickey)`),
  senha recusada, `sysadmin` e `deploy` entrando com chave.
- **`sudo` do `deploy` realmente fechado:** `systemctl reload nginx` passa,
  `systemctl restart postgresql` é negado.
- **Caminho de renovação do certificado provado na prática.** O `certbot renew --dry-run`
  falhou com `rateLimited: Service busy` — limite de taxa do servidor de *testes* do Let's
  Encrypt, não configuração. O risco real era outro: o desafio ACME passa pela autenticação
  básica do site? Posto um arquivo em `/var/www/laf-acme/.well-known/acme-challenge/`, os três
  hosts o serviram **sem senha**, e a raiz do site continuou em 401.
- **Backup disparado uma vez:** dump de 15 KB, 48 comandos de schema/dados, arquivo `0600`,
  retenção aplicada, timer armado para 03:25 UTC.
- **PostgreSQL, Redis e Nitro escutando só em loopback**; `ufw` com 22/80/443; `fail2ban` ativo
  no `sshd` — ativo a ponto de **me banir**: ver abaixo.

## O `fail2ban` funciona, e me baniu

A conferência do trancamento do SSH (tentar `root@`, tentar senha, ver as duas recusadas) é
exatamente o que o `fail2ban` conta como falha de autenticação. Cinco em dez minutos e a
máquina leva uma hora de banimento — foi o que aconteceu com o meu IP no fim da sessão.

O sintoma é `Connection refused` na porta 22, e é **indistinguível, à primeira vista, de ter se
trancado do lado de fora** — que é justamente o medo que o `--trancar-ssh` em duas passadas
existe para evitar. O que separa os dois: a porta 443 continuou aberta, `https://api…/up`
continuou em 200 e o deploy do GitHub Actions rodou depois disso e passou (o runner tem outro
IP). O servidor estava inteiro; só eu estava do lado de fora.

Anotado em `docs/deploy.md` §5, com como distinguir e como soltar
(`fail2ban-client set sshd unbanip SEU_IP`, pelo console da hospedagem). Não desliguei nem
afrouxei nada: o `fail2ban` fez exatamente o trabalho dele.

## O que precisa de você

1. **Link de definição de senha** (24 h, uso único) — entregue fora do repositório. O primeiro
   foi consumido na verificação e a senha temporária que usei fica inválida assim que você
   definir a sua.

2. **Autenticação básica do site de homologação** (usuário `homologacao`) — senha entregue fora
   do repositório, para o cofre.

> **Correção de segurança, sessão 20.** As duas credenciais acima estavam escritas por extenso
> aqui, num arquivo versionado. Foram removidas do texto e entregues por fora. Remover de um
> arquivo não remove do histórico do git: a senha da autenticação básica foi **rotacionada** no
> servidor, e o link de definição de senha precisa ser invalidado por você — ver
> `docs/relatorio-sessao-20.md`, "As duas correções de segurança".

3. **As três chaves.** O classificador de segurança do Claude Code bloqueou a cópia automática
   delas para a sua máquina, duas vezes. Elas estão só no servidor. Rode você mesmo:

   ```bash
   mkdir -p ~/.laf-segredos && chmod 700 ~/.laf-segredos
   ssh sysadmin@2.25.223.146 "sudo grep -E '^(APP_KEY|FIELD_ENCRYPTION_KEY|BLIND_INDEX_KEY|DB_USERNAME|DB_PASSWORD)=' /var/www/laf/staging/shared/.env" \
     > ~/.laf-segredos/homologacao-staging.txt
   chmod 600 ~/.laf-segredos/homologacao-staging.txt
   ```

   `FIELD_ENCRYPTION_KEY` e `BLIND_INDEX_KEY` são **irreversíveis**: perdidas, o dado cifrado
   vira lixo e nenhum backup do banco o traz de volta, porque o backup guarda o texto cifrado,
   não a chave. Cofre de senhas, em lugar **distinto** do backup.

4. **Chave de deploy dedicada** em `~/.ssh/laf-deploy` (privada) — criada nesta sessão e já no
   segredo `DEPLOY_SSH_KEY`. Não a apague.

## O que ficou pendente

- **SMTP.** `MAIL_MAILER=log` em homologação: nenhum e-mail sai da máquina. A entrega real por
  SMTP é a única linha da verificação que não foi exercida — o reendereçamento foi, no conteúdo
  renderizado. Trocar são cinco variáveis no `.env` do servidor.
- **`MAIL_MAILER=log` engole o e-mail em `LOG_LEVEL=info`.** O transporte `log` grava no nível
  **debug**; com o log em `info`, a fila registra `DONE` e não existe arquivo nenhum para olhar.
  Anotado no `.env` do servidor e no `docs/deploy.md` §4.
- **`FORM_RECIPIENT_*` continuam comentados**, esperando endereços reais confirmados da
  instituição. Enquanto isso vale o padrão de `config/forms.php`.
- **Documentos de transparência reais** não foram enviados — entram pelo painel, um a um.
- **Produção não foi provisionada**, como a própria tarefa pede. O passo a passo está no
  `docs/deploy.md` §10, já na ordem corrigida.
- **Restauração de backup nunca foi testada.** "Backup nunca restaurado não é backup"
  (`docs/deploy.md` §14) — e continua valendo.
- **Retenção de backup de produção** segue sem decisão da instituição; 30 dias é um mínimo
  provisório.

## Commits

| | |
|---|---|
| `0bb63df` | `fix(infra): o que o primeiro provisionamento de verdade encontrou` |
| `71cef94` | `fix(site): atualiza devalue para 5.9.2 (GHSA-9rgm-9g3h-6x36)` |
| `4086a8a` | `fix(deploy): o job de publicação não tinha o repositório, e faltava quem administra o servidor` |
| `019a4b8` | `fix(infra): /etc/laf precisa ser atravessável pelo usuário de deploy` |
| `715bc7a` | `fix(deploy): o ensaio de reversão terminava gritando que o ambiente estava quebrado` |
| `86c0712` | `docs(deploy): o servidor como ele ficou, e a ordem que a execução real corrigiu` |

## Árvore

Ficaram fora desta sessão, sem commit, os arquivos de tarefa de sessões futuras
(`docs/tarefas/06-*`, `08-*`, `09-*`): pela regra de `docs/tarefas/README.md`, cada um entra no
primeiro commit da sua própria sessão.
