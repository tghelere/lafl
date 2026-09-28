# Estrutura do Site e do Sistema

> Mapa de rotas, formulários, endpoints e telas administrativas.
> Estado: proposta para validação. Itens marcados `[DECIDIR]` precisam da sua escolha;
> `[VALIDAR]` precisa de confirmação da instituição.

## Convenções

- Slugs em português, minúsculas, com hífen. Sem acento nem `ç` na URL.
- Toda rota pública é indexável, salvo indicação contrária.
- "CMS" = conteúdo editável pelo painel. "Fixo" = codificado no front, muda só em deploy.
- Nenhuma rota pública expõe ID sequencial.

---

# Parte 1 — Site público (Nuxt)

## 1.1 Menu principal

Sete itens de primeiro nível. Os três pilares têm peso visual igual — quem veio pelo bazar
não precisa entender o que é um CEI.

```
HOME · QUEM SOMOS · EDUCAÇÃO INFANTIL · CONTRATURNO · BAZAR · COMO AJUDAR · TRANSPARÊNCIA
```

Notícias e Contato ficam no rodapé e em CTAs contextuais, não no menu principal — sete itens
já é o limite do que cabe em desktop sem quebrar.

## 1.2 Mapa de rotas

| Rota | Tipo | Conteúdo | Formulário |
|---|---|---|---|
| `/` | SSG | Fixo + CMS (números, destaques) | — |
| **Quem somos** | | | |
| `/quem-somos` | SSG | CMS | — |
| `/quem-somos/nossa-historia` | SSG | CMS | — |
| `/quem-somos/missao-visao-valores` | SSG | CMS | — |
| `/quem-somos/governanca` | SSG | CMS | — |
| **Educação infantil** | | | |
| `/educacao-infantil` | SSG | CMS | — |
| `/educacao-infantil/dia-da-crianca` | SSG | CMS | — |
| `/educacao-infantil/proposta-pedagogica` | SSG | CMS | — |
| `/educacao-infantil/alimentacao-e-saude` | SSG | CMS | — |
| `/educacao-infantil/estrutura` | SSG | CMS + galeria | — |
| `/educacao-infantil/depoimentos` | SSG | CMS | — |
| `/educacao-infantil/matricula` | SSG | CMS (explica o caminho pela Central de Vagas) | — |
| **Contraturno** | | | |
| `/contraturno` | SSG | CMS | — |
| `/contraturno/o-projeto` | SSG | CMS | — |
| `/contraturno/para-quem-e` | SSG | CMS | — |
| `/contraturno/como-funciona` | SSG | CMS | — |
| `/contraturno/parceiros` | SSG | CMS | — |
| `/contraturno/o-que-vem-por-ai` | SSG | CMS | — |
| `/contraturno/inscricao` | SSR | CMS + form (aviso de interesse, não inscrição — o programa ainda não abriu) | **Sim** |
| `/contraturno/apoiar` | SSR | CMS + form | **Sim** |
| **Bazar** | | | |
| `/bazar` | SSG | CMS | — |
| `/bazar/visite-a-loja` | SSG | CMS + mapa | — |
| `/bazar/novidades` | ISR | CMS (vitrine) | — |
| `/bazar/agendar-coleta` | SSR | CMS + form | **Sim** |
| `/bazar/o-que-aceitamos` | SSG | CMS | — |
| `/bazar/para-onde-vai` | SSG | CMS | — |
| `/bazar/sua-compra-vira-educacao` | SSG | CMS | — |
| **Como ajudar** | | | |
| `/como-ajudar` | SSG | CMS | — |
| `/como-ajudar/doar` | SSG | CMS (PIX, QR) | — |
| `/como-ajudar/doar-itens` | — | Redirect 301 → `/bazar/agendar-coleta` | — |
| `/como-ajudar/voluntariado` | SSR | CMS + form | **Sim** |
| `/como-ajudar/parceiros` | SSG | CMS | — |
| **Transparência** | | | |
| `/transparencia` | SSG | CMS (página explicativa) | — |
| `/transparencia/documentos` | ISR | CMS, filtro por ano e tipo | — |
| **Notícias** | | | |
| `/noticias` | ISR | CMS, paginado | — |
| `/noticias/:slug` | ISR | CMS | — |
| **Contato** | | | |
| `/contato` | SSR | CMS + form | **Sim** |

