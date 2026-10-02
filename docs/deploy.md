# Deploy

Como o Lar Anália Franco vai ao ar: o que precisa existir no servidor, como se publica, como
se volta atrás e o que fazer quando chegar a hora de criar produção.

O servidor é descrito em script, em `infra/` — não na memória de quem provisionou. Este
documento explica o porquê e a ordem; os scripts são o "como", e rodar de novo qualquer um
deles devolve a máquina ao estado descrito.

Leia junto: `docs/arquitetura.md` (camadas e autenticação),
`docs/decisoes/0014-pacote-de-deploy-minimo.md` (por que o servidor recebe um pacote e não o
repositório), `docs/decisoes/0015-painel-configurado-em-tempo-de-execucao.md` (por que o mesmo
pacote atende os dois ambientes), `docs/decisoes/0016-dois-usuarios-de-acesso-ao-servidor.md`
(quem consegue entrar na máquina, e com qual poder),
`docs/decisoes/0020-resend-via-api-http-em-vez-de-smtp.md` (por que Resend e por que API HTTP)
e `docs/protecao-de-dados.md` (chaves e retenção).

---

## 0. O que está no ar hoje

| | |
|---|---|
| VPS | Hostinger, `2.25.223.146`, Ubuntu 24.04 LTS, **nos Estados Unidos** |
| Homologação | site `homologacao-laf.softhing.com.br`, API `api.homologacao-laf…`, painel `painel.homologacao-laf…` |
| Produção | **não existe** — só é criada quando o lançamento for decidido (ver §10) |
| Publicação automática | ligada para `staging`, desligada para `production` |

Onde estão os segredos deste ambiente: as três chaves e a senha do banco só existem em
`/var/www/laf/staging/shared/.env`, no servidor; a senha da autenticação básica do site
existe só como hash em `/etc/nginx/laf-staging.htpasswd`. Nenhum dos dois é recuperável a
partir do repositório — a cópia de trabalho vive no cofre de senhas.

**A máquina fica nos Estados Unidos**, não no Brasil — o data center brasileiro do provedor
estava indisponível na contratação. Isso não é detalhe de infraestrutura: torna todo dado
enviado pelos formulários do site uma **transferência internacional** sob a LGPD, que
`/politica-de-privacidade` precisa declarar e declara. Ver `docs/protecao-de-dados.md`,
"Transferência internacional". Trocar a região do servidor é mudança de política pública, não
só de infra: exige atualizar aquela página e subir `FORM_CONSENT_TERMS_VERSION`.

Pendência conhecida deste ambiente: **Resend não configurado**. O `.env` está com
`MAIL_MAILER=log`, então nenhum e-mail sai da máquina — o conteúdo renderizado vai para
`storage/logs/laravel.log`, com o destinatário já reescrito por `MAIL_ALWAYS_TO`. Ver §4,
"E-mail (Resend)".

## 1. O que o servidor precisa ter

Instalado por `infra/provisionar.sh`. As versões são as mesmas do `docker-compose.yml` e do
`.github/workflows/ci.yml`. Divergir aqui é reabrir o buraco que fez o projeto abandonar o
SQLite: "verde no CI" só quer dizer alguma coisa quando o CI roda contra o que produção roda.

| Componente | Versão | Observação |
|---|---|---|
| PHP | **8.5** | FPM, do PPA `ondrej/php`. `composer.lock` foi resolvido em 8.5 e trava uma dependência em ">= 8.4.1" |
| Extensões PHP | `pdo_pgsql pgsql mbstring intl exif gd bcmath zip redis` | mesma lista do `docker/php/Dockerfile` e do CI; o script confere uma a uma e falha se faltar |
| PostgreSQL | **16** | escutando só em `localhost` |
| Redis | **7** | escutando só em `localhost`; cache, sessão e fila |
| Node.js | **24** | só para rodar o Nitro do site (`node site/server/index.mjs`) |
| Composer | 2 | usado só no runner do CI, não no servidor |
| Nginx | estável da distro | TLS pelo Certbot, por `--webroot` |

Nada é buildado no servidor: `composer install`, `nuxt build` e `vite build` acontecem no CI,
dentro de `scripts/deploy/empacotar.sh`. O servidor recebe o pacote pronto.

Segurança básica, também do `provisionar.sh`: dois usuários sem senha e só com chave, login
de root e senha por SSH desativados, `ufw` com 22/80/443, `fail2ban` no `sshd`, atualizações
automáticas só do repositório de segurança e sem reinício automático.

### Os dois usuários

| Usuário | Chave | `sudo` | Para quê |
|---|---|---|---|
| `deploy` | a que vive no segredo `DEPLOY_SSH_KEY` do GitHub | **lista fechada** de recarregamentos (`/etc/sudoers.d/laf-deploy`) | receber o pacote e publicar |
| `sysadmin` | a chave pessoal de quem administra | completo, `NOPASSWD` (`/etc/sudoers.d/laf-sysadmin`) | administrar a máquina: `criar-ambiente.sh`, reprovisionar, investigar |

São dois de propósito. O `sudo` do `deploy` é fechado porque a chave dele vive num segredo do
repositório: irrestrito, qualquer vazamento daquele segredo viraria root na máquina da
instituição. Mas com o login de root desativado alguém precisa continuar podendo administrar o
servidor, e nada em `criar-ambiente.sh` cabe naquela lista — daí o `sysadmin`, com a chave
pessoal e mais nada.

> **Quem tem a chave do `sysadmin` tem root na máquina.** O `NOPASSWD` não é conveniência: a
> conta entra só com chave e tem a senha travada (`passwd -l`), e a regra padrão do grupo
> `sudo` do Ubuntu pede senha — sem ele o usuário estaria no grupo certo e ainda assim não
> viraria root, que é o servidor sem administrador que ele existe para evitar. O poder é o
> mesmo de `PermitRootLogin prohibit-password`, com três ganhos: a conta tem nome, o `sudo`
> registra cada comando, e revogar uma chave é apagar uma linha.

O nome é `sysadmin`, e não `admin`, porque o Ubuntu já traz um grupo `admin` legado e o
`/etc/sudoers` da distribuição dá `%admin ALL=(ALL) ALL` a ele: um usuário chamado `admin`
colidiria na criação do grupo primário e ganharia sudo por um caminho que não é o nosso.

