# Deploy

Como o Lar Anália Franco vai ao ar, o que precisa existir no servidor e em que ordem o
primeiro deploy acontece. O **provisionamento** do servidor (Nginx, systemd, certificados,
firewall, backup) e o **deploy automático** pelo GitHub Actions são a tarefa 07b — este
documento é o que aquela tarefa implementa, e vale sozinho para quem precisar publicar à mão.

Leia junto: `docs/arquitetura.md` (camadas e autenticação),
`docs/decisoes/0014-pacote-de-deploy-minimo.md` (por que o servidor recebe um pacote e não o
repositório) e `docs/protecao-de-dados.md` (chaves e retenção).

---

## 1. O que o servidor precisa ter

As versões são as mesmas do `docker-compose.yml` e do `.github/workflows/ci.yml`. Divergir
aqui é reabrir o buraco que fez o projeto abandonar o SQLite: "verde no CI" só quer dizer
alguma coisa quando o CI roda contra o que produção roda.

| Componente | Versão | Observação |
|---|---|---|
| PHP | **8.5** | FPM. `composer.lock` foi resolvido em 8.5 e trava uma dependência em ">= 8.4.1" |
| Extensões PHP | `pdo_pgsql pgsql mbstring intl exif gd bcmath zip redis` | mesma lista do `docker/php/Dockerfile` e do CI |
| PostgreSQL | **16** | escutando só em `localhost` |
| Redis | **7** | escutando só em `localhost`; cache, sessão e fila |
| Node.js | **24** | só para rodar o Nitro do site (`node site/server/index.mjs`) |
| Composer | 2 | usado só no runner do CI, não no servidor |
| Nginx | estável da distro | TLS pelo Certbot |

Nada é buildado no servidor: `composer install`, `nuxt build` e `vite build` acontecem no CI,
dentro de `scripts/deploy/empacotar.sh`. O servidor recebe o pacote pronto.

## 2. Ambientes

`staging` (homologação) e `production` convivem no mesmo VPS, com **base, usuário de banco,
índice de Redis, `.env` e unidades de systemd separados**. Nada é compartilhado entre os dois
além do sistema operacional.

| | staging | production |
|---|---|---|
| Site | `homologacao.DOMINIO` | `DOMINIO` |
| API | `api.homologacao.DOMINIO` | `api.DOMINIO` |
| Painel | `painel.homologacao.DOMINIO` | `painel.DOMINIO` |
| Banco | `lar_analia_franco_staging` | `lar_analia_franco` |
| `APP_ENV` | `staging` | `production` |
| Indexação | bloqueada (ver §6) | liberada |
| E-mail | tudo para `MAIL_ALWAYS_TO` | destinatários reais |
| Dado real de assistido | **nunca** | sim |

Os três hosts de cada ambiente ficam sob o **mesmo domínio raiz**, exigência do modo cookie do
Sanctum (ver `docs/decisoes/0003-sanctum-cookie-mode.md`).

## 3. Processos permanentes

Quatro por ambiente. Os três primeiros já existem em qualquer deploy Laravel; o quarto e o
quinto são os que costumam faltar, e a falta deles não quebra nada de imediato — só faz
notificação e expurgo nunca acontecerem.

| Processo | Comando | Sem ele |
|---|---|---|
| API | PHP-FPM servindo `backend/public` | a API não responde |
| Site | `node site/server/index.mjs` (Nitro) | o site não responde |
| Painel | Nginx servindo `painel/` como estático | o painel não abre |
| Fila | `php artisan queue:work` | e-mail de formulário fica parado no Redis, para sempre |
| Agendador | `cron` de minuto em minuto chamando `php artisan schedule:run` | expurgo por retenção e expurgo de endereço de coleta nunca rodam (ver `backend/routes/console.php`) |

O painel é SPA: o Nginx precisa devolver `index.html` para qualquer rota que não seja arquivo
existente, senão recarregar a página em `/admin/usuarios` dá 404.

Em desenvolvimento os dois últimos são os containers `queue` e `scheduler` do
`docker-compose.yml` — é a mesma separação, de propósito.

## 4. Checklist de variáveis de ambiente

O `.env` de cada ambiente é criado **no servidor** e nunca sai de lá: não vai para o
repositório, não vai para o GitHub, não entra no pacote (o script apaga qualquer `.env*`).
`backend/.env.example` tem a lista completa comentada; o que segue é o que precisa de decisão
humana.

### Chaves — as três, distintas, uma por ambiente

