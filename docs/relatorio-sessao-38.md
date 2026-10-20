# Relatório da sessão 38 — site no ar no domínio definitivo

O sistema de homologação (`homologacao-laf.softhing.com.br`), que o Lar já usava pelo painel,
virou o site público em **`laranaliafrancolondrina.org.br`**: sem senha, indexável, com HTTPS.
Nada foi recriado: mesmo banco, mesmas chaves, mesmo `.env`, mesma release
(`20261010-225934-6d7f01c`). Nenhum `migrate`, nenhum seeder, nenhum deploy. Toda página,
foto, documento, conta e mensagem que o Lar tinha antes da troca continua lá; as contagens
abaixo foram conferidas antes e depois.

| | Antes | Depois |
|---|---|---|
| Site | `homologacao-laf.softhing.com.br` (com senha, noindex) | `laranaliafrancolondrina.org.br` (aberto, indexável) |
| `www` | — | 301 → raiz |
| API | `api.homologacao-laf.softhing.com.br` | `api.laranaliafrancolondrina.org.br` |
| Painel | `painel.homologacao-laf.softhing.com.br` | `painel.laranaliafrancolondrina.org.br` (continua noindex) |
| Domínio antigo | — | 301 para o host equivalente do novo, com caminho e query |

**Decisão de fundo:** o ambiente continua se chamando `staging` no servidor (diretórios,
banco `lar_analia_franco_staging`, unidades systemd, Redis, workflow). Renomear seria mexer em
tudo isso sem ganho nenhum, e foi pedido que não houvesse deploy do zero. O que liga e desliga
homologação nunca foi o nome: é `NUXT_PUBLIC_ENVIRONMENT` (noindex) e a autenticação básica do
nginx, e as duas foram desligadas. `docs/deploy.md` §0 explica isso e as consequências.

## 1. DNS

Conferido no DNS autoritativo (`b.sec.dns.br`), não só no resolvedor local:

| Host | Resolve para |
|---|---|
| `laranaliafrancolondrina.org.br` | `2.25.223.146` |
| `www.laranaliafrancolondrina.org.br` | `2.25.223.146` |
| `painel.laranaliafrancolondrina.org.br` | `2.25.223.146` |
| `api.laranaliafrancolondrina.org.br` | **não existia** no início da sessão; criado pelo Thyago no registro.br durante a sessão, e já visível em 8.8.8.8, 1.1.1.1 e 9.9.9.9 |

O `api` era bloqueante: o painel faz login contra ele, e o cookie do Sanctum exige que os dois
estejam sob o mesmo domínio raiz. Tudo o que não dependia dele (backup, certificados de raiz,
`www` e painel, configs) foi adiantado enquanto o registro não aparecia; a troca só foi aplicada
depois.

A resposta negativa do registro.br dura até 15 minutos (SOA mínimo de 900 s): quem consultou o
`api` antes de ele existir pode ter visto falha por até 15 minutos depois da criação. Já passou.

## 2. Backup, antes de qualquer alteração

Em **`/var/backups/laf/migracao-dominio-20261020/`** no servidor (pasta `700` do root, fora do
alcance da retenção de 7 dias do backup diário, que fica em `/var/backups/laf/staging/`):

| Arquivo | O quê |
|---|---|
| `lar_analia_franco_staging.dump` | `pg_dump -Fc` do banco inteiro. Conferido com `pg_restore -l`: 27 tabelas com dados. No momento: 26 páginas, 7 usuários, 32 imagens |
| `env-staging` | `.env` da API (com as três chaves — por isso a pasta é só do root) |
| `site.env`, `painel-config.js` | configuração dos dois frontends |
| `nginx-etc.tar.gz` | `/etc/nginx` inteiro, inclusive o `laf-staging.htpasswd` que depois foi apagado |
| `letsencrypt-etc.tar.gz` | `/etc/letsencrypt` inteiro (certificados e contas) |
| `systemd-laf.tar.gz` | unidades `laf-*` |
| `storage-app.tar.gz` | `shared/storage/app` — PDFs de transparência e imagens enviadas pelo painel, que não estão em nenhum outro backup (§14 do deploy) |
| `SHA256SUMS` | soma de cada arquivo acima |

O `/etc/laf/staging.conf` original não entrou no backup; as quatro linhas que mudaram nele
estão na seção 6, com o valor antigo.

Restaurar o banco, se um dia for preciso: `docs/deploy.md` §14, trocando o `gunzip | psql` por
`pg_restore -d lar_analia_franco_staging --clean`.

## 3. Nginx e TLS