`/como-ajudar/empresas-ir` e `/como-ajudar/nota-parana` existiam nesta tabela e foram
removidas em 10/09/2026: o cliente confirmou que nem a destinação de Imposto de
Renda/FMDCA nem o Nota Paraná estão disponíveis hoje. Voltam ao escopo quando a
instituição avisar (ver `docs/contexto.md`).

## 1.3 Rotas fora do menu

| Rota | Indexável | Observação |
|---|---|---|
| `/busca?q=` | Não | Busca interna |
| `/politica-de-privacidade` | Sim | Exigência LGPD |
| `/termos-de-uso` | Sim | |
| `/mapa-do-site` | Sim | Ajuda SEO e acessibilidade |
| `/obrigado/:tipo` | **Não** (`noindex`) | Confirmação pós-formulário — URL própria permite medir conversão no Umami |
| `/404` | Não | |
| `/robots.txt`, `/sitemap.xml` | — | Gerados |

`:tipo` ∈ `inscricao`, `coleta`, `voluntariado`, `contato`, `parceria`.

## 1.4 Páginas separadas — decidido

Cada subpágina tem URL própria e indexável. O mapa da §1.2 já reflete isso.

Motivo: cada URL disputa uma busca distinta.

Consequência a acompanhar: algumas páginas de "Contraturno" nascem com pouco conteúdo, já
que o programa é novo. Página magra posiciona mal. Se depois da redação alguma ficar com
menos de ~300 palavras sem previsão de crescer, vale unir à página-pilar em vez de publicar
vazia. Decisão de conteúdo, não de arquitetura — não bloqueia a implementação.

---

# Parte 2 — Formulários

Cinco formulários — a matrícula do CEI não é mais um deles (ver `docs/contexto.md` e §1.4
abaixo): a matrícula é feita exclusivamente pela Central de Vagas da Prefeitura, e a
instituição não atende esse fluxo diretamente, então coletar contato pelo site geraria dado
pessoal sem finalidade.

**Nenhum formulário coleta hoje dado identificável nem dado referente a criança ou
adolescente**, nem mesmo faixa etária. O aviso de interesse no contraturno (§2.2, formulário
2) é o único dos cinco que se relaciona a um programa para menores, e coleta só o contato do
responsável — o programa ainda não abriu inscrições, então não há "matrícula" nem "aluno" a
descrever (ver `docs/contexto.md`).

## 2.1 Manifestação de interesse — decidido, agora só relevante ao histórico

ADR 0007 documentou por que os dois formulários que antes se referiam a uma criança ou
adolescente (matrícula do CEI e inscrição no contraturno) nunca coletariam dado identificável
dela — só a manifestação de interesse do responsável. A decisão continua valendo como
princípio, mas o cenário mudou:

- **Matrícula do CEI:** o formulário foi **removido**. A matrícula é feita exclusivamente
  pela Central de Vagas da Prefeitura de Londrina — ver §1.4.
- **Contraturno:** o programa ainda não abriu inscrições. O que existe hoje não é mais um
  "formulário de manifestação de interesse na matrícula do adolescente" — é um simples aviso
  de "me avise quando abrir", e por isso nem pede faixa etária da criança: só nome e telefone
  do responsável.

Continua valendo, para o que resta: o titular do formulário de aviso do contraturno é o
**responsável adulto**, nunca a criança. Nenhuma tabela de assistido é alimentada por
endpoint público, em nenhuma hipótese.

## 2.2 Inventário

