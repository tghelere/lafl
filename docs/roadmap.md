# Roadmap

> Atualizado ao fim de cada sessão de trabalho. Checkboxes refletem o que está
> implementado, não o que está planejado em `docs/estrutura-site.md`.

## Concluído

- [x] Backend: bootstrap Laravel, Postgres, Sanctum SPA mode, papéis (`spatie/laravel-permission`),
      auditoria (`spatie/laravel-activitylog`)
- [x] Infraestrutura de proteção de dados: normalização (`StringNormalizer`), blind index,
      cifra de campo customizada (`FieldEncrypted`)
- [x] Autenticação: login, logout, usuário atual, troca de senha autenticada
- [x] Scaffold do painel admin (Vue 3 + Vite + Pinia + Router) e do site público (Nuxt 4)
- [x] CI (Pint, Larastan, Pest, `composer audit`, `npm audit`, build dos dois frontends)
- [x] ADRs 0001–0008 em `docs/decisoes/`
- [x] Entidade `pages` ponta a ponta: migration, model, Action, FormRequest, Policy,
      Resources público/admin, endpoints de leitura pública e CRUD administrativo, testes Pest
- [x] Site público: `/quem-somos` e as quatro subpáginas (`nossa-historia`,
      `missao-visao-valores`, `governanca`, `o-lar-hoje`) consumindo `pages` via SSG, meta
      tags vindas da API, redirect 301 por slug antigo

## Em andamento

- [ ] Nenhum item em andamento no momento — próxima sessão começa do zero num item da lista
      abaixo

## Pendente

### Backend — entidades da Fase 1 restantes

- [ ] `posts`, `post_categories` (notícias)
- [ ] `media` + pipeline (MIME real, remoção de EXIF, conversão WebP, thumbnails em fila,
      armazenamento fora do webroot) — inclui a coluna `og_image_id` em `pages`, adiada nesta
      sessão porque `media` ainda não existe (ver decisão abaixo)
- [ ] `testimonials`, `partners`, `institution_stats`
- [ ] `transparency_documents`
- [ ] `settings`
- [ ] `bazaar_showcase_items`
- [ ] Formulários recebidos: `enrollment_interests`, `program_applications`,
      `pickup_requests`, `volunteer_applications`, `partnership_inquiries`, `contact_messages`
      — com rate limit, honeypot, job de descarte por `expires_at`
- [ ] Teste Pest explícito de que `comunicacao` não acessa nenhum formulário recebido

### Site público (Nuxt)

- [ ] Demais rotas de `docs/estrutura-site.md` §1.2 (Educação Infantil, Contraturno, Bazar,
      Como Ajudar, Transparência, Notícias, Contato)
- [ ] `sitemap.xml`/`robots.txt` consumindo conteúdo real (hoje geram só a partir de
      `NUXT_PUBLIC_SITE_URL`, sem `pages`/`posts`)
- [ ] JSON-LD `NGO`/`Organization`
- [x] Direção visual (paleta, tipografia, componentes base) — ver
      `docs/decisoes/0009-direcao-visual.md`
- [ ] Eventos Umami nos CTAs
- [ ] O menu principal (`AppHeader.vue`) e o rodapé (`AppFooter.vue`) já linkam as seis
      seções de `docs/estrutura-site.md` §1.1 que ainda não existem como página
      (`/educacao-infantil`, `/contraturno`, `/bazar`, `/como-ajudar`, `/transparencia`,
      `/contato`). Isso gera avisos `[VUE_ROUTER_R0004] No match found` no console do
      navegador em `nuxt dev` — esperado, não é bug (`crawlLinks: false` em
      `nuxt.config.ts` já impede que isso quebre `nuxt generate`, ver ADR 0009). O aviso some
      sozinho conforme cada seção ganhar página própria.
- [ ] **Bloqueia publicação:** a linha de registro (`LedgerLine.vue`, assinatura visual do
      sistema) exibe número institucional + data, sem fonte externa. Os valores usados até
      agora (250 crianças, 63 anos, 40% do orçamento) vêm de `docs/contexto.md` marcados
      `[CONFIRMAR]`/`[VALIDAR]` — não podem ir ao ar como estão. Toda instância do componente
      fora de exemplo marcado (`example` prop) precisa de número validado pela instituição
      antes do deploy.

### Painel administrativo (Vue)