**Certificados novos** (Let's Encrypt, `--webroot` em `/var/www/laf-acme`, ECDSA, mesma conta
ACME de antes):

- `laranaliafrancolondrina.org.br` — cobre a raiz **e** o `www`;
- `api.laranaliafrancolondrina.org.br`;
- `painel.laranaliafrancolondrina.org.br`.

Os três certificados antigos (`*.homologacao-laf.softhing.com.br`) foram **mantidos e continuam
renovando**: o redirecionamento HTTPS do domínio antigo precisa de certificado válido, senão o
navegador para no aviso de segurança e não segue o 301.

**Arquivos em `/etc/nginx/sites-available/`:**

| Arquivo | O que mudou |
|---|---|
| `laf-staging-site.conf` | `server_name` para a raiz; **sem `auth_basic`**; bloco novo para `www` (443 → 301 raiz); porta 80 da raiz e do `www` → 301 direto para `https://` raiz, num salto só |
| `laf-staging-api.conf` | só host e caminho do certificado |
| `laf-staging-painel.conf` | só host e caminho do certificado; `X-Robots-Tag: noindex, nofollow, noarchive` mantido |
| `laf-staging-dominio-antigo.conf` | **novo**: os três hosts antigos, 80 e 443, `return 301 https://<equivalente>$request_uri`; a porta 80 ainda responde o desafio ACME |

Os quatro foram escritos à mão a partir dos modelos de `infra/modelos/` — o
`criar-ambiente.sh` não roda mais neste ambiente (seção 7).

**Autenticação básica removida:** a diretiva saiu do site e `/etc/nginx/laf-staging.htpasswd`
foi apagado (a cópia está no backup).

**Renovação automática:** `certbot.timer` ativo, com o gancho que recarrega o nginx
(`/etc/letsencrypt/renewal-hooks/deploy/laf-recarregar-nginx.sh`). Resultado do
`certbot renew --dry-run`: **"all simulated renewals succeeded"**, os seis certificados (três
novos e três antigos). Próxima execução do timer no mesmo dia; ele roda duas vezes por dia.

## 4. Indexação

- `NUXT_PUBLIC_ENVIRONMENT` **vazia** em `shared/site.env`: o Nitro deixa de emitir
  `X-Robots-Tag: noindex, nofollow`, e o `robots.txt` passa a ser o de produção.
- `robots.txt` agora:
  ```
  User-agent: *
  Allow: /

  Sitemap: https://laranaliafrancolondrina.org.br/sitemap.xml
  ```
- Nenhuma `<meta name="robots">` nas páginas públicas (as `noindex` de propósito — voluntariado,
  apoiar, `/obrigado/*`, página de erro — continuam como estavam, por decisão anterior).
- **`/quem-somos/o-lar-hoje` continua bloqueada como estava:** a página não existe no banco
  (removida na sessão de 17/09) e responde **404**. Antes ela também tinha o `noindex` geral de
  homologação; agora o que a mantém fora da busca é o 404, que buscador não indexa. Não foi
  acrescentado `Disallow` para ela: um `Disallow` impediria o buscador de ver o 404 e poderia
  deixar a URL listada sem conteúdo.
- **Painel** continua `noindex, nofollow, noarchive` pelo nginx, em toda resposta.

## 5. URLs trocadas

**`shared/.env` (API):**

| Variável | Valor novo |
|---|---|
| `APP_URL` | `https://api.laranaliafrancolondrina.org.br` |
| `SANCTUM_STATEFUL_DOMAINS` | `laranaliafrancolondrina.org.br,painel.laranaliafrancolondrina.org.br` |
| `SESSION_DOMAIN` | `.laranaliafrancolondrina.org.br` |
| `CORS_ALLOWED_ORIGINS` | `https://laranaliafrancolondrina.org.br,https://painel.laranaliafrancolondrina.org.br` |
| `ADMIN_BASE_URL` | `https://painel.laranaliafrancolondrina.org.br` — link do painel no e-mail de notificação e **link de definição de senha** |
| `SITE_BASE_URL` | `https://laranaliafrancolondrina.org.br` — link absoluto dos PDFs de transparência e redirecionamentos de fotos antigas |
| `MAIL_FROM_ADDRESS` | `nao-responda@laranaliafrancolondrina.org.br` |

**`shared/site.env` (Nuxt):** `NUXT_PUBLIC_API_URL`, `NUXT_PUBLIC_SITE_URL` (canônico,
`og:url`, `og:image`, sitemap, robots) e `NUXT_PUBLIC_ENVIRONMENT` vazia.