| Variável | Gerar com |
|---|---|
| `APP_KEY` | `php artisan key:generate` |
| `FIELD_ENCRYPTION_KEY` | `php artisan key:generate --show` |
| `BLIND_INDEX_KEY` | `php artisan key:generate --show` |

> **Perder `FIELD_ENCRYPTION_KEY` ou `BLIND_INDEX_KEY` é irreversível.** Os campos pessoais
> cifrados viram lixo e **nenhum backup do banco os traz de volta** — o backup guarda o texto
> cifrado, não a chave. Guardar as três em cofre de senhas, **em lugar distinto do backup do
> banco**: backup e chave no mesmo lugar significa que quem pegar um pega os dois. Nunca
> reaproveitar a chave de staging em production, nem o `APP_KEY` como qualquer uma das outras
> duas. Rotação: ver `FIELD_ENCRYPTION_PREVIOUS_KEYS` em `docs/protecao-de-dados.md`.

### Domínio e sessão

| Variável | Valor | Se estiver errado |
|---|---|---|
| `SESSION_DOMAIN` | domínio raiz com ponto (`.DOMINIO`) | o login "funciona" e a sessão não gruda: o cookie é emitido e o navegador descarta |
| `SANCTUM_STATEFUL_DOMAINS` | hosts do site e do painel, sem protocolo | a API responde 401 mesmo com cookie válido |
| `CORS_ALLOWED_ORIGINS` | URLs completas do site e do painel | o painel não consegue nem chamar `/csrf-cookie` |
| `TRUSTED_PROXIES` | IP do Nginx/Nitro | todo formulário público é registrado com o IP do proxy, e o limite por IP passa a valer para o servidor inteiro |
| `APP_URL` | `https://api…` | links absolutos e o Scramble saem errados |

### E-mail

`MAIL_MAILER=smtp` e as credenciais do provedor nos dois ambientes.
`FORM_RECIPIENT_PROGRAM_APPLICATION`, `..._PICKUP_REQUEST`, `..._VOLUNTEER_APPLICATION`,
`..._PARTNERSHIP_INQUIRY` e `..._CONTACT_MESSAGE` recebem os endereços **reais** da instituição
nos dois — em staging ninguém recebe nada mesmo assim, por causa de:

`MAIL_ALWAYS_TO` — **só em staging.** Definida, reendereça todo e-mail para esse endereço e
descarta cc e bcc. É o que permite a instituição testar formulário em homologação sem que
chegue mensagem na caixa de ninguém. **Vazia em produção**; preenchida lá, ninguém recebe as
notificações e o sistema parece calado.

Deixar um `FORM_RECIPIENT_*` **definido e vazio** é pior que não defini-lo: o Laravel usa a
string vazia como destinatário em vez de cair no padrão de `config/forms.php` (bug real deste
projeto). Ou preenche, ou comenta a linha.

### URLs usadas em conteúdo

| Variável | Para quê |
|---|---|
| `ADMIN_BASE_URL` | monta o link de `/definir-senha` e o link do e-mail de notificação |
| `SITE_BASE_URL` | monta a URL absoluta da logo no cabeçalho do e-mail |
| `FORM_CONSENT_TERMS_VERSION` | versão do termo gravada a cada envio — mudar quando a política de privacidade mudar |

### Frontends — entram no BUILD, não no `.env` do servidor

O site (Nuxt) lê `NUXT_PUBLIC_*` em **tempo de execução**: `NUXT_PUBLIC_API_URL`,
`NUXT_PUBLIC_SITE_URL`, `NUXT_PUBLIC_ENVIRONMENT` (só `staging`), `NUXT_PUBLIC_UMAMI_WEBSITE_ID`
e `NUXT_PUBLIC_UMAMI_URL` são variáveis de ambiente do processo Nitro. O mesmo `site/` serve
homologação ou produção só trocando isso.

O painel (Vite) grava `VITE_API_URL`, `VITE_SITE_URL` e
`VITE_SESSION_IDLE_TIMEOUT_MINUTES` **dentro do bundle**, em tempo de build — então o `painel/`
do pacote é específico do ambiente e precisa ser reempacotado para produção (ver as
consequências na ADR 0014).

Umami é cookieless e não exige banner de consentimento (ver
`docs/decisoes/0006-umami-em-vez-de-google-analytics.md`); sem as duas variáveis, o script
simplesmente não é injetado.

## 5. Primeiro deploy, na ordem

Depois de o pacote estar no servidor e o `.env` criado. Cada passo depende do anterior.

