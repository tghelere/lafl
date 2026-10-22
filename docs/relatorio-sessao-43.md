# Relatório da sessão 43 — migração para Campinas, Etapa 6: conferência final

Etapa 6 de 6. O DNS já tinha virado para `200.141.22.161` e o deploy de teste pelo GitHub
tinha passado com os segredos novos (Etapa 5). Thyago já tinha conferido à mão o painel
(login, páginas, mensagens de contato legíveis, documentos da transparência) e enviado um
pedido de teste pelo formulário `/contraturno/apoiar`, que apareceu em "Propostas de apoio".

Acesso: `ssh sysadmin@200.141.22.161` + `sudo -n`. Nenhum valor de `.env` nem dado pessoal
aparece neste relatório.

**Sobre o nome da região.** Os relatórios 39 a 42 dizem "São Paulo". A geolocalização do IP
(`ipinfo.io`) devolve `city: Campinas`, `region: São Paulo`, `country: BR`, `AS47583 Hostinger`:
é o mesmo lugar, Campinas, no estado de São Paulo. A documentação passou a dizer "região
Brasil – Campinas". Os relatórios anteriores não foram reescritos.

## 1. Saúde do servidor

**Units** (às 08:07 UTC):

| Unit | Estado | Reinícios |
|---|---|---:|
| laf-site@staging | active / running | 0 desde 07:47 (deploy da Etapa 5) |
| laf-queue@staging | active / running | 0 desde 07:47 |
| laf-scheduler@staging | active / running | 0 desde 07:47 |
| laf-backup@staging.timer | active / waiting | — |
| laf-backup@staging.service | inactive (dead) | é `oneshot`, disparado pelo timer |

`systemctl --failed`: **nenhuma unit**.

**Logs desde a restauração** (22/10, 06:00 UTC em diante):

| Fonte | Achado | Classificação |
|---|---|---|
| journal de `laf-site@staging` | **1** stack trace, 06:30:33: `H3Error: Service Unavailable` (503) em `GET /bazar` | **Esperado, período pré-DNS.** É o 503 do diagnóstico da sessão 42: o SSR buscava a API pelo DNS público, que ainda apontava para Boston. Nenhum outro erro depois dele |
| access log do site | 2 respostas **499** (cliente desistiu): `GET /` às 06:29:59 e às 07:11:40 | **Esperado, período de virada.** A de 06:29:59 é a home de 21 s da sessão 42. A de 07:11:40 veio de um navegador, entre respostas 200, antes dos `resolvectl flush-caches` (07:17 em diante): compatível com o SSR ainda esperando a API pelo IP de Boston no cache do resolvedor. O Nitro não registrou erro para ela, então a causa não é provada, só provável |
| journal de fila, agendador e backup | só o aviso `Unknown key name 'StartLimitIntervalSec' in section 'Service'` (já conhecido, sessão 42); a fila reiniciou às 07:23:58 por `Maximum run time exceeded` | **Inofensivo.** O reinício por tempo máximo é o comportamento configurado do worker |
| `laravel.log` | nenhuma linha nova desde 10/10 | ok |
| `error.log` do Nginx (os quatro de `laf-staging-*` e o geral) | vazios; o geral tem só um `notice` de 05:57 | ok |
| `/var/log/php8.5-fpm.log` | só `NOTICE` de start/reload | ok; `/var/log/php/` sem arquivo de erro do pool |
| access log da API | 25 respostas 404 e uma 400: varredura automática (`wp-includes/wlwmanifest.xml`, `xmlrpc.php`, `/mcp`, `/sse`) e `GET /`; uma 401 em `/api/v1/auth/user` | **Ruído da internet** e uma consulta de sessão antes do login. Nenhum 5xx |

Nenhuma falha de DNS ou de tempo esgotado depois da virada.

**`/etc/hosts`**: só as entradas padrão do cloud-init (`localhost`, IPv6, `ubuntu-24`,
`srv1982616.hstgr.cloud`). **Nenhuma entrada temporária de migração.**

**Resolução no próprio servidor** (`getent ahostsv4`): os quatro nomes do domínio
(`laranaliafrancolondrina.org.br`, `www.`, `api.`, `painel.`) e os três de homologação
resolvem para **`200.141.22.161`**. Do lado de fora, `dig` dá o mesmo, e nenhum dos sete tem
registro AAAA.

## 2. Certificados

