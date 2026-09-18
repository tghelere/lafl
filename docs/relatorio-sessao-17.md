# Sessão 17 — Servidor, homologação e deploy automático

Execução de `docs/tarefas/07b-servidor-homologacao-e-deploy-automatico.md`.

## O ponto de partida: nada disso pôde ser executado de verdade

O bloco "Preencher antes de rodar (Thyago)" da tarefa chegou **vazio** — sem `DOMINIO`, sem
`IP_DA_VPS`, sem `EMAIL_DE_TESTE`. O `CLAUDE.md` continua registrando o domínio como não
registrado. Dos três pré-requisitos manuais:

| Pré-requisito | Situação |
|---|---|
| SSH com chave para a VPS | **falta** — não há VPS nem IP |
| Três registros A de homologação no DNS | **falta** — não há domínio registrado |
| `gh auth login` feito na máquina | ok (conta `tghelere`, escopos `repo` e `workflow`) |

A tarefa prevê isso: *"Se algum pré-requisito falhar, a sessão registra qual e faz tudo o que
não depende dele (scripts, workflow, documentação), em vez de parar."* Foi o que esta sessão
fez. **Nenhum servidor foi provisionado, nenhum certificado foi emitido, nenhum deploy
aconteceu.** O que existe agora é tudo o que roda quando a VPS existir — escrito, revisado e,
onde deu para conferir sem servidor, conferido.

Ver "O que precisa de conferência humana", no fim, para a lista exata do que continua sem
prova empírica.

---

## Etapa 1 — Provisionamento reproduzível

`infra/provisionar.sh` (commit `76748b8`). Idempotente, roda como root na VPS. Instala e
configura, nas versões do `docker-compose.yml` e do CI: Nginx, PHP-FPM 8.5 (PPA `ondrej/php`)
com as extensões conferidas uma a uma, Node 24, PostgreSQL 16 e Redis escutando só em
`localhost`, Certbot. Segurança: usuário `deploy` sem senha e só com chave, `ufw` 22/80/443,
`fail2ban` no `sshd`, atualizações automáticas só do repositório de segurança e sem reinício
automático.

Três decisões que o script materializa:

**Trancar o SSH é uma segunda passada.** `--trancar-ssh` é separado da passada normal de
propósito: desativar senha e root antes de confirmar que a chave do usuário novo funciona é
trancar a porta com a chave do lado de dentro, e recuperar isso numa VPS depende do console da
hospedagem. O script se recusa a trancar se não houver uma sessão do `deploy` aberta (prova
viva) ou um `--eu-confirmo` explícito, usa `sshd -t` antes de aplicar e `reload` em vez de
`restart`, para não derrubar a sessão que é o caminho de volta.

**O arquivo de hardening do `sshd` é `00-*`, não `99-*`.** No OpenSSH vale o **primeiro** valor
obtido para cada palavra-chave, não o último. O `sshd_config` da distro faz
`Include /etc/ssh/sshd_config.d/*.conf` no topo e o cloud-init costuma deixar ali um
`50-cloud-init.conf` com `PasswordAuthentication yes`. Um `99-*.conf` nosso perderia para ele —
e o script diria que trancou enquanto o servidor continuasse aceitando senha.

**O `sudo` do `deploy` é uma lista fechada** de recarregamentos (`/etc/sudoers.d/laf-deploy`).
A chave que o GitHub Actions usa vai viver num segredo do repositório; `sudo` irrestrito
transformaria qualquer vazamento daquele segredo em root na máquina da instituição.

## Etapa 2 — Ambiente de homologação

`infra/criar-ambiente.sh`, `infra/modelos/` e `infra/backup-banco.sh` (commit `409b0b0`).

Monta um ambiente inteiro: banco e usuário próprios, `.env` gerado com as três chaves, três
hosts no Nginx com TLS, pool de PHP-FPM próprio, três serviços `systemd` e backup diário com
retenção de 7 dias.

**O que nunca é sobrescrito, mesmo rodando de novo:** o `.env` (é onde moram as três chaves —
regerar é perder o dado cifrado), o arquivo da autenticação básica e as releases. **O que é
reescrito sempre:** `site.env` e `painel-config.js`, que só guardam endereço. É o que faz
"rodar de novo conserta" valer sem risco.

