# Sessão 20 — Política de privacidade fiel ao sistema

Execução de `docs/tarefas/08-politica-de-privacidade.md`, mais duas correções de segurança
pedidas no fim da sessão.

A regra da tarefa é dura e vale repetir: **cada afirmação da política precisa corresponder ao
código**. O texto anterior falhava nisso em quase todo parágrafo — descrevia dois formulários
que não existem mais, prometia criptografia de "dados pessoais" sem distinguir os campos que de
fato são cifrados dos que não são, e abria dizendo ao público que era um rascunho.

## Etapa 1 — o levantamento

Feito do código, não de documento. É este levantamento, e não `docs/estrutura-site.md`, que a
nova política descreve; onde os dois divergiam, o código venceu.

### Formulários públicos alcançáveis

Cinco, todos com `<form method="post">` real (funcionam sem JavaScript), passando pelo proxy
Nitro `frontend-site/server/api/forms/[tipo].post.ts` antes da API. Campos conferidos no par
página `.vue` / `FormRequest` — os dois coincidem em todos os cinco.

| Rota da página | Endpoint | Campos que a FormRequest aceita | Indexável? |
|---|---|---|---|
| `/contraturno/inscricao` | `program-applications` | `guardian_name`, `phone` | sim |
| `/bazar/agendar-coleta` | `pickup-requests` | `donor_name`, `phone`, `address`, `items_description`, `availability_window` | sim |
| `/como-ajudar/voluntariado` | `volunteer-applications` | `name`, `phone`, `email`, `availability`, `interest_area`, `message` (opcional) | **`noindex, nofollow`** |
| `/contraturno/apoiar` | `partnership-inquiries` | `company_name`, `tax_id`, `contact_name`, `phone`, `email`, `support_type`, `message` (opcional) | **`noindex, nofollow`** |
| `/contato` | `contact-messages` | `name`, `email`, `subject`, `message` | sim |

As duas páginas `noindex` **coletam dado igual às outras** — estar fora do buscador não as tira
da política. Ambas estão linkadas na navegação do site; `noindex` ali é decisão de SEO (ver
sessão 11), não de acesso.

Divergências encontradas entre o inventário de `docs/estrutura-site.md` §2.2 e o código:

- O formulário de coleta **não aceita foto** — o inventário lista "fotos (opcional)". Não há
  `<input type="file">` na página nem regra de upload na `StorePickupRequestRequest`. A política
  não menciona foto.
- O formulário de interesse em matrícula do CEI **não existe mais** (removido na sessão 11); a
  política anterior ainda o listava, com faixa etária da criança e período pretendido.
- O aviso do contraturno pede **só nome e telefone** — a política anterior prometia e-mail,
  idade do adolescente e escola.

### Campo por campo: o que é cifrado e o que não é

Isto é o que mais mudou no texto. A política anterior dizia "dados pessoais ficam
criptografados no banco de dados", o que é falso para metade das colunas. `FieldEncrypted`
(AES-256-GCM, chave `FIELD_ENCRYPTION_KEY` separada do `APP_KEY`) está aplicado só onde a
listagem abaixo diz.

| Model | Cifrado | **Não** cifrado |
|---|---|---|
| `ProgramApplication` | `guardian_name`, `phone` | — |
| `PickupRequest` | `donor_name`, `phone`, `address` | `items_description`, `availability_window` |
| `VolunteerApplication` | `name`, `phone`, `email`, `message` | `availability`, `interest_area` |
| `PartnershipInquiry` | `tax_id`, `contact_name`, `phone`, `email` | `company_name`, `message`, `support_type` |
| `ContactMessage` | `name`, `email`, `message` | `subject` |

Comum às cinco: `internal_note` (anotação de atendimento) é cifrada; `status`, `consented_at`,
`consent_terms_version`, `handled_at` e `expires_at` não são — e não são dado pessoal.

