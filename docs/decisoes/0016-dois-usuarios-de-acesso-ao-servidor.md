# 0016 — Dois usuários de acesso ao servidor, e nenhum deles é root

## Contexto

`infra/provisionar.sh` cria o usuário `deploy` — o que recebe o pacote e publica — e dá a ele
um `sudo` de **lista fechada** (`/etc/sudoers.d/laf-deploy`): só recarregamentos de PHP-FPM,
Nginx e dos três serviços do ambiente. O motivo está na ADR 0014 e em `docs/deploy.md`: a
chave privada desse usuário vive num segredo do repositório (`DEPLOY_SSH_KEY`), ao alcance de
qualquer coisa que consiga rodar um workflow. `sudo` irrestrito ali transformaria um vazamento
daquele segredo em root na máquina da instituição.

A mesma tarefa 07b exige, na Etapa 1, que "login de root e senha por SSH" sejam desativados.

Escrito, isso parecia coerente. **Na primeira execução real (sessão 19) não era.** Com
`PermitRootLogin no`, o único usuário restante na máquina é o `deploy`, e nada do que
administrar um servidor exige cabe naquela lista fechada: `criar-ambiente.sh` escreve unidades
`systemd`, pools de PHP-FPM e arquivos em `/etc/nginx`. O passo a passo do `docs/deploy.md`
chegava a mandar trancar o SSH e **depois** rodar `criar-ambiente.sh` como root — uma ordem
impossível, porque é o root por SSH que o trancamento desativa. Criar o ambiente de produção,
quando chegasse a hora, dependeria do console web da hospedagem.

Três saídas estavam sobre a mesa:

1. **Root por chave** (`PermitRootLogin prohibit-password`). Mantém o passo a passo como está,
   sem código novo — mas não é "login de root desativado", que é o que a tarefa pede.
2. **Root só pelo console da hospedagem.** Literalmente o que a tarefa descreve, zero código —
   e toda administração futura passa por uma janela de navegador que não se roteiriza.
3. **Um usuário humano separado, com sudo completo.**

## Decisão

**A terceira.** Root por SSH fica desativado, e existe um usuário `sysadmin` com sudo completo
e **só a chave pessoal de quem administra**.

| Usuário | Chave | `sudo` | Para quê |
|---|---|---|---|
| `deploy` | a do segredo `DEPLOY_SSH_KEY` | lista fechada de recarregamentos | receber o pacote e publicar |
| `sysadmin` | a chave pessoal de quem administra | completo, `NOPASSWD` | administrar: `criar-ambiente.sh`, reprovisionar, investigar |

Separar os dois é o ponto inteiro. O que dá poder ao `deploy` é uma chave que vive fora da
máquina, num sistema que muita gente pode acionar; o que dá poder ao `sysadmin` é uma chave que
nunca sai do computador de uma pessoa. São superfícies de risco diferentes, e um usuário só não
dá conta das duas sem herdar a pior.

`provisionar.sh --trancar-ssh` passou a **se recusar a rodar** se `deploy` ou `sysadmin`
estiverem sem chave, ou se o `sysadmin` não estiver no grupo `sudo`. É a mesma ideia do
"confirme o acesso antes de trancar a porta", estendida: não basta haver um usuário novo, tem
que haver um usuário novo que consiga administrar.

## Consequências

**Quem tem a chave do `sysadmin` tem root na máquina.** Não há como fingir o contrário: é o
mesmo poder que a opção 1 daria. O que se ganha sobre ela são três coisas concretas — a conta
tem nome (o log diz quem, não "root"), o `sudo` registra cada comando, e revogar um acesso é
apagar uma linha de `authorized_keys` em vez de mexer na conta de root.

**O `NOPASSWD` é obrigatório, não conveniência.** A conta entra só com chave e tem a senha
travada (`passwd -l`), enquanto a regra padrão do grupo `sudo` do Ubuntu **pede senha**. Sem a
linha em `/etc/sudoers.d/laf-sysadmin`, o usuário estaria no grupo certo e ainda assim não
viraria root — o servidor sem administrador que esta ADR existe para evitar. Isso foi
descoberto testando, não lendo: `sudo: a password is required`.

**O nome não pode ser `admin`.** O Ubuntu já traz um grupo `admin` legado, e o `/etc/sudoers`
da distribuição dá `%admin ALL=(ALL) ALL` a ele. Um usuário chamado `admin` colide na criação
do grupo primário e ganharia sudo por um caminho que não é o nosso — duas surpresas num lugar
onde a regra precisa ser exatamente a que está escrita no script.

**A ordem de provisionamento mudou.** Trancar o SSH virou o **último** passo, depois de criar o
ambiente. Daí em diante, criar ambiente é `ssh sysadmin@IP` + `sudo`. Ver `docs/deploy.md` §5 e
§10.

**Se a chave do `sysadmin` for perdida**, o caminho de volta é o console da hospedagem — a
opção 2, que continua existindo como rede de segurança e não como rotina.
