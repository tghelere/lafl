# Modelo de Domínio

> Revisado após o levantamento do contexto institucional. Nomes de tabela e coluna em inglês.
> Ver `@docs/contexto.md` e `@docs/estrutura-site.md`.

## Escopo

A instituição opera **três pilares**: creche (CEI Tio Pedro), escola de contraturno e bazar
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

**`media`** — imagens e arquivos
`uuid`, `path`, `alt` (**obrigatório**, validado na API), `mime`, `size`, `width`, `height`,
`assisted_minor_id` (nullable), `is_public`

EXIF removido no upload, conversão para WebP em fila. Se `assisted_minor_id` estiver
preenchido, `is_public` só pode ser `true` com consentimento de imagem vigente — a coluna já
nasce agora, mesmo sem a entidade de assistidos, com o gate bloqueando por padrão.

**`testimonials`** — depoimentos de famílias
`uuid`, `author_name`, `relationship`, `content`, `authorized_at`, `is_published`

Só entra no site com autorização registrada. Depoimento extraído de avaliação pública do
Google **não** pode ser publicado sem consentimento do autor.

**`partners`** — parceiros e apoiadores
`uuid`, `name`, `logo_id`, `url`, `type` (enum), `display_order`

**`institution_stats`** — números da home
`uuid`, `key`, `label`, `value`, `year`, `display_order`

Editável no painel. Evita número desatualizado codificado no front — 250 crianças hoje, meta
de 300+ em 2027.

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

Base comum a todos: `uuid`, `status` (enum: `new`, `in_progress`, `done`, `discarded`),
`consent_terms_version`, `consented_at`, `ip_hash`, `handled_by`, `handled_at`,
`internal_note` (enc), `created_at`, `expires_at`

**`enrollment_interests`** — matrícula / lista de espera do CEI
`guardian_name` (enc), `phone` (enc), `email` (enc), `child_age_range` (enum),
`desired_period` (enum), `message` (enc)

**`program_applications`** — inscrição no contraturno
`guardian_name` (enc), `phone` (enc), `email` (enc), `teen_age` (int), `school` (enc,
nullable), `message` (enc)

Para ambos: **faixa etária, nunca data de nascimento** — data identifica, faixa não. O campo
`message` é criptografado por precaução, porque alguém escreverá o nome do filho ali mesmo o
rótulo pedindo para não escrever.

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

Definidos em `@docs/estrutura-site.md` §4.4:
`super_admin`, `direcao`, `atendimento`, `bazar`, `comunicacao`.

Modelados pelo **tipo de dado que tocam**, não por cargo. `secretaria_cei` entra na Fase 2.

O enum atual do código (`social_work`, `psychology`, `pedagogy`, `coordination`,
`administrative`, `content_editor`) veio do desenho de acolhimento e **precisa ser
substituído**.

**Princípios:**

- Toda autorização por **Policy**, nunca por checagem inline de papel no controller
- `comunicacao` não enxerga nenhum formulário recebido — exige teste Pest explícito
- Acesso a dado de assistido gera auditoria, inclusive leitura

## Pendências

- [ ] `[VALIDAR]` Vitrine do bazar terá preço? Há quem alimente semanalmente?
- [ ] `[VALIDAR]` Campos exatos de cada formulário, com quem hoje faz o atendimento
- [ ] `[VALIDAR]` Prazos de retenção
- [ ] `[VALIDAR]` Convênio com a Secretaria Municipal de Educação impõe campo ou relatório?
- [ ] Preencher `docs/lgpd/inventario-de-dados.md` antes da Fase 2