Coisas que o desenho obrigou a decidir:

**Redis precisa de índice *e* prefixo por ambiente.** O prefixo padrão do projeto é derivado de
`APP_NAME` e seria idêntico em staging e production. Com um Redis só na máquina, os dois
disputariam as mesmas chaves de sessão e de cache — e o efeito disso é gente deslogando sozinha
em produção quando alguém abre homologação. `REDIS_DB`, `REDIS_CACHE_DB` e `REDIS_PREFIX` saem
diferentes nos dois.

**Certbot roda com `--webroot`, não `--nginx`.** O plugin `nginx` **reescreve** o arquivo do
site; aqui os arquivos de site são gerados a partir de `infra/modelos/nginx-*.conf`, e deixar o
certbot editá-los faria a próxima execução de `criar-ambiente.sh` desfazer o TLS em silêncio. O
TLS ficou num snippet nosso (`snippets/laf-tls.conf`), e não nos arquivos que o plugin instala
em `/etc/letsencrypt` — aqueles só aparecem depois de uma execução com `--nginx`.

**O desafio do ACME tem `auth_basic off`.** Sem isso, a renovação do certificado de homologação
falharia calada até o certificado vencer, porque o host tem autenticação básica.

**No modelo do painel, cache é `expires`, não `add_header Cache-Control`.** No Nginx um
`add_header` dentro de um bloco **descarta todos os herdados** do bloco de cima em vez de somar:
a rota perderia `X-Robots-Tag`, HSTS e os três cabeçalhos comuns de uma vez. `expires` é
diretiva própria e não mexe em nada disso.

**HSTS sem `includeSubDomains`.** Em produção o cabeçalho sai do domínio raiz, e a diretiva
valeria para todo subdomínio dele — inclusive os que a instituição venha a usar para outra coisa
e que não estejam em HTTPS. O navegador guarda isso por um ano e não há como desfazer do lado
dele. Cada host do sistema recebe o cabeçalho por si.

**O backup escreve num arquivo temporário e renomeia só no fim.** Um dump interrompido no meio
(disco cheio, máquina reiniciada) deixaria um `.sql.gz` truncado com nome de backup válido, e a
expurgação por retenção apagaria um backup bom para manter esse.

## Decisão de arquitetura — ADR 0015

A ADR 0014 deixou explícito, para esta tarefa: ou o painel é rebuildado por ambiente, ou passa
a ler a configuração em tempo de execução. O desenho da 07b não deixa escolha — produção
publica **o mesmo pacote já validado em homologação**, e um painel específico do ambiente não é
promovível.

O que decidiu foi o modo de falhar. Um painel de produção construído com `VITE_API_URL` de
homologação **abre normalmente**: a tela pinta, o login pede senha. O que ele faz é autenticar
contra o banco de homologação e editar o conteúdo de homologação, com o endereço de produção na
barra do navegador. Ninguém vê; alguém percebe dias depois que a edição "não aparece no site".

O painel agora lê `window.__LAF_CONFIG__` de `/config.js`, servido fora do bundle e reescrito
no servidor a cada publicação. As `VITE_*` continuam valendo como origem secundária — é o que
mantém `npm run dev` e a bateria de ponta a ponta funcionando sem ninguém reescrever arquivo
nenhum. O `<script>` fica no `<head>` e é clássico: o Vite move o `<script type="module">` para
o `<head>` no build, e deixar o nosso no `<body>` passaria a depender da regra "módulo é
adiado, clássico não" para uma coisa cujo erro é silencioso.

Commits `b63b53d` (painel), `11c76d6` (ADR 0015, nota na 0014, `empacotar.sh` passando a exigir
`painel/config.js`), `b97b5a8` (teste de ponta a ponta).

## Etapa 3 — Deploy automático

`.github/workflows/deploy.yml` e `infra/publicar.sh` (commits `409b0b0` e `b22fe71`).

Push na `main` publica em homologação; tag `v*` publica em produção; o botão publica em
qualquer um dos dois. O CI passou a ser `workflow_call` e **perdeu o gatilho de push na
`main`** — é o deploy que roda na `main` agora, chamando o CI como job. Um workflow não
consegue esperar outro; a alternativa (`workflow_run`) dispara depois, num contexto em que o
commit já pode não ser o mesmo, e publicaria em cima de CI vermelho com dois pushes seguidos.