```bash
cd /var/www/laf/<ambiente>/current/backend

# 1. Estrutura do banco. --force porque em production o Artisan pede confirmação interativa.
php artisan migrate --force

# 2. Papéis. Sem isto o passo 4 se recusa a rodar (não existe super_admin para atribuir).
php artisan db:seed --class=Database\\Seeders\\RoleSeeder --force

# 3. Conteúdo institucional inicial. Só CRIA o que não existe; rodar de novo depois de a
#    instituição editar as páginas não sobrescreve nada (ver ImportInitialPages).
php artisan conteudo:importar-inicial

# 4. Primeira conta capaz de entrar no painel. Pergunta nome e e-mail e imprime o link de
#    definição de senha — nenhuma senha em argumento nem no histórico do shell.
php artisan usuarios:criar-super-admin

# 5. Caches de produção (config, rotas, views). Depois de QUALQUER mudança no .env, repetir.
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

O link impresso no passo 4 vale 24 horas e é de uso único. Entregar por canal privado; não
colar em log de deploy nem em chat de equipe. Perdido ou expirado, gerar outro pelo painel
(a geração invalida o anterior).

**Depois disso, pelo painel, com a conta criada:**

6. **Documentos de transparência reais.** O acervo de exemplo (`TransparencyDocumentsSeeder`,
   PDFs em branco) **não roda** em staging nem em production, de propósito — o que estiver lá
   é o que alguém subiu. Os documentos reais entram pela tela de Transparência, um a um.
7. **Conferir o conteúdo das páginas** e ajustar o que a instituição quiser, pelo painel.

**Conferir que ficou de pé:**

```bash
curl -sI https://api.DOMINIO/up                      # 200
curl -sI https://DOMINIO/ | grep -i x-robots-tag     # produção: nada; homologação: noindex
curl -s  https://DOMINIO/robots.txt                  # produção: Allow; homologação: Disallow
php artisan schedule:list                            # os dois expurgos agendados
```

E, no navegador: entrar no painel pelo link de definição de senha, editar uma página e ver a
alteração no site.

## 6. Homologação não é indexável

Em `staging`, **toda** resposta do site sai com `X-Robots-Tag: noindex, nofollow` e o
`robots.txt` bloqueia o site inteiro — página, arquivo estático, PDF, 404, tudo. Homologação
fica num domínio público com o conteúdo real da instituição; indexada, ela compete com o site
de verdade na busca orgânica, de que a instituição depende.

Quem liga isso é `NUXT_PUBLIC_ENVIRONMENT=staging` no processo do Nitro (ver
`frontend-site/server/plugins/staging-noindex.ts` e o middleware ao lado). É decidido em tempo
de execução: o mesmo `site/` do pacote responde como produção sem a variável. A autenticação
básica do Nginx no host de homologação (tarefa 07b) é a outra metade — as duas juntas, nunca
uma só.

## 7. Cache de HTML e CDN

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

Assets com hash no nome (`/_nuxt/*`, `/assets/*`) podem ser cacheados agressivamente. As fotos
de `/fotos/**` já saem com `Cache-Control: public, max-age=2592000` **sem** `immutable`, de
propósito: o nome do arquivo não tem hash, então trocar a foto mantendo o nome precisa poder
ser visto antes de 30 dias.

## 8. Gerar o pacote

```bash
# Painel: as VITE_* entram no bundle, então precisam estar corretas AQUI, não no servidor.
VITE_API_URL=https://api.DOMINIO \
VITE_SITE_URL=https://DOMINIO \
scripts/deploy/empacotar.sh
```

Sai em `.pacotes/lar-analia-franco-<data>-<commit>/` e no tarball ao lado. O script exige
árvore limpa (o pacote sai de `git archive HEAD`), roda os três builds, poda o que não roda em
servidor e **falha sem gerar nada** se encontrar no pacote qualquer item da lista da ADR 0014.

## 9. Backup

Mínimo, antes de haver dado real de assistido em produção:

- **Banco**: dump diário, retenção a definir com a instituição; restauração testada pelo menos
  uma vez — backup nunca restaurado não é backup.
- **`backend/storage/app`**: onde ficam os PDFs de transparência enviados pelo painel. Não está
  no pacote nem no repositório; só existe no servidor.
- **As três chaves**: em cofre de senhas, fora do servidor e **fora do backup do banco**.

Homologação tem backup diário com 7 dias de retenção (tarefa 07b). Produção precisa de política
própria, decidida com a instituição, antes de receber dado real.