| # | Formulário | Rota | Titular | Campos | Retenção sugerida |
|---|---|---|---|---|---|
| 1 | Aviso de interesse — contraturno | `/contraturno/inscricao` | Responsável (adulto) | Nome, telefone | 12 meses |
| 2 | Agendar coleta — caminho secundário; WhatsApp (43) 99950-0183 é o principal (ver `docs/contexto.md`) | `/bazar/agendar-coleta` | Doador (adulto) | Nome, telefone, endereço, itens, janela de disponibilidade, fotos (opcional) | 6 meses após coleta |
| 3 | Voluntariado | `/como-ajudar/voluntariado` | Voluntário (adulto) | Nome, telefone, e-mail, disponibilidade, área de interesse | 24 meses |
| 4 | Apoiar projeto / empresas | `/contraturno/apoiar` | Contato PJ | Empresa, CNPJ, contato, telefone, e-mail, tipo de apoio | 36 meses |
| 5 | Contato | `/contato` | Visitante | Nome, e-mail, assunto, mensagem | 6 meses |

## 2.3 Regras comuns a todos

- **Consentimento explícito**, checkbox não pré-marcado, com link para a política de
  privacidade. A versão do texto aceito é gravada junto do registro.
- **Honeypot + rate limit por IP.** Sem CAPTCHA de terceiro — implicaria cookie do Google e
  o banner de consentimento que evitamos ao escolher o Umami.
- **Endereço** (formulário 3) é dado sensível na prática: criptografado, e purgado assim que
  a coleta é concluída.
- **Telefone e e-mail** criptografados em todos.
- **Descarte automatizado** por job, conforme a retenção da tabela. Não manual.
- Redirect para `/obrigado/:tipo` no sucesso, com evento no Umami.
- Notificação por e-mail ao setor responsável, via fila.

---

# Parte 3 — API pública

Sem autenticação, cacheada em Redis, com `Cache-Control` e ETag. Resources separados dos
administrativos: nunca expõem autor, rascunho ou campo de controle.

## 3.1 Leitura

| Método | Rota | Uso |
|---|---|---|
| GET | `/api/v1/public/pages` | Inventário para o sitemap: slug e `updated_at`, paginado |
| GET | `/api/v1/public/pages/{slug}` | Página institucional |
| GET | `/api/v1/public/posts` | Notícias, paginado, filtro por categoria |
| GET | `/api/v1/public/posts/{slug}` | Notícia |
| GET | `/api/v1/public/transparency-documents` | Filtro por ano e tipo |
| GET | `/api/v1/public/transparency-documents/{ano}/{slug}.pdf` | PDF `inline`, com contagem |
| GET | `/api/v1/public/transparency-documents/{uuid}/download` | Endereço antigo — 301 para o de cima |
| GET | `/api/v1/public/testimonials` | Depoimentos autorizados |
| GET | `/api/v1/public/partners` | Parceiros e apoiadores |
| GET | `/api/v1/public/bazaar/showcase` | Vitrine "novidades da semana" |
| GET | `/api/v1/public/institution-facts` | Idades calculadas e contagem do acervo (implementado) |
| GET | `/api/v1/public/stats` | Números da home que a instituição informa e atualiza à mão |
| GET | `/api/v1/public/settings` | Endereços, horários, PIX, redes sociais |
| GET | `/api/v1/public/search?q=` | Busca interna |

`settings` é um único endpoint com os dados institucionais que aparecem no rodapé e em
várias páginas — evita seis chamadas para montar o layout.

O PDF de transparência **não é servido por esta URL ao visitante**: quem a chama é a rota de
proxy do site (`/transparencia/documentos/{ano}/{slug}.pdf`, ver
`frontend-site/server/routes/transparencia/documentos/`), para que o arquivo indexado esteja no
domínio do site e não no da API. A rota da API espelha o mesmo caminho e é ela que decide 404,
301 de ano trocado e contagem de download. O `slug` nasce do título na criação e nunca é
recalculado — renomear o documento não pode quebrar link já indexado. O desenho inteiro, com
as alternativas descartadas, está em
`docs/decisoes/0017-url-publica-dos-documentos-de-transparencia.md`.