**A publicação é travada por variável de repositório.** `DEPLOY_STAGING_HABILITADO` e
`DEPLOY_PRODUCTION_HABILITADO` foram criadas, as duas como `false`. É o que permite este
workflow existir antes do servidor existir: o CI e o empacotamento rodam, a publicação é
pulada, e a `main` não fica vermelha a cada push.

**Promoção sem rebuild.** O disparo manual aceita `run_id`: baixa o artefato de uma execução
anterior em vez de empacotar de novo. Com a ADR 0015 o pacote é o mesmo para os dois ambientes,
então promover para produção é promover exatamente o artefato que homologação validou.

No servidor, `publicar.sh` monta a release **inteira** ao lado da que está no ar, aplica
migrations e caches **antes** da troca (uma migration que falhe não chega a mexer no que está no
ar) e só então troca o link — por `mv -T`, que é renomeação e portanto atômica. `ln -sfn` sobre
um link existente **apaga e recria**, e existe um instante em que `current` não aponta para
nada: bastante para o Nginx devolver 404. Depois da troca vem a checagem de saúde; se falhar, o
link volta sozinho para a release anterior e o workflow fica vermelho. A release com defeito
fica em disco, para investigar; o expurgo das antigas só roda depois de a checagem passar.

**A checagem confere a API pela URL pública e o site pela porta de loopback do Nitro.** A API
pública exercita o caminho inteiro (TLS, Nginx, FPM, `.env`, banco). O host público do site, em
homologação, exige autenticação básica — conferi-lo por lá faria a senha circular pelo log do
deploy.

`--forcar-falha-de-saude` existe para exercitar a reversão sem quebrar nada de verdade, e está
exposto como entrada do disparo manual.

## Etapa 4 — Documentação

`docs/deploy.md` reescrito (commit `df2d7b2`): provisionar do zero, ligar a publicação
automática, publicar (push, tag, botão, promoção), reverter à mão, onde ficam os logs, como
reiniciar cada serviço, e o passo a passo de produção para quando o lançamento for decidido.

Corrigido o que estava desatualizado: o agendador é um serviço `systemd` (`schedule:work`) e
não uma linha de `cron`; nenhum dos dois frontends é buildado por ambiente; o pacote não precisa
mais de `VITE_*`. `infra/README.md` cobre a ordem de execução dos scripts.

---

## Decisões tomadas sem consulta

1. **Painel configurado em tempo de execução** em vez de rebuildado por ambiente (ADR 0015). A
   tarefa exige promover o mesmo pacote; a outra saída tornaria isso impossível.
2. **Agendador como serviço `systemd` (`schedule:work`)** em vez do `cron` de minuto em minuto
   que `docs/deploy.md` previa. É o mesmo arranjo do container `scheduler` do
   `docker-compose.yml` e ganha reinício automático de graça.
3. **CI perdeu o gatilho de push na `main`**, e passou a ser chamado pelo deploy. Sem isso, ou o
   CI roda duas vezes por push, ou o deploy publica sem esperar o CI.
4. **`DEPLOY_*_HABILITADO` criadas como `false`** no repositório. Sem esse trinco, todo push na
   `main` deixaria a `main` vermelha até a VPS existir.
5. **Retenção de backup de produção: 30 dias como mínimo provisório.** A tarefa diz que produção
   terá política própria; 30 dias é o que o script usa enquanto a instituição não decidir, e
   está anotado nos dois lugares como decisão pendente, não como escolha.
6. **Ubuntu como distribuição alvo.** `provisionar.sh` recusa rodar em outra e diz por quê: os
   nomes de pacote, o PPA do PHP e a unidade do SSH mudam. Se a VPS vier com Debian, é uma
   adaptação pequena mas precisa ser feita de propósito.

## O que ficou de fora

- **Tudo o que exige o servidor.** Etapas 1 e 2 não foram executadas; a Etapa 3 não teve
  primeiro deploy; a Etapa 4 da tarefa (os comandos `conteudo:importar-inicial` e
  `usuarios:criar-super-admin` em homologação) não aconteceu. Não há link de definição de senha,
  senha de autenticação básica nem chaves para guardar em cofre: **nada disso foi gerado**,
  porque tudo é gerado no servidor.