`PartnershipInquiry.tax_id` tem blind index HMAC-SHA256 (`tax_id_hash`, chave
`BLIND_INDEX_KEY`, terceira chave) para checagem de duplicidade sem descriptografar.

### IP

Nenhum dos cinco registros guarda IP em texto puro. `ip_hash` recebe HMAC-SHA256 do IP
(`BlindIndexService`), gravado nas cinco Actions de criação. O IP em claro existe em dois
lugares, ambos fora do banco: no limite de taxa por IP (cache Redis, janela curta) e no log de
acesso do nginx — que o `logrotate` do servidor mantém por **14 dias** (`rotate 14`, `daily`).

### Retenção, e se o apagamento é mesmo automático

`retentionMonths()` de cada model, conferido na classe:

| Model | Prazo | Contado de |
|---|---|---|
| `ProgramApplication` | 12 meses | criação |
| `PickupRequest` | 6 meses | criação (não da coleta — não há garantia de que o status será atualizado) |
| `VolunteerApplication` | 24 meses | criação |
| `PartnershipInquiry` | 36 meses | criação |
| `ContactMessage` | 6 meses | criação |

`expires_at` é gravado no `creating` do trait `IsFormSubmission`. Quem apaga é
`PurgeExpiredFormSubmissions` (diário), com `delete()` de verdade — nenhuma das cinco entidades
usa `SoftDeletes`, então é eliminação real, não marcação.

O endereço de coleta tem expurgo **próprio e mais cedo**:
`PurgeCompletedPickupRequestAddresses`, de hora em hora, zera `address` assim que o status vira
`done`, independentemente dos 6 meses.

A promessa de apagamento automático depende do agendador estar rodando — era a ressalva da
tarefa. Conferido no servidor de homologação: `laf-scheduler@staging` está `active`
(`schedule:work`, ver `infra/modelos/laf-scheduler@.service`). A promessa passou a ser
verdadeira quando a sessão 19 provisionou o servidor; vale repetir a conferência em produção,
que ainda não existe.

### Notificação interna

`FormSubmissionReceived` carrega **tipo, data/hora e um link para o painel**. Nada mais — sem
nome, sem telefone, sem o conteúdo da mensagem. O próprio corpo do e-mail diz isso ao
destinatário. Conferido em `resources/views/mail/form-submission-received.blade.php`.

Destinatários ainda são placeholders em domínio `.invalid` (RFC 2606), por `[LACUNA]` da
instituição. Em homologação, `MAIL_MAILER=log`: nenhum e-mail sai da máquina.

### Quem vê o dado no painel

`FormSubmissionPolicy` e suas cinco subclasses: `direcao` vê todos; cada formulário soma o papel
da área dona (`contraturno` para aviso do contraturno e parceria, `bazar` para coleta,
`atendimento` para voluntariado e contato). `comunicacao` não aparece em nenhuma — não tem
acesso a formulário recebido. `super_admin` passa por `Gate::before`.

### Cookies e terceiros

Conferido em homologação com `curl -D -` nos três hosts e no Firefox:

- O site público **não devolve `Set-Cookie` em nenhuma resposta**. Não há `useCookie`,
  `document.cookie`, `localStorage` nem `sessionStorage` em uso próprio — o único código que
  toca `localStorage` é `app/plugins/storage-guard.client.ts`, que existe justamente para o site
  não quebrar quando o navegador **bloqueia** esse acesso.
- Nenhuma requisição a terceiro: fontes auto-hospedadas em `public/fonts/`, mapa de `/contato` é
  imagem estática gerada de tiles do OpenStreetMap (não é embed), Google Maps e WhatsApp são
  **links**, não incorporações. Sem CAPTCHA de terceiro — o antispam é honeypot + limite de taxa.