`certbot renew --dry-run`: **"Congratulations, all simulated renewals succeeded"**, 6/6:

| Certificado | Vence | Dry-run |
|---|---|---|
| laranaliafrancolondrina.org.br (+ www) | 2027-01-18 | success |
| api.laranaliafrancolondrina.org.br | 2027-01-18 | success |
| painel.laranaliafrancolondrina.org.br | 2027-01-18 | success |
| homologacao-laf.softhing.com.br | 2026-12-17 | success |
| api.homologacao-laf.softhing.com.br | 2026-12-17 | success |
| painel.homologacao-laf.softhing.com.br | 2026-12-17 | success |

`certbot.timer` ativo e hook `renewal-hooks/deploy/laf-recarregar-nginx.sh` presente. Os três
de homologação começam a ser renovados de verdade a partir de 17/11.

## 3. Backup automático

- `laf-backup@staging.timer`: `active`, `enabled`, próximo disparo **23/10 às 03:26 UTC**.
- Dump da Etapa 4, `lar_analia_franco_staging-20261022-063307.sql.gz` (`root:root 600`):
  `gzip -t` ok.
- **`pg_restore -l` não se aplica**: `laf-backup-banco` gera dump **SQL puro**
  (`pg_dump --format=plain --no-owner --no-privileges`, comprimido com gzip), e `pg_restore` só
  lê os formatos custom, diretório e tar. Fiz o teste mais forte no lugar dele: restaurei o dump
  numa base descartável (`laf_verifica_restauracao_s43`) com
  `psql -v ON_ERROR_STOP=1 --single-transaction`:
  - código de saída 0, **nenhuma linha em stderr**;
  - **27 tabelas** restauradas;
  - contagens iguais às do banco em uso, menos duas diferenças explicadas pelo que aconteceu
    depois das 06:33: `activity_log` 196 → 203 (logins, acessos e o envio de teste) e
    `partnership_inquiries` 0 → 1 (o envio de teste);
  - a base descartável foi apagada logo depois (`dropdb`; `pg_database` confirma que não existe).

## 4. Testes externos pelo DNS real

Da máquina local, **sem `--resolve`**. Os redirecionamentos mostram `remote_ip` =
`200.141.22.161`. Os certificados foram validados com Python `ssl` (cadeia e nome, com SNI) e
comparados pela impressão digital com a tabela da sessão 42.

| Teste | Esperado | Obtido | |
|---|---|---|---|
| https://laranaliafrancolondrina.org.br/ | 200, HTML do Nuxt | 200 text/html em 0,10 s, `__nuxt` presente, título certo | ok |
| https://laranaliafrancolondrina.org.br/bazar | 200, título e meta_description da API | 200, os dois presentes | ok |
| https://www.laranaliafrancolondrina.org.br/quem-somos?x=1 | 301 → raiz, mesmo caminho e query | igual | ok |
| http://laranaliafrancolondrina.org.br/bazar | 301 → https | igual | ok |
| http://www.laranaliafrancolondrina.org.br/ | 301 → https | 301 → https://laranaliafrancolondrina.org.br/ | ok |
| https://api.laranaliafrancolondrina.org.br/up | 200 | 200 | ok |
| https://api.laranaliafrancolondrina.org.br/api/v1/public/pages | 200 com dados | 200, 23 páginas | ok |
| https://api.laranaliafrancolondrina.org.br/api/v1/public/transparency-documents | 200 com dados | 200, 18 documentos | ok |
| http://api.laranaliafrancolondrina.org.br/up | 301 → https | igual | ok |
| https://painel.laranaliafrancolondrina.org.br/ | 200, app Vue | 200 text/html, app presente | ok |
| https://painel.laranaliafrancolondrina.org.br/config.js | 200, API certa | 200, `https://api.laranaliafrancolondrina.org.br` (e o site) | ok |
| http://painel.laranaliafrancolondrina.org.br/ | 301 → https | igual | ok |
| https://homologacao-laf.softhing.com.br/algum/caminho?q=1 | 301 → raiz do domínio novo | igual | ok |
| http://homologacao-laf.softhing.com.br/algum/caminho?q=1 | 301 → https do domínio novo | igual | ok |
| https://api.homologacao-laf.softhing.com.br/algum/caminho?q=1 | 301 → api. do domínio novo | igual | ok |
| http://api.homologacao-laf.softhing.com.br/algum/caminho?q=1 | 301 → https | igual | ok |
| https://painel.homologacao-laf.softhing.com.br/algum/caminho?q=1 | 301 → painel. do domínio novo | igual | ok |
| http://painel.homologacao-laf.softhing.com.br/algum/caminho?q=1 | 301 → https | igual | ok |
| certificado de laranaliafrancolondrina.org.br | `BC:5A:98:B0…` | igual, válido até 18/01/2027 | ok |
| certificado de www.laranaliafrancolondrina.org.br | `BC:5A:98:B0…` | igual | ok |
| certificado de api.laranaliafrancolondrina.org.br | `6C:3D:CB:8B…` | igual | ok |
| certificado de painel.laranaliafrancolondrina.org.br | `AF:E0:80:81…` | igual | ok |
| certificado de homologacao-laf.softhing.com.br | `DB:10:61:38…` | igual, válido até 17/12/2026 | ok |
| certificado de api.homologacao-laf.softhing.com.br | `B6:85:5E:48…` | igual | ok |
| certificado de painel.homologacao-laf.softhing.com.br | `87:76:2F:E1…` | igual | ok |
| /midia/f2a8ccc1…/640.webp | 200, sha256 = storage | 200 image/webp, `79b45798ee1a…` = storage | ok |
| /transparencia/documentos/2026/…pdf | 200, sha256 = storage | 200 application/pdf, `4676db2bfbc5…` = storage | ok |