- **A verificação da tarefa**, inteira: não houve `curl -I` nos três hosts, nem login no painel
  de homologação, nem envio de formulário de teste, nem simulação de falha de saúde contra um
  servidor de verdade, nem listagem do conteúdo de uma release no servidor.
- **Os segredos do repositório** (`DEPLOY_SSH_KEY`, `DEPLOY_HOST`, `DEPLOY_USER`,
  `DEPLOY_KNOWN_HOSTS`) não foram criados: não há host, não há chave de deploy e criar segredo
  com valor inventado é pior que não ter. O passo a passo está em `docs/deploy.md` §5.
- **Produção não foi provisionada**, como a própria tarefa pede.

## O que foi conferido de verdade

- Pint, Larastan, Pest (397 testes, 1078 asserções) e os builds dos dois frontends: verdes.
- Bateria de ponta a ponta: 63 testes verdes, em Firefox de verdade contra a pilha real, mais
  os 2 novos de `/config.js`.
- O teste novo de `/config.js` foi conferido pelo avesso: com `src/config.ts` alterado para
  ignorar `window.__LAF_CONFIG__`, ele fica **vermelho**. Sem essa conferência ele seria
  decorativo, porque em desenvolvimento o valor de execução é vazio e o do build é o certo.
- Substituição dos marcadores dos modelos de Nginx: rodada localmente, os três arquivos saem
  sem nenhum `__MARCADOR__` restante e a autenticação básica entra só no host do site.
- `bash -n` em todos os scripts novos. **Não** foi possível rodar `shellcheck` (ausente nesta
  máquina) nem `nginx -t` (sem Nginx e sem os certificados a que os modelos se referem).

## O que precisa de conferência humana

**No navegador, agora:** nada. O painel não mudou visualmente — a alteração é de onde ele lê a
URL da API, e o caminho interativo está coberto pela bateria de ponta a ponta, que roda o build
de produção num Firefox de verdade. Não houve inspeção manual adicional, e não há o que
inspecionar.

**Quando a VPS e o domínio existirem**, na ordem de `docs/deploy.md` §5, e depois a verificação
que a tarefa pede:

1. `curl -I` nos três hosts: HTTPS válido; o site pedindo autenticação básica (401);
   `X-Robots-Tag: noindex, nofollow` no site de homologação.
2. Login no painel pelo link de definição de senha; editar uma página e ver a alteração no site.
3. Enviar o formulário de contato e confirmar que o e-mail chegou **só** ao endereço de teste.
4. Disparar o workflow com **forçar falha de saúde** e confirmar que a reversão automática
   devolve a release anterior.
5. Listar o conteúdo de uma release no servidor, provando que não há `docs/`, `.md`, testes nem
   código-fonte dos frontends.

**Uma coisa a olhar com atenção no primeiro provisionamento:** o PPA `ondrej/php` precisa ter
PHP 8.5 para a versão do Ubuntu da VPS. Se não tiver, o script falha na conferência das
extensões — e falhar ali é o comportamento certo, mas é uma parada que vale antecipar.

## Árvore

Ficaram fora desta sessão, sem commit, os arquivos de tarefa de sessões futuras
(`docs/tarefas/06-*`, `08-*`, `09-*`): pela regra de `docs/tarefas/README.md`, cada um entra no
primeiro commit da sua própria sessão.

## Commits

| | |
|---|---|
| `76748b8` | `feat(infra): provisionamento reproduzível do servidor` |
| `b63b53d` | `feat(painel): configuração lida em tempo de execução, não gravada no bundle` |
| `11c76d6` | `docs(decisoes): ADR 0015 — painel configurado em tempo de execução` |
| `409b0b0` | `feat(infra): ambiente de homologação — banco, TLS, serviços, backup e publicação` |
| `b22fe71` | `feat(deploy): publicação automática pelo GitHub Actions` |
| `df2d7b2` | `docs(deploy): como publicar, como voltar atrás e como criar produção` |
| `b97b5a8` | `test(e2e): o painel usa mesmo o /config.js, e não o valor do bundle` |