- [ ] Todas as telas de `docs/estrutura-site.md` §4.2/§4.3 — nesta fase só existe a API
      administrativa de `pages`, sem tela nenhuma no painel ainda
- [ ] Editor de texto rico com sanitização no backend (Tiptap, a justificar como nova
      dependência quando a tela existir)
- [ ] Preview de SERP nos campos de SEO

### Validações pendentes com a instituição

- [ ] `[VALIDAR]` Campo `school` em `program_applications` — nome do rótulo e obrigatoriedade
      (o campo em si já está decidido: existe, `enc`, nullable — ver `docs/dominio.md`)
- [ ] `[VALIDAR]` Vitrine do bazar (`bazaar_showcase_items`) terá preço? Há quem alimente
      semanalmente? (schema já esboçado com `price` nullable — ver `docs/dominio.md`)
- [ ] `[VALIDAR]` Prazos de retenção exatos de cada formulário
- [ ] `[VALIDAR]` Convênio com a Secretaria Municipal de Educação impõe campo ou relatório?
- [ ] Preencher `docs/lgpd/inventario-de-dados.md` — bloqueia toda a Fase 2

### Decisões técnicas em aberto, registradas mas não bloqueantes

- [ ] `pages.og_image_id`: entra numa migration futura, junto da entidade `media` — decisão
      desta sessão, para não criar coluna sem uso funcional possível antes de `media` existir
- [ ] Cache Redis + ETag para os demais endpoints públicos (implementado só para `pages`
      nesta sessão)

### Armadilha de infraestrutura descoberta nesta sessão — vale para toda entidade futura

`config('cache.serializable_classes')` vem `false` por padrão no Laravel 13
(`config/cache.php`): `unserialize()` recusa reconstruir **qualquer objeto** vindo do cache
(Redis, database, file — todos os stores que passam por `unserialize()`), inclusive Eloquent
models e DTOs simples, e devolve silenciosamente `__PHP_Incomplete_Class` em vez de lançar
erro. Isso só aparece num cache HIT (segunda leitura em diante) — o primeiro request sempre
funciona porque recalcula em vez de ler do cache, o que torna o bug fácil de não notar em
teste manual rápido.

`App\Actions\Content\ResolvePublicPageBySlug` cacheia só array com campo escalar por causa
disso — nunca o `Page` model nem nenhum DTO. **Toda Action futura que usar `Cache::remember`
com algo além de array/escalar precisa do mesmo cuidado** (posts, transparency_documents,
settings, etc., quando existirem). Não afrouxar `serializable_classes` globalmente para
contornar isso — é uma trava de segurança deliberada contra injeção de objeto via cache
envenenado, e vale para o projeto inteiro, não só para este caso de uso.

### Limitações conhecidas de `pages` — aceitáveis no volume atual

- **`DeletePage` não impede apagar uma página-mãe que tenha filhas.** Excluir
  `quem-somos` com `quem-somos/nossa-historia` ainda existindo deixa a filha com slug
  órfão (o primeiro segmento não resolve mais a lugar nenhum). Consequência do modelo de
  slug plano (ver decisão em `docs/roadmap.md`, "Formato do slug", e
  `App\Actions\Content\SavePage::assertValidSlugDepth`, que só valida na escrita da
  filha, não na exclusão da mãe).
- **Renomear a mãe não propaga para as filhas.** `SavePage` grava histórico e invalida
  cache só do slug que está sendo salvo; renomear `quem-somos` para `sobre-nos` não move
  `quem-somos/nossa-historia` para `sobre-nos/nossa-historia` — a filha continua
  respondendo no prefixo antigo, que passa a não ter mãe.

Ambos aceitáveis com cinco páginas. Revisitar se o número de páginas crescer — provável
que a Fase de conteúdo com `posts`/mais seções de `docs/estrutura-site.md` §1.2 seja o
gatilho natural para decidir entre validar em cascata (bloquear exclusão/rename com
filhas) ou migrar para hierarquia real (`parent_id`).

## Fase 2 — bloqueada

- [ ] Cadastro de assistidos (`assisted_minors`, `guardians`, `guardianships`, `consents`,
      `health_records`, `classes`, `enrollments`, `attendance_records`) — **bloqueado** até
      `docs/lgpd/inventario-de-dados.md` ser preenchido com a instituição