**`shared/painel-config.js` (painel):** `apiUrl` e `siteUrl`.

O backend **não gera PDF** (não há biblioteca para isso): os PDFs de transparência são arquivos
enviados, e o link para eles sai de `SITE_BASE_URL`. **Umami** não estava configurado
(`NUXT_PUBLIC_UMAMI_*` vazias) e continua sem — ver pendências.

**Banco:** procurado `homologacao-laf` em todas as tabelas (`pg_dump -a | grep`) — nenhuma
ocorrência. O conteúdo das páginas usa caminhos relativos (`/midia/{uuid}`), então nada precisou
ser reescrito no banco.

**Repositório:** fora `docs/relatorio-sessao-*` (histórico, não se reescreve), as ocorrências de
`homologacao-laf`/`softhing.com.br` eram:

- crédito da Softhing no rodapé do site, no login e na barra lateral do painel (mantidos, como
  pedido) e o `LEIA-ME` da marca e a tarefa 04 (os dois sobre o crédito);
- `backend/scripts/midia/levar-fotos.sh` (exemplo de uso) e `docs/deploy.md` — corrigidos;
- `docs/roadmap.md` e `docs/tarefas/07b-…` — mantidos: registram o que foi feito na época.

## 6. Deploy automático

O workflow não conhece domínio nenhum: publica pelo IP (`DEPLOY_HOST`) e a checagem de saúde do
`publicar.sh` confere `https://${HOST_API}/up`, com `HOST_API` lido de **`/etc/laf/staging.conf`**,
no servidor. Esse arquivo foi atualizado:

| Linha | Antes | Depois |
|---|---|---|
| `DOMINIO` | `homologacao-laf.softhing.com.br` | `laranaliafrancolondrina.org.br` |
| `HOST_SITE` | `homologacao-laf.softhing.com.br` | `laranaliafrancolondrina.org.br` |
| `HOST_API` | `api.homologacao-laf.softhing.com.br` | `api.laranaliafrancolondrina.org.br` |
| `HOST_PAINEL` | `painel.homologacao-laf.softhing.com.br` | `painel.laranaliafrancolondrina.org.br` |
| `PUBLICO=1` | — | nova (seção 7) |

**Conferido** como o usuário `deploy`, pelo mesmo caminho do `publicar.sh`:
`API https://api.laranaliafrancolondrina.org.br/up → 200` e o site no loopback → 200. O próximo
push na `main` publica normalmente — e **publica direto no site público** (não há mais
homologação entre a `main` e a instituição). No repositório só mudaram comentários do
`deploy.yml`. A publicação de verdade não foi exercitada nesta sessão (ver pendências).

## 7. Trava contra reconfigurar como homologação (`8ce05b9`)

`infra/criar-ambiente.sh --ambiente staging`, rodado de novo, reescreveria o nginx com a senha,
o `site.env` com `NUXT_PUBLIC_ENVIRONMENT=staging` e os hosts antigos — tiraria o site do ar
para o público e da busca. Agora ele **recusa rodar** quando `/etc/laf/<ambiente>.conf` tem
`PUBLICO=1`, que foi gravado no `staging.conf`. Mesmo commit: `docs/deploy.md` (§0 reescrito,
avisos em §10 e §11, exemplo do §10.2), `CLAUDE.md` (estado do projeto), comentários do
`deploy.yml` e exemplo de `levar-fotos.sh`.

## 8. Caches e serviços

Na ordem, logo depois de trocar `.env`, `site.env` e `painel-config.js`:
`optimize:clear`, `cache:clear` (inclui o cache de 10 minutos das páginas), `config:cache`,
`route:cache`, `view:cache` (como `deploy`, mesmo dono da release); `reload` do `php8.5-fpm`;
`queue:restart`; `restart` de `laf-site@staging`, `laf-queue@staging` e `laf-scheduler@staging`;
`reload` do nginx. Os cinco ativos depois. Dono e permissão de `.env` (`deploy:www-data 640`),
`site.env` e `painel-config.js` preservados.

**Efeito colateral esperado:** quem estava logado no painel antigo precisa entrar de novo no
endereço novo — o cookie de sessão agora é de `.laranaliafrancolondrina.org.br`.

## 9. Testes (curl, contra o servidor de verdade)

