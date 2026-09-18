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