O porquê completo, e as duas alternativas descartadas, estão em
`docs/decisoes/0016-dois-usuarios-de-acesso-ao-servidor.md`.

## 2. Ambientes

`staging` (homologação) e `production` convivem no mesmo VPS, com **base, usuário de banco,
índices e prefixo de Redis, `.env`, pool do PHP-FPM e unidades de systemd separados**. Nada é
compartilhado entre os dois além do sistema operacional.

| | staging | production |
|---|---|---|
| Site | `homologacao.DOMINIO` | `DOMINIO` |
| API | `api.homologacao.DOMINIO` | `api.DOMINIO` |
| Painel | `painel.homologacao.DOMINIO` | `painel.DOMINIO` |
| Banco | `lar_analia_franco_staging` | `lar_analia_franco` |
| Usuário do banco | `laf_staging` | `laf_production` |
| Redis | `REDIS_DB=1`, `REDIS_CACHE_DB=2`, prefixo `laf-staging-` | `3`, `4`, prefixo `laf-production-` |
| Porta do Nitro | 3101 | 3001 |
| `APP_ENV` | `staging` | `production` |
| Indexação | bloqueada (ver §11) | liberada |
| Autenticação básica | **só no site** | nenhuma |
| E-mail | tudo para `MAIL_ALWAYS_TO` | destinatários reais |
| Backup | diário, 7 dias | diário, retenção a definir (ver §14) |
| Dado real de assistido | **nunca** | sim |

Os três hosts de cada ambiente ficam sob o **mesmo domínio raiz**, exigência do modo cookie do
Sanctum (ver `docs/decisoes/0003-sanctum-cookie-mode.md`).

> **Homologação em domínio próprio.** A tabela acima supõe a homologação dentro do domínio de
> produção (`homologacao.DOMINIO`). Quando ela mora num domínio dedicado — que é o caso hoje,
> em `homologacao-laf.softhing.com.br` — passe `--hosts-na-raiz` para `criar-ambiente.sh` e os
> três hosts saem da raiz do domínio informado: `DOMINIO`, `api.DOMINIO`, `painel.DOMINIO`.
> Sem a opção, o nome sairia repetido (`homologacao.homologacao-laf…`). A opção vale só para
> `staging`: em produção os hosts já saem da raiz, e o script recusa recebê-la lá.

> **O prefixo do Redis é por ambiente, e isso não é detalhe.** O padrão do projeto é derivado
> de `APP_NAME` e seria idêntico nos dois. Com um Redis só na máquina, staging e production
> disputariam as mesmas chaves de sessão e de cache — e o efeito é gente deslogando sozinha em
> produção quando alguém abre homologação.

## 3. Processos permanentes

Cinco por ambiente. Os três primeiros já existem em qualquer deploy Laravel; o quarto e o
quinto são os que costumam faltar, e a falta deles não quebra nada de imediato — só faz
notificação e expurgo nunca acontecerem.

| Processo | Quem é | Sem ele |
|---|---|---|
| API | pool `laf-<ambiente>` do PHP-FPM, socket próprio | a API não responde |
| Site | `laf-site@<ambiente>.service` (Nitro, em `127.0.0.1:<porta>`) | o site não responde |
| Painel | Nginx servindo `painel/` como estático | o painel não abre |
| Fila | `laf-queue@<ambiente>.service` (`queue:work`) | e-mail de formulário fica parado no Redis, para sempre |
| Agendador | `laf-scheduler@<ambiente>.service` (`schedule:work`) | expurgo por retenção e expurgo de endereço de coleta nunca rodam (ver `backend/routes/console.php`) |

O agendador é `schedule:work` num serviço, e não uma linha de `cron`: é o mesmo arranjo do
container `scheduler` do `docker-compose.yml`, de propósito, e ganha reinício automático de
graça. Quem **executa** os jobs agendados é o worker — o agendador só os despacha; os dois
precisam estar no ar.

O painel é SPA: o Nginx devolve `index.html` para qualquer rota que não seja arquivo existente,
senão recarregar a página em `/admin/usuarios` dá 404.

## 4. Checklist de variáveis de ambiente

O `.env` de cada ambiente é criado **no servidor**, por `infra/criar-ambiente.sh`, e nunca sai
de lá: não vai para o repositório, não vai para o GitHub, não entra no pacote (o script de
empacotamento apaga qualquer `.env*`). `backend/.env.example` tem a lista completa comentada.

O script preenche tudo o que consegue deduzir do ambiente e do domínio. O que sobra para
preencher à mão, depois, é **Resend e destinatários**:

- `RESEND_API_KEY` — uma chave por ambiente (ver "E-mail (Resend)", abaixo).
- `MAIL_MAILER=resend`, `MAIL_FROM_ADDRESS` — remetente no subdomínio de envio verificado.
- `FORM_RECIPIENT_*` — endereços **reais** da instituição nos dois. Em staging ninguém recebe
  nada mesmo assim, por causa do `MAIL_ALWAYS_TO`, e é de propósito que estejam lá: é assim que
  a homologação testa a configuração que vai para produção.

> **`MAIL_MAILER=log` engole o e-mail em `LOG_LEVEL=info`.** O transporte `log` grava no
> nível **debug**; com o log em `info`, a fila registra `DONE` e não existe arquivo nenhum
> para olhar. Para conferir um envio de homologação: baixar `LOG_LEVEL` para `debug`,
> `config:cache`, reiniciar `laf-queue@staging`, enviar, ler `storage/logs/laravel.log` e
> voltar o nível. A armadilha está anotada no próprio `.env` do servidor.

> Deixar um `FORM_RECIPIENT_*` **definido e vazio** é pior que não defini-lo: o Laravel usa a
> string vazia como destinatário em vez de cair no padrão de `config/forms.php` (bug real deste
> projeto). Ou preenche, ou deixa comentado — que é como o script os escreve.

Depois de **qualquer** alteração no `.env`:

```bash
cd /var/www/laf/<ambiente>/current/backend && php8.5 artisan config:cache
sudo systemctl restart laf-queue@<ambiente> laf-scheduler@<ambiente>
```

### As três chaves

