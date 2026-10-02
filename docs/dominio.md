# Modelo de Domínio

> Revisado após o levantamento do contexto institucional. Nomes de tabela e coluna em inglês.
> Ver `@docs/contexto.md` e `@docs/estrutura-site.md`.

## Escopo

A instituição opera **três pilares**: creche (CEI Anália Franco), escola de contraturno e bazar
beneficente. O serviço de acolhimento institucional foi **encerrado em 2022** — não existem
no sistema medida protetiva, prontuário de acolhimento, dados de guarda ou vínculo com vara
da infância.

## Fase 1 — Site público e formulários

É o escopo implementável agora. **Nenhuma entidade desta fase contém dado de menor.**

### Conteúdo

**`pages`** — páginas institucionais
`uuid`, `slug` (único), `title`, `content`, `meta_title`, `meta_description`, `og_image_id`,
`status` (enum), `published_at`

**`page_slug_history`** — slugs antigos, para redirect 301
`page_id`, `slug`, `created_at`

**`posts`** — notícias
Base de `pages`, mais `excerpt`, `category_id`, `author_id`. Também com histórico de slug.

**`post_categories`** — `uuid`, `slug`, `name`

**`media`** — biblioteca de imagens do conteúdo (implementada na sessão 27)
`uuid`, `alt` (**obrigatório**, validado na API), `caption`, `credit` (opcional, mostrado na
ampliação do site, sessão 30), `depicts_assisted_minor`
(declaração obrigatória, sem padrão), `version`, `mime`, `extension`, `size`, `width`,
`height`, `widths` (derivadas webp geradas), `sha256`, `origin_key` (nulo, exceto nas fotos
vindas do catálogo inicial — só para `midia:importar-fotos-iniciais` ser idempotente)

Sem coluna de caminho: os arquivos ficam em `media/{uuid}/{versão}/`, fora do webroot. A
original é recodificada no upload (todo metadado sai) e as derivadas webp são geradas na hora,
não em fila. `depicts_assisted_minor = true` torna a imagem impublicável e é recusado no
upload enquanto não existir registro de consentimento de imagem; `assisted_minor_id` entra
junto com `consents`, na Fase 2, e `Media::isPublishable()` passa a consultá-lo. Ver
`docs/decisoes/0024-biblioteca-de-midia.md`.

**`page_images`** — capa e galeria de uma página (sessão 28)
`page_id`, `media_id`, `role` (`cover` | `gallery`, enum `PageImageRole`), `position`

Só a ligação: arquivo, texto alternativo e legenda são da imagem, em `media`. A galeria é o que
a página mostra depois do texto; a capa (uma por página) representa a página em outro lugar do
site e não aparece nela. Cascata dos dois lados. Imagem no meio do texto não tem linha aqui:
vive no `content`. Ver `docs/decisoes/0025-imagens-da-pagina.md`.

**`testimonials`** — depoimentos de famílias
`uuid`, `author_name`, `relationship`, `content`, `authorized_at`, `is_published`

Só entra no site com autorização registrada. Depoimento extraído de avaliação pública do
Google **não** pode ser publicado sem consentimento do autor.

**`partners`** — parceiros e apoiadores (implementado: ver `docs/decisoes/0029-parceiros-e-ampliacao-no-minimo-da-pagina.md`)
`uuid`, `name`, `logo_id`, `url`, `type` (enum), `display_order`

**`institution_stats`** — números da home
`uuid`, `key`, `label`, `value`, `year`, `display_order`

Editável no painel. Evita número desatualizado codificado no front — 213 crianças em
abr/2023, previsão de 308 em 2027 (ver `docs/contexto.md`).

**`transparency_documents`** — prestação de contas
`uuid`, `title`, `year`, `type` (enum: `balance`, `bylaws`, `minutes`, `certificate`,
`agreement_accounting`, `notice`, `annual_report`), `file_path`, `file_size`,
`download_count`, `published_at`

Entidade de primeira classe, não anexo. É o eixo da estratégia reputacional: o acervo já
existe (~70 documentos) mas hoje é invisível para buscadores.

**`settings`** — dados institucionais
`key`, `value`. Endereços, horários, PIX, redes sociais, contatos.

**`bazaar_showcase_items`** — vitrine "novidades da semana"
`uuid`, `title`, `description`, `media_id`, `price` (nullable), `is_available`,
`published_at`

`[VALIDAR]` Se a vitrine terá preço. E há risco de abandono: se ninguém fotografar e
cadastrar toda semana, a página morre — como o blog do Wix morreu em 2020.

