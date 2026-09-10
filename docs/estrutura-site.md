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
| `/quem-somos/o-lar-hoje` | SSG | CMS | — |
| **Educação infantil** | | | |
| `/educacao-infantil` | SSG | CMS | — |
| `/educacao-infantil/dia-da-crianca` | SSG | CMS | — |
| `/educacao-infantil/proposta-pedagogica` | SSG | CMS | — |
| `/educacao-infantil/alimentacao-e-saude` | SSG | CMS | — |
| `/educacao-infantil/estrutura` | SSG | CMS + galeria | — |
| `/educacao-infantil/depoimentos` | SSG | CMS | — |
| `/educacao-infantil/matricula` | SSR | CMS + form | **Sim** |
| **Contraturno** | | | |
| `/contraturno` | SSG | CMS | — |
| `/contraturno/o-projeto` | SSG | CMS | — |
| `/contraturno/para-quem-e` | SSG | CMS | — |
| `/contraturno/como-funciona` | SSG | CMS | — |
| `/contraturno/parceiros` | SSG | CMS | — |
| `/contraturno/o-que-vem-por-ai` | SSG | CMS | — |
| `/contraturno/inscricao` | SSR | CMS + form | **Sim** |
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

`:tipo` ∈ `matricula`, `inscricao`, `coleta`, `voluntariado`, `contato`, `parceria`.

## 1.4 Páginas separadas — decidido

Cada subpágina tem URL própria e indexável. O mapa da §1.2 já reflete isso.

Motivo: cada URL disputa uma busca distinta, e `/quem-somos/o-lar-hoje` precisa de endereço
próprio dado o peso reputacional — é a página que deve aparecer quando alguém busca o nome
da instituição junto do episódio de 2022.

Consequência a acompanhar: algumas páginas de "Contraturno" nascem com pouco conteúdo, já
que o programa é novo. Página magra posiciona mal. Se depois da redação alguma ficar com
menos de ~300 palavras sem previsão de crescer, vale unir à página-pilar em vez de publicar
vazia. Decisão de conteúdo, não de arquitetura — não bloqueia a implementação.

---

# Parte 2 — Formulários

Seis formulários. **Dois deles coletam dados de menores** — são a superfície de maior risco
de todo o projeto.

## 2.1 Manifestação de interesse — decidido

**Nenhum formulário público coleta dado identificável de criança ou adolescente.**

O site recebe apenas a manifestação de interesse do responsável: nome e contato dele, faixa
etária da criança, período pretendido. Os dados completos — nome da criança, nascimento,
documentos, endereço, escola — são coletados **presencialmente**, no momento da matrícula
efetiva, junto com o termo de consentimento assinado.

Isso é regra de arquitetura, não preferência de produto. Consequências que valem em todo o
projeto:

- O titular dos formulários 1 e 2 é o **responsável adulto**, não a criança. O art. 14 da
  LGPD, com sua exigência de consentimento específico de responsável, não se aplica a esses
  registros — o que simplifica bastante o consentimento web.
- **Faixa etária**, nunca data de nascimento. Data de nascimento identifica; faixa não.
- Nenhum campo livre deve induzir o preenchimento do nome da criança. O rótulo do campo de
  mensagem precisa ser explícito: dados da criança são coletados presencialmente.
- Se alguém escrever o nome da criança no campo livre mesmo assim, o registro passa a conter
  dado de menor. O campo de mensagem desses dois formulários é criptografado por precaução.

Nenhuma tabela de assistido é alimentada por endpoint público, em nenhuma hipótese.

## 2.2 Inventário