| Variável | Gerada por | Perder significa |
|---|---|---|
| `APP_KEY` | `criar-ambiente.sh` | sessões e cookies assinados invalidados |
| `FIELD_ENCRYPTION_KEY` | `criar-ambiente.sh` | **irreversível** |
| `BLIND_INDEX_KEY` | `criar-ambiente.sh` | **irreversível** |

O script gera as três e as imprime **uma única vez**, no fim. Não há como recuperá-las depois
a não ser lendo o `.env` do servidor.

> **Perder `FIELD_ENCRYPTION_KEY` ou `BLIND_INDEX_KEY` é irreversível.** Os campos pessoais
> cifrados viram lixo e **nenhum backup do banco os traz de volta** — o backup guarda o texto
> cifrado, não a chave. Guardar as três em cofre de senhas, **em lugar distinto do backup do
> banco**: backup e chave no mesmo lugar significa que quem pegar um pega os dois. Nunca
> reaproveitar a chave de staging em production. Rotação: ver
> `FIELD_ENCRYPTION_PREVIOUS_KEYS` em `docs/protecao-de-dados.md`.

`criar-ambiente.sh` **nunca sobrescreve um `.env` existente**, justamente por causa disso.

### Domínio e sessão

Preenchidas pelo script a partir de `--dominio`. O que cada uma quebra quando está errada:

| Variável | Se estiver errado |
|---|---|
| `SESSION_DOMAIN` | o login "funciona" e a sessão não gruda: o cookie é emitido e o navegador descarta |
| `SANCTUM_STATEFUL_DOMAINS` | a API responde 401 mesmo com cookie válido |
| `CORS_ALLOWED_ORIGINS` | o painel não consegue nem chamar `/csrf-cookie` |
| `TRUSTED_PROXIES` | todo formulário público é registrado com o IP do proxy, e o limite por IP passa a valer para o servidor inteiro |
| `APP_URL` | links absolutos e o Scramble saem errados |

### E-mail (Resend)

O envio usa o transporte nativo do Laravel para o Resend (`resend/resend-php`), por **API
HTTP** — não SMTP. `MAIL_MAILER=resend` e a chave em `RESEND_API_KEY` (lida por
`config/services.php`, não por `config/mail.php`) são as duas variáveis que ligam o envio; sem
elas o mailer cai no padrão `log` e nenhum e-mail sai da máquina (é o estado de hoje, ver §0).

**Por que API HTTP e não SMTP.** Menos uma porta de saída para liberar no `ufw`/provedor
(443, já aberta, contra 587/465 de SMTP) e uma superfície de configuração menor — chave de
API em vez de host/porta/usuário/senha. A troca não muda nada do lado da aplicação: quem
monta e enfileira o e-mail é sempre `App\Mail\FormSubmissionReceived`, por
`App\Actions\Forms\NotifyFormSubmissionReceived` — só o transporte muda.

**Domínio de envio.** Um **subdomínio dedicado**, nunca o domínio raiz — `envio.SEUDOMINIO`,
por exemplo. Isolar a reputação de entrega do domínio de envio da do domínio principal
(site, e-mail administrativo etc.) é o motivo: se o subdomínio de envio for marcado como
spam em algum momento, o domínio raiz — e o e-mail de quem trabalha na instituição, se usar o
mesmo domínio — não é afetado.

**Remetente sugerido:** `nao-responda@envio.SEUDOMINIO`. Os e-mails de notificação (ver §3 e
`config/forms.php`) não esperam resposta — quem precisa agir abre o link para o painel, não
responde ao e-mail.

**Passo a passo de verificação do domínio, no painel do Resend:**

1. Adicionar o domínio de envio (`envio.SEUDOMINIO`) em Resend → Domains.
2. O Resend gera três registros DNS para cadastrar no provedor de DNS do domínio:
   | Registro | Tipo | Finalidade |
   |---|---|---|
   | SPF | `TXT` | lista o Resend como remetente autorizado do subdomínio |
   | DKIM | `TXT` (ou `CNAME`, conforme o Resend apresentar) | assina cada e-mail; é o que a maioria dos provedores de destino confere para não jogar em spam |
   | DMARC | `TXT`, em `_dmarc.envio.SEUDOMINIO` | declara o que fazer com e-mail que falhar SPF/DKIM — começar em `p=none` (só monitora) e apertar depois |
3. Cadastrar os três exatamente como o Resend apresenta — o valor de DKIM é gerado por
   domínio e não é reaproveitável entre staging e produção nem entre dois domínios.
4. Esperar a propagação (minutos a algumas horas, conforme o TTL do provedor de DNS) e clicar
   em "Verify" no painel do Resend. O status some de "Pending" para "Verified" nos três
   registros — enviar antes disso funciona, mas cai em spam com frequência maior, ou é
   recusado por alguns destinos.
5. Gerar uma **chave de API por ambiente** (Resend → API Keys), com permissão de envio
   (`Sending access`), e preencher `RESEND_API_KEY` no `.env` daquele ambiente. Não
   reaproveitar a chave de staging em produção — mesma razão de nunca reaproveitar
   `FIELD_ENCRYPTION_KEY` entre ambientes: revogar uma não deveria afetar o outro.

Depois de configurado: mesmo checklist de qualquer alteração de `.env` (`config:cache` +
reiniciar fila e agendador, no início desta seção). Conferir um envio de verdade é criar uma
mensagem de contato pelo site e checar se o e-mail chegou ao `FORM_RECIPIENT_CONTACT_MESSAGE`
(em staging, ao endereço de `MAIL_ALWAYS_TO`) — não só o `200` da API.

### Frontends — tudo em tempo de execução

Nenhum dos dois frontends precisa ser rebuildado por ambiente.

**Site (Nuxt).** Lê `NUXT_PUBLIC_*` em tempo de execução, do ambiente do processo Nitro. Ficam
em `/var/www/laf/<ambiente>/shared/site.env`, lido pelo `EnvironmentFile` do systemd:
`NUXT_PUBLIC_API_URL`, `NUXT_PUBLIC_SITE_URL`, `NUXT_PUBLIC_ENVIRONMENT` (só `staging`),
`NUXT_PUBLIC_UMAMI_WEBSITE_ID` e `NUXT_PUBLIC_UMAMI_URL`.