### Formulários recebidos

**Todos os titulares são adultos.** Ver `@docs/estrutura-site.md` §2.1: nenhum formulário
público coleta dado identificável de criança ou adolescente.

Base comum a todos: `uuid`, `status` (enum: `in_progress`, `done`, `archived`),
`consent_terms_version`, `consented_at`, `ip_hash`, `read_at`, `read_by`, `handled_by`,
`handled_at`, `internal_note` (enc), `created_at`, `expires_at`

**Leitura e atendimento são eixos independentes.** `read_at`/`read_by` registram a primeira
abertura do detalhe, compartilhada pela equipe inteira — não existe leitura por usuário. O
status descreve o atendimento, e começa em `in_progress`. Não existe status `new`: "ninguém
olhou isto ainda" é `read_at` nulo. Ver
`docs/decisoes/0021-leitura-separada-do-status-de-atendimento.md`.

**`enrollment_interests`** — matrícula / lista de espera do CEI
`guardian_name` (enc), `phone` (enc), `email` (enc), `child_age_range` (enum),
`desired_period` (enum), `message` (enc)

**`program_applications`** — aviso de interesse no contraturno
`guardian_name` (enc), `phone` (enc)

A Escola de Contraturno ainda não abriu inscrições (ver `docs/contexto.md`) — o formulário
não é uma inscrição de fato, só um aviso de "me avise quando abrir". Por isso não coleta
nenhum dado da criança ou adolescente, nem faixa etária: só o contato do responsável.

**`enrollment_interests` foi removida** (ver `docs/estrutura-site.md` §2.1): a matrícula do
CEI é feita exclusivamente pela Central de Vagas da Prefeitura, e a instituição não atende
diretamente esse fluxo — coletar contato que ela não pode atender geraria dado pessoal sem
finalidade.

**`pickup_requests`** — agendamento de coleta do bazar
`donor_name` (enc), `phone` (enc), `address` (enc), `items_description`,
`availability_window`, `scheduled_for`, `media_ids` (fotos opcionais)

Endereço residencial é o dado mais sensível desta fase. Purgado assim que a coleta é
concluída, não ao fim da retenção geral.

**`volunteer_applications`**
`name` (enc), `phone` (enc), `email` (enc), `availability`, `interest_area`, `message` (enc)

**`partnership_inquiries`** — empresas
`company_name`, `tax_id` (enc + hash), `contact_name` (enc), `phone` (enc), `email` (enc),
`support_type` (enum), `message`

**`contact_messages`**
`name` (enc), `email` (enc), `subject`, `message` (enc)

Retenção por tabela conforme `@docs/estrutura-site.md` §2.2, com job de descarte usando
`expires_at`. Descarte automatizado, nunca manual.

### Contas

**`users`** — contas do sistema. 2FA obrigatório.

## Fase 2 — Cadastro de assistidos

**Bloqueado** até o preenchimento de `docs/lgpd/inventario-de-dados.md` com a instituição.
Esboço, a revisar quando a fase começar:

**`assisted_minors`** — `uuid`, `internal_code`, `initials`, `name` (enc + blind index),
`birth_date`, `document_*` (enc + hash), `address_*` (enc), `school` (enc),
`program` (enum: `cei`, `after_school`), `status`, `admitted_at`, `left_at`

**`guardians`** — `uuid`, `name` (enc + blind index), `document` (enc + hash), `phone` (enc),
`email` (enc), `address` (enc)

**`guardianships`** — vínculo N:N, `relationship` (enum: `mother`, `father`, `tutor`,
`guardian`, `other`), `is_primary`, `started_at`, `ended_at`

**`consents`** — ver `@docs/protecao-de-dados.md`. Finalidades separadas, termos versionados,
revogação com efeito imediato.

**`health_records`** — **encolhido**: numa creche o dado relevante é alergia, restrição
alimentar e medicação de uso contínuo. Continua sendo dado sensível (art. 5º, II), em tabela
separada e criptografado, mas não é prontuário clínico.

**`classes`** e **`enrollments`** — turmas e matrículas, por pilar

**`attendance_records`** — frequência

**Removido do modelo anterior:** `referrals` (encaminhamento judicial e medida protetiva) e
`staff_members`. O primeiro era do acolhimento; o segundo não tem uso definido — `users`
basta por ora.

## Convenções de tabela