| # | Formulário | Rota | Titular | Campos | Retenção sugerida |
|---|---|---|---|---|---|
| 1 | Matrícula / lista de espera | `/educacao-infantil/matricula` | Responsável (adulto) | Nome, telefone, e-mail, faixa etária da criança, período pretendido, mensagem | 12 meses após contato |
| 2 | Inscrição contraturno | `/contraturno/inscricao` | Responsável (adulto) | Nome, telefone, e-mail, idade do adolescente, escola `[VALIDAR]`, turno livre | 12 meses |
| 3 | Agendar coleta | `/bazar/agendar-coleta` | Doador (adulto) | Nome, telefone, endereço, itens, janela de disponibilidade, fotos (opcional) | 6 meses após coleta |
| 4 | Voluntariado | `/como-ajudar/voluntariado` | Voluntário (adulto) | Nome, telefone, e-mail, disponibilidade, área de interesse | 24 meses |
| 5 | Apoiar projeto / empresas | `/contraturno/apoiar` | Contato PJ | Empresa, CNPJ, contato, telefone, e-mail, tipo de apoio | 36 meses |
| 6 | Contato | `/contato` | Visitante | Nome, e-mail, assunto, mensagem | 6 meses |

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
| GET | `/api/v1/public/pages/{slug}` | Página institucional |
| GET | `/api/v1/public/posts` | Notícias, paginado, filtro por categoria |
| GET | `/api/v1/public/posts/{slug}` | Notícia |
| GET | `/api/v1/public/transparency-documents` | Filtro por ano e tipo |
| GET | `/api/v1/public/transparency-documents/{uuid}/download` | Download com contagem |
| GET | `/api/v1/public/testimonials` | Depoimentos autorizados |
| GET | `/api/v1/public/partners` | Parceiros e apoiadores |
| GET | `/api/v1/public/bazaar/showcase` | Vitrine "novidades da semana" |
| GET | `/api/v1/public/stats` | Números da home |
| GET | `/api/v1/public/settings` | Endereços, horários, PIX, redes sociais |
| GET | `/api/v1/public/search?q=` | Busca interna |

`settings` é um único endpoint com os dados institucionais que aparecem no rodapé e em
várias páginas — evita seis chamadas para montar o layout.

## 3.2 Escrita

Todas com rate limit agressivo e honeypot.

| Método | Rota |
|---|---|
| POST | `/api/v1/public/enrollment-interests` |
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
| **Início** | Painel com pendências: novos interesses, coletas a agendar, mensagens não lidas | todos, filtrado pelo papel |
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

O enum atual (`social_work`, `psychology`, `pedagogy`) veio do desenho de acolhimento e não
se aplica mais. Substituir por:

```php
enum Role: string
{
    case SuperAdmin = 'super_admin';
    case Direcao = 'direcao';
    case Atendimento = 'atendimento';
    case Bazar = 'bazar';
    case Comunicacao = 'comunicacao';
}
```

**Princípio:** os papéis são modelados pelo **tipo de dado que tocam**, não por cargo.
Organograma muda; a classificação do dado não.

Três níveis de dado no sistema:

1. **Conteúdo público** — páginas, notícias, mídia, documentos. Nenhum dado de pessoa.
2. **Formulários recebidos** — dado de adulto: responsáveis, doadores, voluntários, empresas.
3. **Cadastro de assistidos** — dado de menor. Ainda não implementado.

| Papel | Conteúdo | Formulários | Assistidos | Usuários |
|---|---|---|---|---|
| `super_admin` | total | total | total | total |
| `direcao` | total | total | total | — |
| `atendimento` | leitura | total | — | — |
| `bazar` | só vitrine | só coletas | — | — |
| `comunicacao` | total | **nenhum** | **nenhum** | — |

**Notas de desenho:**

- `super_admin` é a única conta que gerencia usuários e papéis. `direcao` enxerga tudo
  operacional mas não cria acesso — conceder acesso deve ser sempre ato deliberado.
- `bazar` é separado porque pedido de coleta contém **endereço residencial** do doador, e
  porque a equipe do bazar tende a ser própria e rotativa.
- `comunicacao` é o papel mais provável de ser terceirizado. **Não enxerga nenhum formulário
  recebido.** Exige teste Pest afirmando isso explicitamente.
- Não há papel `financeiro`: publicar documento de transparência é ato de direção, e o
  contador externo não precisa de login.
- `secretaria_cei` entra quando o cadastro de assistidos existir, para separar quem vê dado
  de criança de quem vê formulário de adulto.
- Dividir papel depois é simples; juntar é que dá trabalho. Se `atendimento` se revelar
  amplo demais, divide-se em `secretaria` e `contato`.

## 4.5 Endpoints administrativos

CRUD padrão sob `/api/v1/` para: `pages`, `posts`, `media`, `testimonials`, `partners`,
`transparency-documents`, `stats`, `settings`, `enrollment-interests`,
`program-applications`, `pickup-requests`, `volunteer-applications`,
`partnership-inquiries`, `contact-messages`, `users`, `roles`.

Além disso:

| Método | Rota | Uso |
|---|---|---|
| PATCH | `/api/v1/{recurso}/{uuid}/status` | Mudança de status com anotação |
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

Em aberto — nenhum bloqueia a implementação:
- [ ] `[VALIDAR]` Campos de cada formulário com quem hoje faz esse atendimento
- [ ] `[VALIDAR]` Prazos de retenção
- [ ] `[VALIDAR]` Se a vitrine do bazar terá preço ou só foto e descrição
- [ ] Domínio definitivo