**Painel (Vite).** Lê `window.__LAF_CONFIG__` de `/config.js`, servido fora do bundle. O
arquivo de verdade é `/var/www/laf/<ambiente>/shared/painel-config.js`, e `publicar.sh` aponta
o `painel/config.js` de cada release para ele. Ver
`docs/decisoes/0015-painel-configurado-em-tempo-de-execucao.md` — antes dessa decisão, as
`VITE_*` ficavam gravadas dentro do bundle e um painel de produção construído com a URL de
homologação abria normalmente, editando o conteúdo errado sem nenhum sinal na tela.

Os dois arquivos são **reescritos a cada execução** de `criar-ambiente.sh`, e podem ser: só
guardam endereço, nenhum segredo.

> **Isto vale para SSR, não para rota prerenderizada.** Prerender roda no BUILD e grava no
> pacote o valor que a variável tinha ali — e o pacote é um só para os dois ambientes. Por
> isso nenhuma rota indexável pode ser prerenderizada: o canônico, o `og:url` e o `og:image`
> de toda página são absolutos e saem de `NUXT_PUBLIC_SITE_URL`. Ver
> `docs/decisoes/0018-nenhuma-rota-indexavel-e-prerenderizada.md`, que tem a medição. Sobrou
> na lista de prerender só o que é `noindex` e não emite nenhuma dessas tags.

Umami é cookieless e não exige banner de consentimento (ver
`docs/decisoes/0006-umami-em-vez-de-google-analytics.md`); sem as duas variáveis, o script
simplesmente não é injetado.

## 5. Provisionar o servidor, do zero

Pré-requisitos, fora do Claude Code e fora destes scripts:

1. Acesso SSH **com chave** ao usuário inicial da VPS (a chave pública entra pelo painel da
   hospedagem).
2. No DNS do domínio, registros A apontando para o IP da VPS. Homologação precisa de três:
   `homologacao`, `api.homologacao`, `painel.homologacao`.

```bash
# Envie infra/ para o servidor. O repositório NÃO vai junto (ADR 0014).
rsync -av --delete infra/ root@IP:/root/laf-infra/

# 1. Base do servidor, e cria os dois usuários com a chave de cada um.
ssh root@IP '/root/laf-infra/provisionar.sh \
  --chave-publica       "'"$(cat ~/.ssh/laf-deploy.pub)"'" \
  --chave-publica-admin "'"$(cat ~/.ssh/id_ed25519.pub)"'"'

# 2. CONFIRME os dois acessos novos — em outro terminal, sem fechar o primeiro.
ssh deploy@IP   'echo entrou'
ssh sysadmin@IP 'sudo -n whoami'      # precisa imprimir "root"

# 3. Ambiente de homologação (banco, TLS, serviços, backup). Ainda como root, ou já
#    como `sudo` pelo sysadmin — tanto faz, desde que o passo 2 tenha passado.
ssh root@IP '/root/laf-infra/criar-ambiente.sh \
  --ambiente staging --dominio SEUDOMINIO --hosts-na-raiz \
  --email-tls voce@exemplo.org --email-de-teste teste@exemplo.org'

# 4. POR ÚLTIMO: desativa login de root e senha por SSH.
ssh root@IP '/root/laf-infra/provisionar.sh --trancar-ssh --eu-confirmo'
```

**A ordem importa em dois lugares.**

Trancar o SSH vem **depois** de confirmar os acessos novos (passo 2), e não antes: desativar
senha e root sem saber se a chave do usuário novo funciona é trancar a porta com a chave do
lado de dentro, e recuperar isso numa VPS depende do console da hospedagem. O
`provisionar.sh --trancar-ssh` se recusa a rodar se `deploy` ou `sysadmin` estiverem sem
chave, ou se o `sysadmin` não estiver no grupo `sudo`.

Trancar vem **por último**, depois de criar o ambiente, porque o passo 3 roda como root — e
é justamente o root por SSH que o passo 4 desativa. Depois de trancado, qualquer
`criar-ambiente.sh` novo (produção, por exemplo) passa a ser `ssh sysadmin@IP` +
`sudo /root/laf-infra/criar-ambiente.sh …`.

> **Conferir o trancamento bane o seu IP.** Depois do passo 4, a conferência natural é tentar
> `ssh root@IP` e tentar entrar por senha, e ver as duas serem recusadas. As duas contam como
> falha de autenticação para o `fail2ban`, que está com `maxretry = 5` e `findtime = 10m`: meia
> dúzia de tentativas e a sua máquina leva `bantime = 1h`. O sintoma é **`Connection refused` na
> porta 22**, com a 443 respondendo normalmente e o deploy do GitHub Actions continuando a
> funcionar — o banimento é por IP, e o runner tem outro.
>
> É indistinguível, à primeira vista, de ter se trancado do lado de fora. Para separar os dois:
> se `https://api.DOMINIO/up` responde 200 e uma publicação pelo GitHub Actions passa, o
> servidor está inteiro e o problema é o seu IP. Saídas: esperar a hora passar, entrar de outra
> rede, ou soltar pelo console da hospedagem com
> `fail2ban-client set sshd unbanip SEU_IP`.

O passo 3 imprime, uma única vez, **as três chaves, a senha do banco e a senha da autenticação
básica do site de homologação**. Anote antes de fechar o terminal. Se ele falhar no meio (por
DNS, por exemplo) depois de já ter criado o `.env`, a execução seguinte **não reimprime as
chaves** — elas continuam no `.env` do servidor, e a senha da autenticação básica, que é
guardada só como hash, se recupera apagando `/etc/nginx/laf-<ambiente>.htpasswd` e rodando o
script de novo.

### Ligar a publicação automática

Chave dedicada de deploy — **não** reaproveite a sua chave pessoal. É ela que vai no
`--chave-publica` do passo 1 acima, então gere antes de provisionar:

```bash
ssh-keygen -t ed25519 -f ~/.ssh/laf-deploy -C 'github-actions@laf' -N ''

gh secret   set DEPLOY_SSH_KEY     < ~/.ssh/laf-deploy
gh secret   set DEPLOY_HOST        --body 'IP_OU_HOST'
gh secret   set DEPLOY_USER        --body 'deploy'
gh secret   set DEPLOY_KNOWN_HOSTS --body "$(ssh-keyscan -H IP_OU_HOST 2>/dev/null)"

gh variable set DEPLOY_STAGING_HABILITADO --body true
```