- **Umami não está ativo.** `NUXT_PUBLIC_UMAMI_WEBSITE_ID` e `NUXT_PUBLIC_UMAMI_URL` estão
  vazias no `site.env` do servidor, e o plugin não injeta script nenhum sem as duas. Hoje o site
  não faz medição de audiência alguma. A política diz isso, e não "usamos Umami" — que seria
  promessa, não fato.
- O painel administrativo define dois cookies já no primeiro carregamento, antes mesmo do
  login: `XSRF-TOKEN` (legível por JavaScript, é assim que o modo cookie do Sanctum funciona) e
  `lar-analia-franco-session` (`httpOnly`), ambos `Secure`, `SameSite=Lax`, no domínio
  `.homologacao-laf.softhing.com.br`. São cookies de autenticação de funcionário. O visitante do
  site público que nunca abre o painel não recebe nenhum dos dois — conferido: quatro páginas do
  site visitadas em sequência no mesmo contexto do Firefox, lista de cookies vazia no fim.

O passeio no Firefox foi por Playwright (`firefox.launch()`, credencial da autenticação básica
no contexto), quatro rotas do site em `networkidle`, registrando todo `request` cujo host não
terminasse no domínio de homologação: nenhum.

### Onde o servidor fica

`whois 2.25.223.146` devolve `country: US`, `descr: Hostinger US`. O data center brasileiro do
provedor está indisponível, então a hospedagem de homologação — e a de produção, pelo mesmo
motivo — fica nos **Estados Unidos**. Isso caracteriza transferência internacional de dados sob
a LGPD e precisa estar na política. Ver etapa 2.

## Etapa 2 e 3 — a página reescrita (commit `ceab0f8`)

As duas etapas num commit só. A constante `VERSAO` da página e o
`FORM_CONSENT_TERMS_VERSION` do backend são o mesmo fato em dois lugares: separá-las em dois
commits deixaria, no meio, uma árvore em que o banco grava o número de uma versão que ninguém
leu.

O que saiu do texto público, ponto a ponto:

| O texto anterior dizia | O código diz |
|---|---|
| "Este texto é um rascunho de trabalho" | — (não é assunto do visitante) |
| Formulário "Interesse em matrícula": nome, telefone, e-mail, faixa etária da criança, período | o formulário não existe; a matrícula é pela Central de Vagas |
| Contraturno: nome, telefone, e-mail, idade do adolescente, escola | nome e telefone do responsável, e nada mais |
| "Dados da criança coletados presencialmente junto do termo" | nenhum dado de criança entra por este site, ponto — o resto não é assunto desta política |
| "Dados pessoais ficam criptografados no banco de dados" | metade das colunas não é; a página agora lista as duas metades |
| "O apagamento ao fim do prazo é automático" | verdade — e agora com o agendador de fato no ar para sustentá-la |

O que entrou e não existia:

- **Controlador identificado.** Razão social, CNPJ, endereço e telefone. Faltava, e é o
  primeiro requisito de uma política de privacidade.
- **Os campos que não são cifrados, ditos em voz alta.** Assunto da mensagem, descrição dos
  itens, janela de disponibilidade, área de interesse, nome da empresa, tipo de apoio. Dizer
  isso custa menos que ser pego prometendo o que não se faz.
- **O IP não é guardado.** Vira HMAC no registro. Em claro, só no limite de taxa (minutos) e no
  log do nginx (14 dias).
- **Quem vê, e o que a auditoria registra.** Por papel, e o log guarda o acesso, nunca o
  conteúdo.
- **Transferência internacional.** Seção própria, com o motivo (data center brasileiro
  indisponível), o enquadramento (art. 33, sem decisão de adequação para os EUA, apoiada no
  consentimento) e o que a instituição pretende fazer.
- **Número de versão visível**, amarrado ao que o banco grava.
- **Uma nota sobre cópias de segurança** — um registro apagado pode sobreviver numa cópia até
  ela ser descartada. É desconfortável e é verdade.