| Teste | Resultado |
|---|---|
| `http://laranaliafrancolondrina.org.br/` | 301 → `https://laranaliafrancolondrina.org.br/` |
| `http://www…/quem-somos?x=1` | 301 → `https://laranaliafrancolondrina.org.br/quem-somos?x=1` (um salto) |
| `https://www…/quem-somos?x=1` | 301 → `https://laranaliafrancolondrina.org.br/quem-somos?x=1` |
| `https://laranaliafrancolondrina.org.br/` | 200, sem pedir senha |
| `/quem-somos`, `/transparencia`, `/contato` | 200 |
| `/quem-somos/o-lar-hoje` | 404 |
| `/sitemap.xml` | 200, 48 URLs, todas `https://laranaliafrancolondrina.org.br/…`, nenhuma do domínio antigo |
| `/robots.txt` | 200, `Allow: /` + `Sitemap:` do domínio novo |
| `X-Robots-Tag` em `/`, `/sitemap.xml`, `/robots.txt`, `/transparencia`, `/contato` | ausente |
| `<meta name="robots">` na home | ausente |
| canônico e `og:url` da home / `og:image` | `https://laranaliafrancolondrina.org.br/` / `…/og/og-padrao.png` |
| canônico de `/quem-somos` | `https://laranaliafrancolondrina.org.br/quem-somos` |
| HTML de `/`, `/quem-somos`, `/transparencia`, `/contato` | zero ocorrências de `homologacao-laf` |
| `https://api…/up` | 200 |
| `https://painel…/` | 200, `X-Robots-Tag: noindex, nofollow, noarchive` |
| `https://painel…/config.js` | `apiUrl` e `siteUrl` do domínio novo |
| HSTS | `max-age=31536000` (sem `includeSubDomains`, como antes) |
| `https://homologacao-laf…/transparencia?a=1&b=2` | 301 → `https://laranaliafrancolondrina.org.br/transparencia?a=1&b=2` |
| `http://homologacao-laf…/contato?q=9` | 301 → `https://laranaliafrancolondrina.org.br/contato?q=9` |
| `https://api.homologacao-laf…/up` | 301 → `https://api.laranaliafrancolondrina.org.br/up` |
| `https://painel.homologacao-laf…/admin/paginas?p=2` | 301 → `https://painel.laranaliafrancolondrina.org.br/admin/paginas?p=2` |

**Login no painel (Sanctum em modo cookie):**

1. `GET https://api…/sanctum/csrf-cookie` com `Origin` do painel → 204,
   `Access-Control-Allow-Origin: https://painel.laranaliafrancolondrina.org.br`,
   `Allow-Credentials: true`, cookies `XSRF-TOKEN` e `lar-analia-franco-session` com
   `domain=.laranaliafrancolondrina.org.br; secure; samesite=lax` (a sessão também `httponly`).
2. `POST /api/v1/auth/login` com credencial inexistente → **422 "Credenciais inválidas."** (o
   CSRF passou); sem o token XSRF → **419**; `Origin` estranha → sem `Allow-Origin`.
3. Login de verdade, com uma conta **temporária** sem papel nenhum, criada no servidor com
   senha aleatória que nunca foi impressa: login **200** → `GET /auth/user` só com o cookie
   **200** → logout **204** → `/auth/user` **401**. A conta foi apagada em seguida (de volta a 7
   usuários). Ficaram no log de auditoria as duas entradas automáticas, criação e exclusão de
   "Teste migração de domínio (temporário)" — de propósito: apagar registro de auditoria não é
   algo que se faça para arrumar a casa.

**Formulário público:** `POST https://laranaliafrancolondrina.org.br/api/forms/contato` (o mesmo
caminho do `<form>` da página, que passa pelo proxy do Nitro até `https://api…`) → **303 →
`/obrigado/contato`**, e a mensagem apareceu no banco (`contact_messages.id = 4`). Apagada pelo
modelo logo depois; as 3 mensagens que já existiam continuam lá. A auditoria do contato registra
só status, sem dado pessoal; ficaram as entradas de criação e exclusão.

**No navegador (Playwright):** `https://www.laranaliafrancolondrina.org.br/` terminou em
`https://laranaliafrancolondrina.org.br/` com a home completa (menu, texto, foto da sede), sem
pedir senha. `https://painel.homologacao-laf.softhing.com.br/` terminou em
`https://painel.laranaliafrancolondrina.org.br/login` com a tela de login. O único erro no
console é o 401 de `/auth/user`, que o painel espera quando não há sessão. **Não conferido no
navegador:** entrar no painel com uma conta real e editar uma página. Fica para o Thyago ou
para o Lar, no primeiro acesso.

**Logs depois da troca:** nada novo de erro no `laf-site@staging`, no nginx nem no `laravel.log`.

## 10. Pendências que dependem do Thyago