`institution-facts` e `stats` não são a mesma coisa e vão conviver: `institution-facts`
devolve o que é DERIVADO (idade a partir de uma data, contagem do acervo publicado) e por isso
não pode ser digitado em lugar nenhum; `stats` guardaria o que a instituição INFORMA (crianças
atendidas, turmas) e atualiza à mão. Ver
`docs/decisoes/0012-numeros-institucionais-calculados.md`.

## 3.2 Escrita

Todas com rate limit agressivo e honeypot.

| Método | Rota |
|---|---|
| POST | `/api/v1/public/program-applications` |
| POST | `/api/v1/public/pickup-requests` |
| POST | `/api/v1/public/volunteer-applications` |
| POST | `/api/v1/public/partnership-inquiries` |
| POST | `/api/v1/public/contact-messages` |

---

# Parte 4 — Painel administrativo (Vue 3 SPA)

Todas as rotas sob `/admin`, `noindex`, atrás de login.

## 4.1 Autenticação (fora do menu)

`/login` · `/esqueci-senha` · `/redefinir-senha/:token` · `/dois-fatores` · `/trocar-senha`

## 4.2 Menu do painel

| Seção | Telas | Papéis |
|---|---|---|
| **Início** | Painel com a contagem de formulários **não lidos** por seção (ver ADR 0021) | todos, filtrado pelo papel |
| **Conteúdo** | Páginas · Notícias · Mídia · Depoimentos · Parceiros · Números da home | `comunicacao`, `direcao` |
| **Transparência** | Documentos (upload, ano, tipo, publicação) | `direcao` |
| **Atendimento** | Interesses de matrícula · Inscrições contraturno · Propostas de apoio · Mensagens de contato · Voluntários | `atendimento`, `direcao` |
| **Bazar** | Coletas (agenda, status) · Vitrine | `bazar`, `direcao` |
| **Configurações** | Usuários e papéis · Dados institucionais · Auditoria | `super_admin` |

A tela de Início é a mesma para todos, mas mostra só os blocos que o papel enxerga —
`comunicacao` vê um painel sem nenhum formulário.

## 4.3 Padrão de rota

```
/admin/{recurso}                 listagem, filtro, paginação
/admin/{recurso}/novo            criação
/admin/{recurso}/{uuid}          detalhe / edição
```

## 4.4 Papéis — decidido

Implementado em `App\Enums\Role` (backend/app/Enums/Role.php); esta seção espelha
`docs/dominio.md`, seção "Papéis" — em caso de divergência entre os dois, o código e
`docs/dominio.md` têm precedência, esta seção é só a versão "painel/menu" da mesma matriz.

```php
enum Role: string
{
    case SuperAdmin = 'super_admin';
    case Direcao = 'direcao';
    case Financeiro = 'financeiro';
    case Contraturno = 'contraturno';
    case Bazar = 'bazar';
    case Atendimento = 'atendimento';
    case Comunicacao = 'comunicacao';
}
```

**Princípio:** os papéis são modelados pela **área de atuação**, não por cargo. Organograma
muda; a área dona de um formulário ou conteúdo não. Um usuário pode acumular mais de um
papel — cada papel soma seus acessos aos dos outros que o mesmo usuário tiver, não existe
papel "combinado" à parte.

### Matriz de acesso por recurso

A fonte da verdade é sempre a Policy do recurso (`backend/app/Policies/*`), nunca esta
tabela.

| Recurso | Papéis com acesso (leitura e escrita) |
|---|---|
| `pages` | `direcao`, `comunicacao` |
| `transparency-documents` | `direcao`, `financeiro` |
| `program-applications` | `direcao`, `contraturno` |
| `partnership-inquiries` | `direcao`, `contraturno` |
| `pickup-requests` | `direcao`, `bazar` |
| `volunteer-applications` | `direcao`, `atendimento` |
| `contact-messages` | `direcao`, `atendimento` |
| gestão de usuários (`/api/v1/users`, `/api/v1/roles`) | **só `super_admin`** — nem `direcao` |