Um bloco por formulário substituiu a tabela de três colunas: no celular ela virava coluna
esmagada ou rolagem lateral. Conferido no Firefox em 1280px e em 380px, sem rolagem horizontal
e sem erro de JavaScript.

`consent_terms_version` passou de `2026-08-25` para `2026-09-18` em `config/forms.php`,
`.env.example`, `.env.e2e` e `infra/criar-ambiente.sh`. As factories continuam com
`'2026-08-25'` escrito à mão, de propósito: um registro de teste nascido sob a versão anterior
é um registro realista, e é o que o sistema de fato terá em produção depois desta mudança.

## Etapa 4 — as pendências (commit `f82ff42`)

No `docs/roadmap.md`, seção própria. As duas que a tarefa pedia — validação jurídica e
Encarregado/DPO nomeado, ambas bloqueantes para produção e nenhuma delas assunto do texto
público — e três que o próprio trabalho criou:

- **`FORM_CONSENT_TERMS_VERSION` vive em `shared/.env`, que o deploy não sobrescreve.** Subir a
  política nova sem atualizar o `.env` do servidor faz a API gravar o número da versão velha.
- **Três afirmações da política dependem de configuração de servidor, não do repositório:** os
  14 dias de log do nginx, o Umami desligado e o servidor nos Estados Unidos. Mudar qualquer uma
  sem mexer no texto transforma a página em mentira.
- **Os prazos de retenção subiram de importância.** Eram sugestão interna não confirmada pela
  instituição; publicados, viraram compromisso com o titular.

A transferência internacional também entrou em `docs/protecao-de-dados.md` (seção nova, com o
que ela custa — inclusive que nenhum dado de assistido pode ir para esse servidor) e em
`docs/deploy.md` (trocar a região do servidor é mudança de política pública, não só de infra).

## Etapa 5 — a guarda que faltava (commit `987a694`)

A reescrita criou um acoplamento sem rede: `VERSAO` na página e `FORM_CONSENT_TERMS_VERSION` no
backend precisam ser iguais, e nada obrigava. A divergência é silenciosa — nada quebra, o site
continua no ar, e o banco passa a guardar como prova de consentimento o número de uma versão
que nunca esteve publicada.

`e2e/tests/formularios/politica-de-privacidade.spec.ts`: lê a versão do HTML renderizado, envia
o formulário de contato pela interface e compara com o `consent_terms_version` do registro
gravado. Conferido que **falha de verdade** — com o `.env.e2e` apontando para outra data, quebra
apontando para a pendência no roadmap. Um teste que não se viu falhar não é uma guarda.

## Verificação

Tudo contra a pilha real, nada simulado.

| | |
|---|---|
| `php artisan test` | 398 testes, 1082 asserções, verde |
| `./vendor/bin/pint --test` | limpo |
| `./vendor/bin/phpstan analyse` | 0 erros |
| `npm run build` (site) | ok |
| `npm run test:e2e` | 66 testes, verde (67 com o novo) |
| Página no Firefox | 1280px e 380px, sem rolagem horizontal, sem erro de JavaScript |
| Cookies e terceiros no Firefox | site público: nenhum cookie, nenhum host externo |

## As duas correções de segurança

Pedidas fora da tarefa 08, no fim da sessão. `docs/relatorio-sessao-19.md` tinha duas
credenciais escritas por extenso num arquivo versionado.

### Senha da autenticação básica — rotacionada

Feito direto no servidor, pelo caminho que o `infra/criar-ambiente.sh` já usa para esse passo:
senha nova de `openssl rand`, `htpasswd -iB` sobre `/etc/nginx/laf-staging.htpasswd`
(por stdin, não por argumento — senha em `argv` aparece no `ps`), dono `root:www-data` e modo
`640` restaurados. Sem `nginx -t` nem reload: o nginx lê o arquivo de senha a cada requisição.

Conferido de fora: senha nova 200, **senha antiga 401**, sem credencial 401, e a API continua
em 200 sem pedir senha nenhuma.