**27/27 ok.** O sha256 do storage foi recalculado no servidor nesta sessão, e é o mesmo da
sessão 42. O PDF foi baixado com User-Agent de robô (`LafMigracaoBot/1.0`): `download_count`
continuou em 1.

## 5. Documentação

- `docs/deploy.md` §0:
  - servidor novo: região Brasil – Campinas, `200.141.22.161`, chave de host ed25519
    `SHA256:yKeeVzuADiEPpDaGJTfRqamdfwQmUYTO/JF/MdSOkeo`;
  - o parágrafo "a máquina fica nos Estados Unidos" virou o histórico da troca;
  - roteiro da migração em seis etapas, com referência aos relatórios 39 a 43;
  - lista de pendências (abaixo).
- `docs/deploy.md` §10.2: o exemplo de `levar-fotos.sh` usa o IP novo.
- `docs/protecao-de-dados.md`: a seção "Transferência internacional" virou "Onde os dados
  ficam". Ela registra o período nos Estados Unidos, o que valia para os consentimentos dados
  nele e a regra de que mudar de região é mudança de política pública.
- `docs/roadmap.md`: a afirmação da política que depende do servidor passou a ser "fica no
  Brasil".
- Três comentários de código justificavam o tratamento de fuso com "o servidor fica nos
  Estados Unidos". Passaram a citar o motivo real, que é o relógio em UTC (`config/app.php`):
  - `backend/app/Support/InstitutionalTime.php`;
  - `frontend-admin/src/types/submission.ts`;
  - `e2e/tests/painel/formularios-data.spec.ts`.

  Também deixavam de apontar para uma seção de documento que não existe mais.

## 6. Política de privacidade

- **`/politica-de-privacidade`**:
  - a seção "Onde os dados ficam, e a transferência internacional" virou "Onde os dados
    ficam";
  - o texto novo diz que o servidor fica no Brasil, no data center brasileiro do provedor, e
    que a hospedagem não envolve transferência internacional;
  - o parágrafo sobre a criptografia continua;
  - saíram a explicação da transferência (art. 33, adequação, consentimento como base) e a
    promessa de "trazer a hospedagem para o Brasil".

  Nada mais na página mudou. A frase "o único terceiro envolvido é a empresa que hospeda o
  servidor — ver 'Onde os dados ficam'" continua valendo.
- **Texto de consentimento dos formulários**: os cinco formulários dizem só "Li e concordo
  com a política de privacidade", sem menção à hospedagem. Não havia o que mudar.
- **Versão**: `2026-09-18` → **`2026-10-22`**, nos cinco lugares que `config/forms.php` e o
  relatório da sessão 20 listam:
  - `VERSAO` da página;
  - o padrão de `config/forms.php`;
  - `.env.example`;
  - `.env.e2e`;
  - `infra/criar-ambiente.sh`.