- PK `id` bigint auto-increment para uso interno; `uuid` único e indexado para exposição
  externa. Rotas e payloads usam **apenas** o UUID.
- `created_at`, `updated_at` em tudo
- `deleted_at` onde faz sentido reverter erro
- Colunas cifradas: sempre `text`
- Blind index: `char(64)` indexado; tokens em `char(64)[]` com índice GIN
- Enums PHP espelhando constraints `CHECK` no banco
- Campo cifrado é escrito **apenas via instância de model**, nunca por query builder
  (`Model::query()->update()`, `upsert()`, `insert()`) — esses caminhos não disparam o hook
  que sincroniza o blind index, e ainda gravariam texto puro por não passarem pelo cast.

## Papéis

`App\Enums\Role`: `super_admin`, `direcao`, `financeiro`, `contraturno`, `bazar`,
`atendimento`, `comunicacao`.

Por **área de atuação**, não por cargo — organograma muda, a área dona de um formulário ou
conteúdo não. Um usuário pode acumular mais de um papel (ex.: quem cuida do financeiro também
cobre o contraturno); cada papel soma seus acessos aos dos outros que o mesmo usuário tiver —
não há papel "combinado" à parte. Sem papel para a creche (CEI): Educação Infantil não tem
formulário recebido próprio (matrícula aponta para a Central de Vagas da Prefeitura, ver
`docs/roadmap.md`) nem conteúdo administrado fora de `pages`, que já é
`comunicacao`/`direcao`. `secretaria_cei` (ou equivalente) só entra quando a Fase 2 (cadastro
de assistidos) for desbloqueada — fora do escopo atual.

### Matriz de acesso por recurso

A fonte da verdade é sempre a Policy do recurso (`App\Policies\*`), nunca esta tabela — ela
existe para consulta rápida, não para ser lida em vez do código.

| Recurso | Papéis com acesso (leitura e escrita) |
|---|---|
| `pages` | `direcao`, `comunicacao` (ver nota abaixo) |
| `transparency-documents` | `direcao`, `financeiro` |
| `program-applications` | `direcao`, `contraturno` |
| `partnership-inquiries` | `direcao`, `contraturno` |
| `pickup-requests` | `direcao`, `bazar` |
| `volunteer-applications` | `direcao`, `atendimento` |
| `contact-messages` | `direcao`, `atendimento` |
| gestão de usuários | não implementada (ver `docs/roadmap.md`) |

`super_admin` acessa tudo, sempre — bypass via `Gate::before` em `AppServiceProvider`, não
aparece na tabela. `direcao` acessa todos os recursos acima, leitura e escrita, exceto gestão
de usuários (quando existir). `comunicacao` não tem acesso a nenhum formulário recebido nem a
`transparency-documents` — só `pages`, nunca dado de pessoa.

**Nota sobre `pages`:** `comunicacao` lê e edita página existente (título, conteúdo, campos
de SEO) igual a `direcao`, mas não **cria** nem **exclui** página — as duas abilities exigem
`direcao`. Criar ou apagar mexe na estrutura do site (o que passa a existir, o que sai do ar e
do que o Nuxt prerenderiza), mesmo peso de decisão que `managePublication` já reserva a
`direcao` para trocar slug ou status de uma página existente (ver `App\Policies\PagePolicy`).
`comunicacao` é o papel de quem escreve o conteúdo do dia a dia, não de quem decide a
estrutura.

`partnership-inquiries` está sob `contraturno`, não `atendimento`: o único formulário de
proposta de parceria do site (`/contraturno/apoiar`, página "Apoiar o Projeto") é específico
do Contraturno — não existe formulário de parceria institucional geral (`/como-ajudar/
parceiros` é conteúdo sobre parceiros já existentes, sem formulário).

**Princípios:**

- Toda autorização por **Policy**, nunca por checagem inline de papel no controller
- `comunicacao` não enxerga nenhum formulário recebido nem dado de pessoa — exige teste Pest
  explícito
- Acesso a dado de assistido gera auditoria, inclusive leitura

## Pendências

- [ ] `[VALIDAR]` Vitrine do bazar terá preço? Há quem alimente semanalmente?
- [ ] `[VALIDAR]` Campos exatos de cada formulário, com quem hoje faz o atendimento
- [ ] `[VALIDAR]` Prazos de retenção
- [ ] `[VALIDAR]` Convênio com a Secretaria Municipal de Educação impõe campo ou relatório?
- [ ] Preencher `docs/lgpd/inventario-de-dados.md` antes da Fase 2