Enquanto `DEPLOY_STAGING_HABILITADO` não for `true`, o workflow roda o CI e o empacotamento
e **pula** a publicação. É o que permite o automatismo existir antes do servidor existir, sem
deixar a `main` vermelha a cada push.

> O trinco vale **também para o disparo manual**: o botão não publica com a variável em
> `false`. Ligar não é um passo posterior ao primeiro deploy — é pré-requisito dele.

`DEPLOY_KNOWN_HOSTS` existe para não usar `StrictHostKeyChecking=no`: sem ele, qualquer coisa
que responda naquele IP receberia o pacote e o comando de deploy.

## 6. Publicar

| Quero | Faço |
|---|---|
| Publicar em homologação | `git push` na `main` |
| Publicar em produção | `git tag v1.0.0 && git push origin v1.0.0` |
| Publicar à mão | Actions → **Deploy** → *Run workflow* → escolher o ambiente |
| Promover para produção o pacote exato que homologação validou | *Run workflow* → ambiente `production` → **run_id** da execução que publicou em homologação |
| Conferir que a reversão automática funciona | *Run workflow* → **forçar falha de saúde** (nunca em produção) |

Em todos os casos a publicação só acontece **depois de todos os jobs do CI passarem** — Pint,
Larastan, Pest, lint e build dos dois frontends e a bateria de ponta a ponta. A auditoria de
dependências (`composer audit`, `npm audit --omit=dev`) **não** bloqueia: roda em
`auditoria.yml` (push na main + segunda-feira) e fica vermelha sozinha; exceções sem correção
ficam, com motivo, em `scripts/ci/npm-audit.mjs`. A única exceção é a promoção por `run_id`, e ela é exceção porque o artefato
promovido veio de uma execução em que tudo isso já passou.

O que acontece no servidor, em ordem (`infra/publicar.sh`):

1. a release nova é montada **inteira**, ao lado da que está no ar, em
   `releases/<data-hora>-<commit>`;
2. `.env`, `storage/` e `painel/config.js` são ligados ao `shared/` do ambiente;
3. `migrate --force` e os caches do Laravel — **antes** da troca, para que uma migration que
   falhe não chegue a mexer no que está no ar;
4. o link `current` muda, por renomeação (`mv -T`), que é atômica: em nenhum instante `current`
   deixa de apontar para uma release válida;
5. PHP-FPM recarrega, `queue:restart`, os três serviços reiniciam;
6. checagem de saúde: `https://api…/up` e a home do Nitro;
7. se a checagem falhar, o link **volta sozinho** para a release anterior, os serviços
   recarregam de novo e o workflow fica vermelho. A release com defeito **fica em disco**, para
   investigar;
8. só depois de a checagem passar, as releases além das 5 últimas são apagadas.

> **Workflow vermelho no passo de publicação significa "está no ar a release ANTERIOR", não
> "o ambiente está quebrado".** A mensagem de erro diz qual dos dois aconteceu.

A checagem confere a API pela URL pública — é o caminho inteiro: TLS, Nginx, FPM, `.env`,
banco — e o site pela porta de loopback do Nitro. O host público de homologação exige
autenticação básica, e conferi-lo por lá faria a senha circular pelo log do deploy.

## 7. Voltar para a release anterior, à mão

Quando o deploy passou na checagem e o defeito apareceu depois, olhando a tela:

```bash
ssh deploy@IP
/var/www/laf/bin/reverter.sh --ambiente staging --listar     # o que existe; → marca a do ar
/var/www/laf/bin/reverter.sh --ambiente staging              # volta uma
/var/www/laf/bin/reverter.sh --ambiente staging --para 20260918-142233-a1b2c3d
```

> **A reversão devolve o CÓDIGO, não o BANCO.** As migrations aplicadas continuam aplicadas.
> É por isso que migration deste projeto é reversível e aditiva (`CLAUDE.md`, regra 9): se uma
> release removeu ou renomeou coluna, a anterior pode não funcionar com o schema novo, e aí o
> caminho é restaurar o backup (§14).

## 8. Logs e reinício

```bash
# Aplicação (Laravel) — é o mesmo arquivo em todas as releases: fica no shared/.
tail -f /var/www/laf/<ambiente>/shared/storage/logs/laravel.log

# Site, fila e agendador
journalctl -u laf-site@<ambiente>      -f
journalctl -u laf-queue@<ambiente>     -f
journalctl -u laf-scheduler@<ambiente> -f

# Nginx, por host
tail -f /var/log/nginx/laf-<ambiente>-{site,api,painel}.{access,error}.log

# PHP-FPM (erro que acontece antes de o Laravel subir)
tail -f /var/log/php/laf-<ambiente>.error.log

# Backup
journalctl -u laf-backup@<ambiente>
systemctl list-timers laf-backup@<ambiente>.timer
```

Job de fila que falhou (esgotou as `--tries=3`) é reportado em `laravel.log` **e** gravado em
`failed_jobs` — é o worker padrão do Laravel, sem nada customizado neste projeto:

```bash
cd /var/www/laf/<ambiente>/current/backend && php8.5 artisan queue:failed   # lista
php8.5 artisan queue:retry <uuid|all>                                       # reprocessa
```

```bash
sudo systemctl restart laf-site@<ambiente>       # site (Nitro)
sudo systemctl restart laf-queue@<ambiente>      # worker da fila
sudo systemctl restart laf-scheduler@<ambiente>  # agendador
sudo systemctl reload  php8.5-fpm                # API
sudo systemctl reload  nginx                     # painel e roteamento
```

Conferir o que está no ar:

```bash
cat /var/www/laf/<ambiente>/current/RELEASE      # commit, data e versões do build
```

## 9. Primeiro deploy, na ordem

Depois de a primeira release estar publicada e o `.env` completo (SMTP e `FORM_RECIPIENT_*`).
Cada passo depende do anterior.

