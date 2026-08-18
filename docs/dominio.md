# Modelo de Domínio

> Proposta inicial, a validar com a instituição. Nomes de tabela e coluna em inglês.

## Entidades

### Núcleo protegido

**`assisted_minors`** — crianças e adolescentes atendidos
`uuid`, `internal_code` (público, não sequencial adivinhável), `initials`, `name` (enc),
`name_hash`, `name_tokens`, `birth_date`, `document_*` (enc + hash), `address_*` (enc),
`school` (enc), `status` (enum), `admitted_at`, `left_at`

**`guardians`** — responsáveis legais
`uuid`, `name` (enc), `name_hash`, `document` (enc + hash), `phone` (enc), `email` (enc),
`address` (enc)

**`guardianships`** — vínculo assistido ↔ responsável (N:N)
`assisted_minor_id`, `guardian_id`, `relationship` (enum: `mother`, `father`, `tutor`,
`guardian`, `other`), `is_primary`, `started_at`, `ended_at`

Um assistido pode ter mais de um responsável, e nem sempre é genitor.

**`consents`** — ver `@docs/protecao-de-dados.md`

**`health_records`** — tabela separada, acesso por papel clínico
`assisted_minor_id`, `type` (enum), `content` (enc), `recorded_by`, `recorded_at`

**`referrals`** — origem do encaminhamento
`assisted_minor_id`, `source` (enc), `protective_measure` (enc, nullable), `notes` (enc)

Sigilo reforçado. Papel dedicado, auditoria obrigatória em toda leitura.

**`attendance_records`** — registro de presença/atividades
`assisted_minor_id`, `activity_id`, `date`, `present`, `notes` (enc, nullable)

### Pessoas da instituição

**`users`** — contas do sistema. 2FA obrigatório para papéis com acesso a assistido.

**`volunteers`** — voluntários
`uuid`, `name`, `email`, `phone`, `availability`, `status`, `started_at`

Dados de adulto: regime comum, sem criptografia de nome. Documento continua cifrado.

**`staff_members`** — equipe. Vínculo com `users`.

### Conteúdo público

**`pages`** — páginas institucionais
`uuid`, `slug` (único), `title`, `content`, `meta_title`, `meta_description`, `og_image_id`,
`published_at`

**`posts`** — notícias
mesma base de `pages`, mais `excerpt`, `category_id`, `author_id`

**`media`** — imagens e arquivos
`uuid`, `path`, `alt` (**obrigatório**, validado na API), `mime`, `size`,
`assisted_minor_id` (nullable), `is_public`

Se `assisted_minor_id` estiver preenchido, `is_public` só pode ser `true` com consentimento de
imagem vigente. Regra na Action, não na interface.

**`transparency_documents`** — prestação de contas
`uuid`, `title`, `year`, `type` (enum), `file_path`, `published_at`

**`activities`** — programas e oficinas oferecidos

**`contact_messages`** — formulário de contato
Retenção curta, descarte automatizado.

**`volunteer_applications`** — inscrições de voluntariado

## Convenções de tabela

- PK `id` bigint auto-increment para uso interno; `uuid` único e indexado para exposição
  externa. Rotas e payloads usam **apenas** o UUID.
- `created_at`, `updated_at` em tudo
- `deleted_at` onde faz sentido reverter erro
- Colunas cifradas: sempre `text`
- Blind index: `char(64)`, indexado; tokens em `char(64)[]` com índice GIN
- Enums PHP espelhando constraints `CHECK` no banco — validação nas duas pontas

## Papéis e permissões

| Papel | Assistidos | Saúde | Encaminhamento | Voluntários | Conteúdo | Usuários |
|---|---|---|---|---|---|---|
| `super_admin` | total | total | total | total | total | total |
| `coordination` | total | leitura | leitura | total | leitura | leitura |
| `social_work` | total | leitura | total | — | — | — |
| `psychology` | leitura + evolução | total | leitura | — | — | — |
| `pedagogy` | leitura + frequência | — | — | — | — | — |
| `administrative` | leitura restrita¹ | — | — | leitura | — | — |
| `content_editor` | **nenhum acesso** | — | — | — | total | — |

¹ Apenas código interno, iniciais e dados necessários a matrícula e prestação de contas.
Sem saúde, sem endereço, sem encaminhamento.

**Princípios:**

- `content_editor` — quem cuida do site público — não tem **nenhum** acesso ao cadastro de
  assistidos. Esse é o papel mais provável de ser terceirizado.
- Toda autorização por **Policy**, nunca por checagem inline de papel no controller.
- Permissão granular por recurso; papéis são agrupamentos de conveniência, não a fonte da
  verdade.
- Acesso a `health_records` e `referrals` sempre gera registro de auditoria, inclusive
  leitura.

## Pendências com a instituição

- [ ] Validar quais atividades e programas existem
- [ ] Confirmar a estrutura real de papéis da equipe
- [ ] Definir quais documentos de assistido são efetivamente necessários (minimização)
- [ ] Confirmar se há convênio com órgão público que imponha campo ou prazo específico