`super_admin` acessa tudo, sempre — bypass via `Gate::before` em `AppServiceProvider`, não
aparece na tabela. `direcao` acessa todos os recursos operacionais, leitura e escrita, exceto
gestão de usuários. `comunicacao` não tem acesso a nenhum formulário recebido nem a
`transparency-documents` — só `pages`, nunca dado de pessoa.

**Notas de desenho:**

- Gestão de usuários (criar, editar papel, desativar, reativar, gerar link de senha) é a
  única área sem nenhum papel de área autorizado — nem `direcao`, que administra todo o
  resto. Conceder acesso ao próprio painel é sempre ato deliberado de `super_admin`, nunca
  delegável. Ver `docs/dominio.md`, seção "Contas", para o desenho completo (estado
  ativo/inativo, link de senha de uso único, proteções contra remover o último
  `super_admin`).
- `bazar` é separado porque pedido de coleta (`pickup_requests`) contém **endereço
  residencial** do doador, e porque a equipe do bazar tende a ser própria e rotativa.
- `partnership-inquiries` está sob `contraturno`, não `atendimento`: o único formulário de
  proposta de parceria do site (`/contraturno/apoiar`, "Apoiar o Projeto") é específico do
  Contraturno — não existe formulário de parceria institucional geral.
- `comunicacao` é o papel mais provável de ser terceirizado. **Não enxerga nenhum formulário
  recebido nem documento de transparência.** Exige teste Pest afirmando isso explicitamente
  (ver `tests/Feature/Authorization/RoleMatrixTest.php`).
- Não há papel para a creche (CEI): a Educação Infantil não tem formulário recebido próprio
  (matrícula aponta para a Central de Vagas da Prefeitura) nem conteúdo administrado fora de
  `pages`, que já é `comunicacao`/`direcao`.
- `secretaria_cei` (ou equivalente) só entra quando o cadastro de assistidos (Fase 2)
  existir, para separar quem vê dado de criança de quem vê formulário de adulto — Fase 2
  está bloqueada até `docs/lgpd/inventario-de-dados.md` ser preenchido.
- Dividir papel depois é simples; juntar é que dá trabalho. Se `atendimento` se revelar
  amplo demais, divide-se em `secretaria` e `contato`.

## 4.5 Endpoints administrativos

CRUD padrão sob `/api/v1/` para: `pages`, `posts`, `media`, `testimonials`, `partners`,
`transparency-documents`, `stats`, `settings`,
`program-applications`, `pickup-requests`, `volunteer-applications`,
`partnership-inquiries`, `contact-messages`, `users`, `roles`.

Além disso:

| Método | Rota | Uso |
|---|---|---|
| PATCH | `/api/v1/{recurso}/{uuid}/status` | Mudança de status com anotação |
| DELETE | `/api/v1/{recurso}/{uuid}/read` | Marcar como **não** lido (não há POST: abrir o detalhe já marca como lido — ver ADR 0021) |
| POST | `/api/v1/media` | Upload — remove EXIF, converte WebP |
| POST | `/api/v1/{recurso}/{uuid}/publish` | Publicação, auditada |
| GET | `/api/v1/audit-logs` | Auditoria |
| GET | `/api/v1/me` | Usuário autenticado |

---

# Parte 5 — Pendências

Decidido:

- [x] Manifestação de interesse, sem coleta de dado de menor pela web (§2.1)
- [x] Páginas separadas, uma URL por subpágina (§1.4)
- [x] Cinco papéis, modelados por tipo de dado (§4.4)
- [x] Leitura compartilhada, separada do status de atendimento (ADR 0021)

Em aberto — nenhum bloqueia a implementação:
- [ ] `[VALIDAR]` Campos de cada formulário com quem hoje faz esse atendimento
- [ ] `[VALIDAR]` Prazos de retenção
- [ ] `[VALIDAR]` Se a vitrine do bazar terá preço ou só foto e descrição
- [ ] Domínio definitivo