```bash
cd /var/www/laf/<ambiente>/current/backend

# 1. Papéis. Sem isto o passo 3 se recusa a rodar (não existe super_admin para atribuir).
#    As migrations já rodaram: publicar.sh as aplica a cada deploy.
php8.5 artisan db:seed --class=Database\\Seeders\\RoleSeeder --force

# 2. Conteúdo institucional inicial. Só CRIA o que não existe; rodar de novo depois de a
#    instituição editar as páginas não sobrescreve nada (ver ImportInitialPages).
#    PRODUÇÃO NÃO USA ESTE PASSO: lá o conteúdo vem do pacote de homologação (§10.1).
php8.5 artisan conteudo:importar-inicial

# 2b. Fotos do site na biblioteca de imagens, na capa e na galeria das páginas do passo 2.
#     Mesma regra: só cria o que falta, nunca duplica nem pisa no que a equipe mudou.
#     PRODUÇÃO TAMBÉM NÃO USA ESTE PASSO: as fotos viajam no pacote (§10.1).
php8.5 artisan midia:importar-fotos-iniciais

# 3. Primeira conta capaz de entrar no painel. Pergunta nome e e-mail e imprime o link de
#    definição de senha — nenhuma senha em argumento nem no histórico do shell.
php8.5 artisan usuarios:criar-super-admin
```

O link impresso no passo 3 vale 24 horas e é de uso único. Entregar por canal privado; não
colar em log de deploy nem em chat de equipe. Perdido ou expirado, gerar outro pelo painel
(a geração invalida o anterior).

**Depois disso, pelo painel, com a conta criada:**

4. **Documentos de transparência reais.** O acervo de exemplo (`TransparencyDocumentsSeeder`,
   PDFs em branco) **não roda** em staging nem em production, de propósito — o que estiver lá
   é o que alguém subiu. Os documentos reais entram pela tela de Transparência, um a um.
5. **Conferir o conteúdo das páginas** e ajustar o que a instituição quiser, pelo painel.

**Conferir que ficou de pé:**

```bash
curl -sI https://api.DOMINIO/up                       # 200
curl -sI https://HOST_DO_SITE/                        # homologação: 401 sem usuário e senha
# Em homologação, tudo o que for atrás da autenticação básica precisa de -u:
curl -sI -u 'homologacao:SENHA' https://HOST_DO_SITE/            | grep -i x-robots-tag
curl -sI -u 'homologacao:SENHA' https://HOST_DO_SITE/nao-existe  | grep -i x-robots-tag
curl -s  -u 'homologacao:SENHA' https://HOST_DO_SITE/robots.txt  # homologação: Disallow
php8.5 artisan schedule:list                          # os dois expurgos agendados
```

O `X-Robots-Tag` precisa aparecer **também no 404**: quem emite é o Nitro, em toda resposta,
e conferir só a home deixaria de fora justamente as respostas que escapam da navegação.

E, no navegador: entrar no painel pelo link de definição de senha, editar uma página e ver a
alteração no site.

## 10. Criar o ambiente de produção, quando chegar a hora

Produção **não é criada junto com homologação**, de propósito: ela só existe quando o
lançamento for decidido. Quando for:

1. **DNS.** Três registros A novos para o IP da VPS: o apex (`DOMINIO`), `api` e `painel`.
2. **Decidir a política de backup** com a instituição, antes de haver dado real de assistido
   (§14). O padrão do script é 30 dias, que é um mínimo, não uma decisão.
3. **Criar o ambiente.** Root por SSH já está desativado a esta altura, então o caminho é o
   `sysadmin`. Sem `--hosts-na-raiz`: em produção os hosts já saem da raiz do domínio, e o
   script recusa a opção lá.
   ```bash
   rsync -av --delete infra/ sysadmin@IP:/tmp/laf-infra/ && \
   ssh sysadmin@IP 'sudo /tmp/laf-infra/criar-ambiente.sh \
     --ambiente production --dominio SEUDOMINIO --email-tls voce@exemplo.org'
   ```
   Sem `--email-de-teste`: `MAIL_ALWAYS_TO` fica **ausente** em produção. Preenchida lá,
   ninguém recebe as notificações e o sistema parece calado.
4. **Anotar as chaves novas.** São outras — nunca as de homologação.
5. **Completar o `.env`**: SMTP e `FORM_RECIPIENT_*` reais.
6. **Ligar a publicação:** `gh variable set DEPLOY_PRODUCTION_HABILITADO --body true`.
7. **Publicar por promoção**, não por build novo: Actions → Deploy → *Run workflow* →
   `production` → `run_id` da execução que publicou em homologação a versão aprovada. O
   artefato é o mesmo, byte a byte.
8. **Marcar a versão:** `git tag v1.0.0 && git push origin v1.0.0` — a partir daí, tag publica
   em produção sozinha.
9. Levar o conteúdo e abrir o painel: **§10.1, na ordem que está lá** — não o §9 inteiro.
   Confira também que `NUXT_PUBLIC_ENVIRONMENT` está **vazia** em `shared/site.env`:
   preenchida, o site de produção sai da busca, de que a captação da instituição depende.

### 10.1 Como o conteúdo de homologação chega a produção

**O que acontece sozinho quando o ambiente é criado (e o que não).** `criar-ambiente.sh` cria
o banco **vazio** (`createdb`) e `shared/storage/` **vazio** (`install -d`, sem copiar nada);
`publicar.sh` só roda `migrate --force`, nunca `db:seed`, e liga `backend/storage` ao
`shared/storage` do ambiente. Resultado: produção nasce sem página, sem documento e sem PDF.
Nada do que a instituição editou na homologação atravessa sozinho — e o §9 sozinho traria
o texto do repositório (`InitialPages.php`), que é **anterior** às edições feitas pelo painel.
A decisão está no ADR 0023.

**O que entra e o que NÃO entra.**

| Entra (pacote `conteudo:exportar`) | Não entra — de propósito |
|---|---|
| Páginas do CMS: publicadas, rascunhos e arquivadas, com o histórico de slugs (redirects 301) e a capa e a galeria de cada uma (formato 3, ADR 0025) | **Usuários.** As contas de homologação são de teste; em produção cria-se a conta real com `usuarios:criar-super-admin`, e as demais pelo painel |
| Documentos de transparência (publicados e não), com os PDFs | **Formulários recebidos** (candidatura a programa, retirada, voluntariado, parcerias, contato). Em homologação são mensagens de teste, e o dado de quem preencheu não tem por que sair de lá |
| Imagens da biblioteca que podem ir para o site, com original e derivadas | **Imagem marcada como foto de criança ou adolescente atendido** — não pode ir para o site. A exportação **recusa** o pacote se alguma página ainda usar uma delas, no texto, na capa ou na galeria (ADR 0024 e 0025) |
| | Log de auditoria, contador de downloads, itens na lixeira, sessões, filas |