1. **E-mail não sai da máquina.** `MAIL_MAILER=log` e `MAIL_ALWAYS_TO` preenchido, como em
   homologação. Com o site aberto ao público, **ninguém recebe aviso de formulário novo** — as
   mensagens só aparecem no painel. Para ligar: verificar o domínio de envio no Resend
   (`docs/deploy.md` §4, registros SPF/DKIM/DMARC no registro.br), preencher `RESEND_API_KEY`,
   `MAIL_MAILER=resend`, os `FORM_RECIPIENT_*` reais, e **apagar** `MAIL_ALWAYS_TO` (preenchido,
   todo e-mail vai só para o endereço dele). Depois: `config:cache` e reiniciar fila e
   agendador.
2. **Umami** não está configurado. Criar o site no Umami para o domínio novo e preencher
   `NUXT_PUBLIC_UMAMI_WEBSITE_ID` e `NUXT_PUBLIC_UMAMI_URL` em `shared/site.env`, depois
   `sudo systemctl restart laf-site@staging`.
3. **Google Search Console:** cadastrar `laranaliafrancolondrina.org.br` (propriedade de domínio,
   com TXT no registro.br) e enviar o sitemap. Vale também cadastrar o domínio antigo e usar a
   "mudança de endereço", caso a homologação tenha chegado a ser vista por algum buscador
   (improvável: estava atrás de senha e com noindex).
4. **Retenção do backup.** O backup diário continua com a retenção de homologação, **7 dias**,
   e agora guarda dado de produção. Decidir a política (§14 do deploy) e ajustar
   `RETENCAO_BACKUP_DIAS` em `/etc/laf/staging.conf`.
5. **Dado real de assistido.** A tabela do §2 do deploy diz "nunca" para homologação; este
   ambiente agora é produção. Confirmar com a instituição a partir de quando o painel passa a
   receber dado real — e que a decisão de backup (item 4) vem antes disso.
6. **Primeira publicação pelo pipeline.** A checagem de saúde foi conferida à mão (seção 6),
   mas nenhum deploy rodou depois da troca. O `8ce05b9` mexe em `.github/workflows/deploy.yml`
   e em `infra/`, que não estão no `paths-ignore`, então o próximo `git push` dispara o CI
   completo e publica — é a confirmação final. **Nada foi enviado ao GitHub nesta sessão.**
7. **Avisar o Lar** que o painel mudou de endereço
   (`https://painel.laranaliafrancolondrina.org.br`) e que é preciso entrar de novo. O
   endereço antigo redireciona, então favoritos continuam funcionando.
8. **`APP_ENV` continua `staging`** no `.env` da API. Hoje o efeito prático é só
   `preventLazyLoading` ligado (uma consulta preguiçosa esquecida vira erro 500 em vez de
   passar calada) e o `source_environment` do pacote de conteúdo. Mudar para `production` não
   foi feito, para não mudar comportamento de código no mesmo dia da troca de domínio; avaliar
   numa sessão própria.
9. **Limpeza futura:** `/var/backups/laf/migracao-dominio-20261020/` pode ser apagada quando o
   site estiver estável e houver backups diários suficientes do estado novo — mas não antes de
   decidir o item 4. Os três certificados do domínio antigo podem ser desativados quando o
   redirecionamento não for mais necessário (remover `laf-staging-dominio-antigo.conf` e
   `certbot delete` dos três).

## 11. Fechamento

- Nenhum código de aplicação mudou (nem `backend/app`, nem os dois frontends). Por isso Pint,
  Larastan, Pest e os builds **não foram rodados** nesta sessão. Os dois scripts alterados
  (`infra/criar-ambiente.sh`, `levar-fotos.sh`) passaram por `bash -n`, e a trava `PUBLICO=1`
  foi conferida contra o `staging.conf` real (`grep -qx` casa a linha gravada). O CI do
  próximo push roda tudo.
- Decisões tomadas sem consulta:
  - manter o nome `staging` e não mexer em `APP_ENV` (ADR 0030);
  - não acrescentar `Disallow` para `o-lar-hoje` (seção 4);
  - manter os certificados do domínio antigo;
  - remetente `nao-responda@laranaliafrancolondrina.org.br`, o mesmo padrão que o
    `criar-ambiente.sh` geraria (o `envio.` do §4 do deploy fica para quando o Resend for
    configurado);
  - deixar as entradas de auditoria dos dois testes.
- Arquivos temporários removidos: o script de troca e as configs enviadas para `/tmp/laf-troca`
  no servidor e o pote de cookies local. As configs aplicadas estão em
  `/etc/nginx/sites-available/`.