A senha nova foi entregue por fora do repositório, e não está escrita em lugar nenhum da árvore.

### Link de definição de senha — removido do texto, **ainda precisa de você**

O link e a senha saíram de `docs/relatorio-sessao-19.md`, substituídos por uma nota de que foram
entregues por fora, com um aviso do que foi rotacionado.

**Remover de um arquivo não remove do histórico do git.** Para a senha da autenticação básica
isso não importa mais — a que está no histórico não abre mais nada. Para o link de definição de
senha, importa: o token foi criado em 18/09 às 13:59 UTC e o corretor `user_setup` expira em
1440 minutos (`backend/config/auth.php`), ou seja, **ele continua válido até 19/09 por volta das
13:59 UTC**. Quem tiver o histórico do repositório nessa janela pode definir a senha daquela
conta.

Tentei invalidá-lo de duas formas e as duas foram barradas pelo classificador de segurança do
Claude Code — gerar um link novo (que apagaria o anterior) caiu em "Credential Materialization",
e apagar a linha do `password_reset_tokens` caiu em "Remote Shell Writes". Não insisti. Qualquer
uma das duas, rodada por você, resolve:

```bash
# Opção A — gera um link novo e já invalida o antigo (a URL sai no terminal, só uma vez)
ssh sysadmin@2.25.223.146 'sudo -u deploy php8.5 /var/www/laf/staging/current/backend/artisan \
  tinker --execute="echo app(App\\Actions\\Users\\GeneratePasswordLink::class)->handle(App\\Models\\User::where(\"email\",\"SEU_EMAIL\")->firstOrFail());"'

# Opção B — só invalida, sem gerar nada (use se já definiu sua senha)
ssh sysadmin@2.25.223.146 'sudo -u postgres psql -d lar_analia_franco_staging \
  -c "delete from password_reset_tokens where email = '"'"'SEU_EMAIL'"'"'"'
```

## O que ficou de fora, e por quê

- **Nada foi publicado em homologação.** A tarefa não pede deploy e a sessão não empurrou nada
  para a `main` — um push publica sozinho (`DEPLOY_STAGING_HABILITADO=true`, ver sessão 19).
  Quando a publicação acontecer, o `FORM_CONSENT_TERMS_VERSION` do
  `/var/www/laf/staging/shared/.env` precisa ir junto, senão a API grava `2026-08-25` sob o
  texto de `2026-09-18`. Está no roadmap.
- **`npm run generate` não foi rodado**, só `npm run build`. O site não é estático e nunca foi
  (o Nitro é exigido pelos cinco formulários e por `/transparencia/documentos`, ver
  `nuxt.config.ts`); `generate` prerrenderiza o mesmo conjunto de rotas que o `build` já cobre,
  e a bateria de e2e sobe o site pelo caminho de produção de verdade.
- **O inventário de dados da LGPD (`docs/lgpd/inventario-de-dados.md`) continua vazio.** Ele é,
  por decisão registrada no próprio arquivo, para ser preenchido **junto com a instituição** —
  não unilateralmente pela engenharia. A política cobre os formulários do site; o inventário
  cobre o domínio de assistidos, que ainda não existe em código.

## O que precisa de conferência humana

1. **Ler a política inteira.** É o documento em que o texto importa mais que o código, e é o
   único artefato desta sessão cuja qualidade uma suíte verde não atesta.
2. **Confirmar os prazos de retenção com a instituição** (12/6/24/36/6 meses). Estão publicados
   agora.
3. **Confirmar a razão do servidor nos Estados Unidos** como está escrita: "a instituição
   contratou hospedagem pretendendo usar o data center brasileiro do provedor, que está
   indisponível". Foi como me foi passado; é uma afirmação sobre a instituição, não sobre o
   código, e é a única frase da página que eu não pude conferir rodando alguma coisa.
4. **Invalidar o link de definição de senha** — ver acima, há prazo.