**Nunca restaurar o dump de homologação em produção.** Seria o caminho mais curto e traria
tudo o que a tabela acima deixa de fora, mais as chaves de cifra erradas (cada ambiente tem as
suas). O pacote existe para que não seja preciso.

**Passo a passo.** Homologação e produção estão na mesma VPS, então o pacote passa de uma
pasta para a outra. Como `deploy`:

1. **Congelar o texto.** Combinar com a instituição que ninguém edita em homologação a partir
   daqui, e que a versão aprovada é a que está no ar lá. Edição feita depois do export não vai.
2. **Criar e publicar produção** — itens 1 a 7 do §10 (ambiente, `.env`, ligar a publicação,
   primeira release por promoção).
3. **Papéis em produção:**
   ```bash
   cd /var/www/laf/production/current/backend
   php8.5 artisan db:seed --class=Database\\Seeders\\RoleSeeder --force
   ```
   **Não** rodar `conteudo:importar-inicial` em produção: ele criaria as páginas com o texto do
   repositório, e o passo 6 então as pularia (sem `--substituir`) e o site subiria com o texto
   antigo.
4. **Exportar de homologação:**
   ```bash
   cd /var/www/laf/staging/current/backend
   php8.5 artisan conteudo:exportar /var/tmp/laf-pacote
   # imprime .../conteudo-AAAAMMDD-HHMMSS — anote o caminho
   ```
   O comando falha se algum documento aponta para um PDF que não está no disco.
5. **Levar para produção** (mesma máquina):
   ```bash
   cp -a /var/tmp/laf-pacote/conteudo-AAAAMMDD-HHMMSS /var/tmp/laf-pacote-producao
   ```
   Se um dia os ambientes estiverem em máquinas diferentes, `tar` + `scp`: o pacote é uma pasta
   comum. Não há dado pessoal nele, mas é conteúdo ainda não publicado (rascunhos) — apagar ao
   final (passo 9).
6. **Simular, depois importar** em produção:
   ```bash
   cd /var/www/laf/production/current/backend
   php8.5 artisan conteudo:importar /var/tmp/laf-pacote-producao --simular
   php8.5 artisan conteudo:importar /var/tmp/laf-pacote-producao
   ```
   O comando confere o SHA-256 de cada arquivo e o formato antes de escrever, e grava tudo
   numa transação. Sem `--substituir` ele **nunca sobrescreve nem apaga**; se a saída listar
   algo como "já existia — mantido" num ambiente que devia estar vazio, pare e descubra por
   quê. `--substituir` (pede confirmação; `--force` a dispensa) só serve para um ambiente que
   já tinha conteúdo e deve ser sobrescrito — e nunca apaga o que o pacote não traz.
7. **Criar a primeira conta real:** `php8.5 artisan usuarios:criar-super-admin` (§9, passo 3).
8. **Conferir no ar:** a contagem que o comando imprimiu bate com a do export, uma página
   publicada abre no site e um rascunho só aparece no painel, um PDF baixa pelo link público, `curl -sI https://api.DOMINIO/up` → 200 e
   `robots.txt` **sem** `Disallow: /`.
9. **Limpar:** `rm -rf /var/tmp/laf-pacote /var/tmp/laf-pacote-producao`. Guardar uma cópia do
   pacote em `/var/backups/laf/production/` vale a pena: é o retrato do que foi ao ar.
10. **Só então** marcar a versão (item 8 do §10) — a partir da tag, publicar em produção
    passa a ser automático.

**Depois do lançamento**, produção é a única fonte do conteúdo: quem edita é a instituição,
pelo painel. Homologação vai divergir de produção, e isso é esperado. Repetir o pacote sobre
produção só com `--substituir` e sabendo que sobrescreve o que a instituição editou lá.
Para trazer o conteúdo de produção para homologação (por exemplo, para testar com texto real),
o caminho é o inverso — exportar de produção, importar em homologação com `--substituir`.

### 10.2 Só as fotos, para um ambiente cujo texto não pode mudar

Quando as páginas do ambiente já existem e foram editadas lá (homologação em uso), o §10.1
sobrescreveria o texto ou pularia a página inteira. `midia:importar` lê o mesmo pacote de
`conteudo:exportar`, mas grava **só** a biblioteca de imagens (registro, original e derivadas) e
a capa e a galeria de cada página. Página casa pelo **slug**; a imagem mantém o **uuid** da
origem, que é como o texto cita a foto (`/midia/{uuid}`), então nenhuma referência de texto
precisa de remapeamento e o texto não é gravado. Nunca sobrescreve: imagem que já existe e
página que já tem capa ou galeria ficam como estão. Foto marcada como de assistido não está no
pacote.

```bash
php8.5 artisan midia:importar /var/tmp/PACOTE --simular     # o que seria feito
php8.5 artisan midia:importar /var/tmp/PACOTE               # grava (transação)
php8.5 artisan midia:importar /var/tmp/PACOTE --verificar   # banco, disco (SHA-256) e vínculos
```

Do desenvolvimento local para o servidor, de ponta a ponta (exporta, rsync, backup de `media` e
`page_images`, importa, confere, restaura o backup se a conferência falhar):

```bash
SITE_AUTH='homologacao:SENHA' backend/scripts/midia/levar-fotos.sh --ambiente staging \
  --host 2.25.223.146 --api https://api.homologacao-laf.softhing.com.br \
  --site https://homologacao-laf.softhing.com.br
```

O script usa **uma** conexão SSH (reaproveitada com `ControlMaster`, sem mexer em
`~/.ssh/config`) e para na primeira falha de conexão, por causa do fail2ban. O host vai pelo
IP porque é assim que ele está no `known_hosts`. O backup fica em
`/var/tmp/laf-midia-backup-<carimbo>/` no servidor; apagar quando não for mais preciso.

## 11. Homologação não é indexável

