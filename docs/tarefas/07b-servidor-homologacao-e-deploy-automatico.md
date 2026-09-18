> **Modelo recomendado: Opus**

# 07b — Servidor, homologação e deploy automático

Leia `docs/tarefas/README.md`, `docs/deploy.md` e a ADR do pacote de deploy (ambos criados na
07). Rode depois da 07.

## Preencher antes de rodar (Thyago)

```
DOMINIO=                      # ex.: laranaliafrancolondrina.org.br
IP_DA_VPS=
USUARIO_SSH_INICIAL=root      # ou o usuário que a Hostinger criou
EMAIL_DE_TESTE=               # recebe todos os e-mails da homologação
```

Pré-requisitos manuais, fora do Claude Code:

1. `ssh USUARIO_SSH_INICIAL@IP_DA_VPS` funcionando **com chave**, sem pedir senha (a chave
   pública entra pelo painel da Hostinger, em SSH Keys da VPS).
2. No DNS do domínio, três registros A apontando para o IP da VPS:
   `homologacao`, `api.homologacao`, `painel.homologacao`.
3. `gh auth login` feito na máquina (o Claude Code usa o `gh` para criar os segredos do
   repositório).

Se algum pré-requisito falhar, a sessão registra qual e faz tudo o que não depende dele
(scripts, workflow, documentação), em vez de parar.

## Desenho

- **O código não é buildado no servidor.** O GitHub Actions roda os testes, gera o pacote da
  07 e envia só ele. O servidor nunca recebe o repositório.
- **Push em `main` → deploy automático em homologação**, só se todos os jobs do CI passarem.
- **Produção → por tag `v*`** (ex.: `v1.0.0`) ou botão manual no GitHub Actions, com o mesmo
  pacote já validado em homologação. O ambiente de produção só é criado quando o lançamento for
  decidido — nesta tarefa, deixar o workflow pronto e documentado, sem provisionar produção.
- **Releases com troca atômica:** `/var/www/laf/<ambiente>/releases/<data-hora>`,
  `shared/` (`.env`, `storage/`) e o link `current`. Mantém as 5 últimas; se a checagem de saúde
  falhar depois da troca, volta o link para a release anterior sozinho.
- Homologação em `homologacao.DOMINIO` (site), `api.homologacao.DOMINIO` e
  `painel.homologacao.DOMINIO` — mesmo domínio raiz, como o modo cookie do Sanctum exige.

## Etapa 1 — Provisionamento reproduzível

Script idempotente versionado (ex.: `infra/provisionar.sh`, executado via SSH) que instala e
configura, nas versões do CI/compose: Nginx, PHP-FPM com as extensões necessárias, Node,
PostgreSQL e Redis **escutando só em localhost**, Certbot. Segurança básica: usuário `deploy`
sem senha e só com chave, login de root e senha por SSH desativados (só depois de confirmar
que o acesso por chave do usuário novo funciona — nunca trancar a própria porta), `ufw` com
22/80/443, `fail2ban`, atualizações automáticas de segurança do sistema.

`infra/` é versionado, mas não entra no pacote de deploy.

## Etapa 2 — Ambiente de homologação

- Base `lar_analia_franco_staging` com usuário próprio; `.env` de `staging` criado **só no
  servidor** (nunca no GitHub), com chaves novas geradas lá. Guardar uma cópia das três chaves
  num arquivo local fora do repositório e informar o caminho no relatório — o Thyago transfere
  isso para um cofre de senhas.
- Nginx: os três hosts com HTTPS (Certbot). **Autenticação básica (usuário e senha) só no host
  do site de homologação** — o painel já exige login, e a API não é navegável; autenticação
  básica na API quebraria as requisições do painel. Usuário e senha da autenticação básica no
  relatório, fora do repositório.
- Serviços `systemd`: Nitro do site, worker da fila e scheduler, com reinício automático.
- Backup diário do banco de homologação com retenção de 7 dias (produção terá política própria,
  documentada, quando existir).

## Etapa 3 — Deploy automático

- Workflow no GitHub Actions: jobs de CI existentes → empacotamento → envio por `rsync` sobre
  SSH com chave dedicada de deploy (segredo do repositório criado via `gh secret set`) → no
  servidor: `migrate --force`, caches do Laravel, troca do link, recarga do PHP-FPM, reinício do
  Nitro, `queue:restart`, checagem de saúde (`/up` da API e a home do site), reversão automática
  se falhar.
- Primeiro deploy manual pelo mesmo workflow (botão), depois conferir que um push trivial em
  `main` publica sozinho.
- Depois do primeiro deploy: `conteudo:importar-inicial` e `usuarios:criar-super-admin` em
  homologação (e-mail do Thyago); link de definição de senha no relatório.

## Etapa 4 — Documentação

Atualizar `docs/deploy.md` com: como fazer deploy (push em `main`), como publicar em produção
(tag), como voltar para a release anterior manualmente, onde ficam logs, como reiniciar cada
serviço, e o passo a passo para criar o ambiente de produção quando chegar a hora.

## Verificação

- `curl -I` nos três hosts: HTTPS válido; site pedindo autenticação básica; `X-Robots-Tag:
  noindex, nofollow` no site de homologação.
- Login no painel de homologação pelo link de definição de senha; editar uma página e ver a
  alteração no site; enviar o formulário de contato e confirmar que o e-mail chegou só ao
  `EMAIL_DE_TESTE`.
- Simular falha de checagem de saúde num deploy e confirmar a reversão automática.
- Listar no relatório o conteúdo de uma release no servidor, provando que não há `docs/`,
  `.md`, testes nem código-fonte dos frontends.