- **`.env` do servidor**: alterado **antes** do push, porque o `publicar.sh` roda
  `config:cache` a partir dele. Assim, a versão nova entrou em vigor no mesmo instante da página
  nova, e não antes.
  - Cópia prévia: `/var/backups/laf/env-staging-antes-sessao-43`, `root:root 600`, sha256
    idêntico ao original.
  - Só a linha `FORM_CONSENT_TERMS_VERSION` mudou (`diff`: 1 linha), por `sed` ancorado no
    valor antigo exato.
  - Dono e modo foram mantidos (`deploy:www-data 640`).

  | `shared/.env` | sha256 |
  |---|---|
  | antes | `6c5fdfb2189b73c583d18f1d3fb58b5509fd0b2682eafe543131ccaf19857b9f` |
  | depois | `0331d1d74bfb5f62dff060603fc0362385b00bc5c5fbfe1ee02b4176de3ff6ee` |

- **Verificação local**: `e2e/tests/formularios/politica-de-privacidade.spec.ts` lê a versão
  do HTML e compara com o `consent_terms_version` gravado por um envio real. Ele passou com a
  versão nova, junto com os 5 testes de `formularios-data.spec.ts` (6/6). `pint --test` e
  `eslint` passaram nos arquivos alterados.

## 7. Registro de teste em `partnership_inquiries`

Identificação, com a sessão do Postgres em modo só leitura:

- `PartnershipInquiry::count()` = **1**, o único registro da tabela;
- criado em 22/10/2026 às 08:02:57 UTC pelo formulário público, com termo `2026-09-18`;
  uuid começando por `89037898`;
- **`tax_id_hash` preenchido**: 64 caracteres hexadecimais. O CNPJ decifra com a
  `FIELD_ENCRYPTION_KEY`, e o blind index **recalculado** com a `BLIND_INDEX_KEY` do servidor
  novo é igual ao gravado (`hash_equals`). Isso fecha a pendência da sessão 40: as duas chaves
  funcionam na máquina nova, para gravar e para buscar;
- histórico no log: `created` (08:02:57, sem autor, vem do site) e `viewed` (08:03:13, por um
  usuário do painel).

**Exclusão.** O painel não tem rota de exclusão de formulário: o único caminho da aplicação é
o model Eloquent. O trait `LogsActivity` grava o evento `deleted`, que a tela de Auditoria
rotula pelo enum `FormAuditEvent`. Por isso a exclusão foi:

- um `$inquiry->delete()` dentro de `DB::transaction`;
- num script PHP que inicializou a aplicação da release em uso, como `deploy`;
- com uma guarda que abortaria se a tabela não tivesse exatamente um registro, com aquele
  uuid.

Não houve `DELETE` direto no banco. O expurgo por retenção (`PurgeExpiredFormSubmissions`)
**não** serviria: ele apaga por query, sem eventos de model, e não deixaria registro no log.

| Conferência depois | Resultado |
|---|---|
| `PartnershipInquiry::count()` | **0** |
| última entrada do log para o registro | `event = deleted`, rótulo **"Registro excluído"**, 08:24:54 UTC |
| autor da entrada | nenhum: execução por linha de comando, sem usuário autenticado |
| campos guardados na entrada | só `status`, `handled_by` e `handled_at`, os de `logOnly` (nenhum campo pessoal) |
| `activity_log` | 203 → 204 |

**Motivo da exclusão:** o registro foi um envio de teste feito por Thyago, depois da migração,
para provar que o blind index de `tax_id` funciona com as chaves restauradas. Ele não é uma
proposta de apoio real e não deve ficar no banco da instituição pelos 36 meses de retenção.

Como esperado (é a pendência registrada), a entrada "Registro excluído" aparece na tela de
Auditoria com tipo de formulário "—". O tipo é deduzido do `subject`, que deixou de existir.

## 8. Commit, push e deploy

- Commit `ba96b21` (`feat(site): política de privacidade reflete hospedagem no Brasil`) com
  as mudanças dos passos 5 e 6, empurrado para a `main`. O push levou junto os relatórios 39 a
  42, que ainda estavam só locais; como são só `docs/**`, não disparam deploy por si.
- **Workflow "Deploy", run `39747959646`**: `success`, de 08:28:12 a 08:34:18 UTC, com todos os
  jobs verdes:
  - Decidir o ambiente;
  - CI: detectar mudanças, frontend-site, backend (Pint, Larastan, Pest), frontend-admin e e2e
    nas duas partes;
  - Empacotar;
  - Publicar em staging.