Em `staging`, **toda** resposta do site sai com `X-Robots-Tag: noindex, nofollow` e o
`robots.txt` bloqueia o site inteiro — página, arquivo estático, PDF, 404, tudo. Homologação
fica num domínio público com o conteúdo real da instituição; indexada, ela compete com o site
de verdade na busca orgânica, de que a instituição depende.

Quem liga isso é `NUXT_PUBLIC_ENVIRONMENT=staging` no processo do Nitro (ver
`frontend-site/server/plugins/staging-noindex.ts` e o middleware ao lado). É decidido em tempo
de execução: o mesmo `site/` do pacote responde como produção sem a variável.

A **autenticação básica** do Nginx no host de homologação é a outra metade — as duas juntas,
nunca uma só. A autenticação impede a leitura; o cabeçalho impede a indexação de qualquer coisa
que escape dela. Não existe equivalente na API nem no painel, de propósito: a API é consumida
pelo painel por XHR e uma senha de Nginx quebraria toda requisição; o painel já exige login de
verdade.

O desafio do Certbot (`/.well-known/acme-challenge/`) tem `auth_basic off` — sem isso, a
renovação do certificado de homologação falharia calada até o certificado vencer.

## 12. Cache de HTML e CDN

O painel edita `pages.content`, e por isso **toda rota cujo conteúdo vem do CMS é SSR a cada
requisição** — nenhuma delas é prerenderizada. Salvar pelo painel invalida o cache de 10
minutos do backend (`App\Actions\Content\SavePage` → `Cache::forget`) e a alteração aparece já
na requisição seguinte ao site, sem rebuild. Essa cadeia só continua verdadeira **se nada
acrescentar uma camada de cache de HTML por cima**.

> **Se um CDN (Cloudflare ou outro) for colocado na frente do Nitro, ele não pode cachear o
> HTML dessas rotas.** Por padrão o Cloudflare cacheia só assets por extensão, então isso só
> morde se alguém criar uma regra de "cache everything" — e aí a edição pelo painel "não
> aparece" por horas, com o backend e o Nitro certos, que é o tipo de defeito que ninguém
> encontra olhando o código. Saídas, na ordem: **(1) não cachear HTML dessas rotas**; (2) TTL
> curto (1–5 min) somado a purge por URL no salvamento, o que exigiria o backend chamar a API
> do CDN — dependência e credencial novas, a avaliar só se o tráfego justificar.

Assets com hash no nome (`/_nuxt/*`, `/assets/*`) podem ser cacheados agressivamente. O resto de
`/fotos/` responde 301 para `/midia/` (endereços antigos, ADR 0025) e não deve ganhar regra de
cache própria na CDN além do que o 301 já diz. O mapa de
`/fotos/contato/` (única imagem que sobrou em `public/fotos/`) sai com `Cache-Control: public,
max-age=2592000` **sem** `immutable`, de propósito: o nome do arquivo não tem hash, então
trocá-lo mantendo o nome precisa poder ser visto antes de 30 dias. As fotos vêm da biblioteca,
em `/midia/**`, com `max-age=600, must-revalidate` e ETag (ADR 0024): não cachear por mais
tempo que isso, porque tirar do ar uma foto marcada como de assistido precisa valer em minutos.

`/config.js` e `/index.html` do painel saem com `no-cache` — os dois não têm hash no nome e
mudam a cada publicação; cacheados, o navegador continuaria carregando a configuração do
ambiente anterior.

## 13. Gerar o pacote à mão

```bash
scripts/deploy/empacotar.sh
```

Sem nenhuma variável de ambiente: desde a ADR 0015 o pacote é o mesmo para os dois ambientes.
Sai em `.pacotes/lar-analia-franco-<data>-<commit>/` e no tarball ao lado. O script exige
árvore limpa (o pacote sai de `git archive HEAD`), roda os três builds, poda o que não roda em
servidor e **falha sem gerar nada** se encontrar no pacote qualquer item da lista da ADR 0014.

Para publicar esse pacote sem passar pelo GitHub Actions:

```bash
rsync -az --delete .pacotes/lar-analia-franco-.../ deploy@IP:/var/www/laf/staging/incoming/pacote/
rsync -az infra/publicar.sh deploy@IP:/var/www/laf/bin/
ssh deploy@IP '/var/www/laf/bin/publicar.sh --ambiente staging --pacote /var/www/laf/staging/incoming/pacote'
```

## 14. Backup

`infra/backup-banco.sh`, disparado por `laf-backup@<ambiente>.timer` todo dia de madrugada.
Dump comprimido em `/var/backups/laf/<ambiente>/`, escrito num arquivo temporário e renomeado
só no fim — um dump interrompido no meio não vira um backup aparentemente válido.

| Ambiente | Retenção |
|---|---|
| staging | 7 dias |
| production | **a decidir com a instituição**, antes de haver dado real de assistido. O script usa 30 dias como mínimo até que haja decisão. |

O dump guarda o dado pessoal **já cifrado**. A chave que o abre está no `.env`, não no backup —
e é por isso que `docs/protecao-de-dados.md` exige que as chaves fiquem em cofre de senhas, em
lugar distinto do backup: quem pegar os dois juntos pega tudo; quem pegar só um não pega nada.

Restaurar (destrutivo — confira o ambiente duas vezes):

```bash
sudo systemctl stop laf-queue@<amb> laf-scheduler@<amb>
gunzip -c /var/backups/laf/<amb>/<arquivo>.sql.gz | sudo -u postgres psql -d <banco>
sudo systemctl start laf-queue@<amb> laf-scheduler@<amb>
```

**Backup nunca restaurado não é backup** — a restauração precisa ser testada pelo menos uma vez.

Falta cobrir, além do banco:

- **`backend/storage/app`** (em `shared/storage/app`): onde ficam os PDFs de transparência
  e as imagens da biblioteca (`private/media/`) enviados pelo painel. Não está no pacote de
  deploy nem no repositório; só existe no servidor. O pacote de conteúdo (§10.1) leva uma
  cópia do que é publicável e serve como retrato, mas não é backup, porque não inclui imagem
  marcada nem documento na lixeira.
- **As três chaves**: em cofre de senhas, fora do servidor e **fora do backup do banco**.