- **No servidor**:
  - `current` → `releases/20261022-083411-ba96b21`, e o `RELEASE` registra o commit `ba96b21`;
  - as quatro units estão `active`, sem unit em `--failed`;
  - o journal do site não tem nenhuma linha de erro depois das 08:30;
  - `config("forms.consent_terms_version")` da release publicada é `2026-10-22`, conferido só
    como verdadeiro/falso.
- **No site publicado** (`https://laranaliafrancolondrina.org.br/politica-de-privacidade`, pelo
  DNS real):
  - a página mostra "Versão 2026-10-22";
  - a seção se chama "Onde os dados ficam" e diz "fica no Brasil, no data center brasileiro do
    provedor de hospedagem… a hospedagem não envolve transferência internacional de dados";
  - "Estados Unidos" não aparece mais na página.
- O workflow "Auditoria" do mesmo push falhou, pelo mesmo motivo de antes desta sessão (ver
  "Observações").

## Observações fora do escopo da migração

- **`composer audit` está vermelho na `main`** (workflow "Auditoria") desde antes desta
  sessão: falhou também no push do `3e2eb33`. O alerta é de `symfony/http-foundation`, advisory
  `PKSA-xmwy-rmx5-m7h4` / CVE-2026-108082, ReDoS em `HeaderUtils::split()`. Os dois
  `npm audit` estão verdes. O deploy não depende desse workflow, então ele não bloqueou nada,
  mas é vulnerabilidade numa dependência de produção de um site público. A atualização da
  dependência não foi feita aqui, porque está fora do escopo da etapa.
- **`docs/deploy.md` §0 diz que, com `MAIL_MAILER=log`, o conteúdo do e-mail vai para o
  `laravel.log`.** Não vai: o aviso do envio de teste foi processado pela fila às 08:02:57, e o
  `laravel.log` não ganhou nenhuma linha. O transporte `log` escreve em nível `debug`, e o
  nível configurado do canal não deixa `debug` passar (conferido só como verdadeiro/falso, sem
  ler o valor). Para a privacidade isso é inofensivo, porque o aviso não tem dado pessoal. Mas
  a frase do §0 está errada, e quem procurar o e-mail no log não vai achar.

## Pendências

Registradas em `docs/deploy.md` §0, "Pendências deixadas pela migração":

1. `provisionar.sh` aceitar mais de uma chave por usuário (sessão 41).
2. Usar `rsync -a --chown=root:root` (ou `--no-o --no-g`) nas cópias para `/root` (sessão 41).
3. Remover os hosts de homologação: `laf-staging-dominio-antigo.conf` do Nginx e
   `certbot delete` dos três certificados antigos.
4. Guardar o tipo do formulário no próprio registro do log de auditoria, para que exclusões não
   apareçam com "—".
5. Mover `StartLimitIntervalSec` para `[Unit]` nas três units de `infra/modelos/` (sessão 42).

Nenhuma delas é efeito da migração que esteja quebrado agora. São melhorias de repositório ou
limpeza futura, e o site funciona sem elas.

## Veredito

**ETAPA 6 CONCLUÍDA — MIGRAÇÃO ENCERRADA.**

O servidor de Campinas serve o site, a API e o painel pelo DNS real, 27/27 testes ok,
inclusive o sha256 de mídia e de PDF contra o storage. As units estão ativas e não há falha
nem erro depois da virada do DNS. Os únicos erros do período, um 503 e duas desistências de
cliente, são da janela em que o SSR ainda resolvia a API para Boston. O `/etc/hosts` está
limpo. Os 6 certificados passam no `--dry-run`. O backup diário está agendado, e o dump da
Etapa 4 restaura sem erro. O blind index funciona com as chaves restauradas. O registro de
teste foi excluído pela aplicação, com "Registro excluído" na auditoria. A política de
privacidade e a versão do termo (`2026-10-22`, no repositório e no servidor) refletem a
hospedagem no Brasil e estão publicadas.

As cinco pendências acima ficaram registradas em `docs/deploy.md`, como pedido. São melhorias
de repositório e limpeza futura, não partes da migração que ficaram sem funcionar.

Fora da migração, mas urgente: o `composer audit` da `main` está vermelho por
CVE-2026-108082 em `symfony/http-foundation` (ver "Observações").
