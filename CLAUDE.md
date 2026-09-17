# Lar Anália Franco — Site Institucional e Sistema Administrativo

## Contexto

Instituição sem fins lucrativos que atende **crianças e adolescentes**. O sistema tem duas
faces: um site público (institucional, transparência, captação de voluntários) e um painel
administrativo com dados de assistidos.

**Todo assistido é menor de idade e titular hipervulnerável perante a LGPD.** Isso não é um
detalhe de conformidade — é a restrição central que molda schema, autorização e API.

## Stack

| Camada | Tecnologia |
|---|---|
| Repositório | Monorepo: `backend/`, `frontend-site/`, `frontend-admin/`, `docs/` |
| API | Laravel — REST puro, sem views, versionada em `/api/v1` |
| Banco | PostgreSQL |
| Auth | Laravel Sanctum em **SPA mode** (cookie `httpOnly`, `SameSite=Lax`, CSRF) |
| Permissões | `spatie/laravel-permission` + Policies |
| Auditoria | `spatie/laravel-activitylog` |
| Site público | Nuxt 4 (SSR/SSG) — SEO é requisito de missão. Ver `docs/decisoes/0005-nuxt-4-em-vez-de-nuxt-3.md` |
| Painel admin | Vue 3 SPA + Vite + Pinia — `noindex`, atrás de login |
| Docs de API | Scramble (OpenAPI 3 gerado do código, sem annotation manual) |
| Cache/filas | Redis |
| Analytics | Umami (cookieless) + Google Search Console. **Sem GA4, sem banner de cookies.** |
| Testes | Pest (backend) + Playwright/Firefox (ponta a ponta, pilha real) |

## Regras invioláveis

Estas nunca são flexibilizadas. Se uma tarefa parecer exigir violá-las, **pare e pergunte**.

1. **Zero regra de negócio no frontend.** O front valida apenas para UX imediata. A API é a
   única fonte de verdade — inclusive para mensagens de erro. Permissão no front só esconde
   botão; quem barra é a Policy.
2. **Nenhum dado pessoal de assistido trafega ou é exposto sem passar por Policy.** Não
   existe endpoint "de conveniência" que devolva lista de assistidos com nome completo.
3. **Campos pessoais são criptografados** conforme `@docs/protecao-de-dados.md`. Nunca
   adicione coluna com dado pessoal em texto puro sem consultar aquele documento.
4. **IDs em rotas e payloads são UUID.** Nunca ID sequencial — enumeração de assistidos é
   vazamento.
5. **Nunca `$request->all()`.** Toda entrada passa por FormRequest; toda saída por API Resource.
6. **Nenhuma foto de assistido é publicável sem consentimento de imagem vigente.** A regra é
   travada na API, não na interface.
7. **EXIF é removido de todo upload de imagem**, sem exceção.
8. **O log de auditoria registra o acesso, nunca o valor descriptografado.**
9. **Migrations sempre reversíveis.** Nenhuma regra de negócio dentro de migration.
10. **Não commitar `.env`, chaves, dumps de banco ou dado real de assistido** — nem em
    fixture, nem em seeder, nem em teste. Seeders usam dados sintéticos.

## Convenções essenciais

- **Fluxo obrigatório:** `Route → Middleware → FormRequest → Controller (fino) → Action/Service → Model → API Resource`
- Método de controller com mais de ~15 linhas é sinal de regra de negócio no lugar errado.
- Regra de negócio mora em `app/Actions/` ou `app/Services/`. Nunca em controller, model,
  migration ou observer.
- Enums PHP para todo status, tipo ou categoria. Nunca string mágica.
- Idioma do código: **inglês** para tabelas, colunas, classes, métodos e variáveis.
  **Português** apenas para conteúdo voltado ao usuário (mensagens, labels, docs).
- Toda listagem é paginada. Sem exceção.
- Toda FK tem índice explícito (o Postgres não cria automaticamente).
- `Model::preventLazyLoading()` ativo em desenvolvimento.
- Teste Pest obrigatório para todo endpoint de escrita e todo endpoint que toque dado de
  assistido.
- **Toda funcionalidade nova do painel administrativo ganha um teste de ponta a ponta do seu
  fluxo principal** (`e2e/`). O painel não tem nenhuma outra rede de proteção automatizada, e
  todo defeito encontrado nele até hoje foi de integração — busca contra o banco real,
  comportamento do editor, colisão de CSS, reuso de instância de componente no vue-router.
  Nenhum apareceria num teste de componente com a API simulada. Um fluxo por funcionalidade,
  não cobertura exaustiva: o caminho que a pessoa percorre para fazer a coisa acontecer.
- Commits em português, formato Conventional Commits.

## Comandos

```bash
# Backend (a partir de backend/)
php artisan serve
php artisan migrate:fresh --seed
php artisan test                    # Pest
./vendor/bin/pint                   # formatação
./vendor/bin/phpstan analyse        # análise estática (Larastan, nível alto)
php artisan scramble:export         # gera openapi.json

# Site público (frontend-site/)
npm run dev
npm run build
npm run generate

# Painel admin (frontend-admin/)
npm run dev
npm run build
npm run lint

# Ponta a ponta (e2e/) — sobe API, painel e site sozinho; exige Postgres e Redis do compose
npm install
npm run test:e2e
```

## Estado do projeto

Projeto em fase inicial — nada foi implementado ainda. Ao criar estrutura nova, siga
`@docs/arquitetura.md`. Pendências conhecidas: domínio ainda não registrado (o cookie mode do
Sanctum exige front e API sob o mesmo domínio raiz), sem multi-idioma, sem gateway de
pagamento no escopo atual.

## Detalhamento

Consulte quando a tarefa exigir:

- `@docs/arquitetura.md` — estrutura de pastas, camadas, contrato da API, auth, deploy
- `@docs/protecao-de-dados.md` — LGPD, ECA, matriz de criptografia, consentimento, retenção
- `@docs/dominio.md` — entidades, relacionamentos, papéis e permissões
- `@docs/convencoes.md` — padrões de código detalhados, backend e frontend

## Armadilhas conhecidas

- Conteúdo público de página é cacheado por 10 minutos (`ResolvePublicPageBySlug`). Depois
  de reseedar, rode `cache:clear` antes de conferir no navegador — sem isso o site serve o
  conteúdo anterior e a alteração parece não ter surtido efeito.
- **PostgreSQL é o único banco suportado — desenvolvimento, teste e produção. Nunca
  reintroduzir SQLite, em lugar nenhum.** Um bug real (`LIKE` sensível a maiúsculas, que o
  Postgres respeita e o SQLite ignora) passou pela suíte inteira sem ser notado enquanto ela
  rodava em SQLite `:memory:`. Um segundo *motor* de banco recria esse buraco: "verde
  localmente" só significa "verde de verdade" quando tudo roda contra o mesmo banco de
  produção. Por isso não existem mais caminho de desenvolvimento em SQLite, conexão `sqlite`
  em `config/database.php`, nem guarda `DB::getDriverName()` em migration — o caminho Postgres
  é o único.

- **Os três bancos.** São três *bases* no mesmo Postgres do `docker-compose.yml`, todas
  PostgreSQL 16 — o que não se repete é o motor, não o nome da base. Separar existe porque
  dois dos três rodam `migrate:fresh`, e apagar o banco de desenvolvimento por engano é
  irreversível.

  | Banco | Arquivo de ambiente | Quem usa |
  |---|---|---|
  | `lar_analia_franco` | `backend/.env` | desenvolvimento (`php artisan serve`, `npm run dev`) |
  | `lar_analia_franco_test` | `backend/.env.testing` | suíte Pest **e** `backend/scripts/concorrencia/` |
  | `lar_analia_franco_e2e` | `backend/.env.e2e` | bateria de ponta a ponta (`e2e/`, `npm run test:e2e`) |

  `backend/phpunit.xml` só define `APP_ENV=testing` para carregar `.env.testing` — nunca
  duplicar config de banco ali de volta; o mesmo vale para `APP_ENV=e2e` e `.env.e2e`, que o
  `webServer` do Playwright injeta. Os dois arquivos são commitados de propósito (só valores
  fictícios) e têm exceção explícita no `.gitignore`.

  **Pest, os scripts de concorrência e a bateria de e2e não podem rodar ao mesmo tempo onde
  compartilham banco.** Pest e os scripts de concorrência dividem `lar_analia_franco_test` e os
  dois recriam as tabelas — rodar em paralelo dá falha sem sentido, nas duas pontas. A bateria
  de e2e tem banco próprio e pode rodar junto de qualquer um dos dois; o que ela não pode é
  rodar duas vezes em paralelo consigo mesma.

  Quem recria o banco de e2e é `php artisan e2e:prepare`, e só ele: o comando **recusa rodar**
  se o ambiente não for `e2e` ou se o banco resolvido não for `lar_analia_franco_e2e` — mesma
  guarda dupla de `backend/scripts/concorrencia/bootstrap.php`, pelo mesmo motivo (ele roda
  `migrate:fresh`). Ver README, seção "Testes e qualidade", e `e2e/README.md`.

- **Toda operação nova que possa reduzir o total de `super_admin` ativos (desativar usuário,
  remover papel, e qualquer outra que vier a existir) precisa passar por
  `App\Actions\Users\AssertLastActiveSuperAdminSurvives` — chamado dentro da mesma transação,
  antes da escrita.** A proteção depende de travar a linha certa (o papel `super_admin` em
  `roles`, como mutex) antes de contar; travar outra coisa (ex.: as linhas de `users`) não
  serializa nada — foi exatamente esse o bug corrigido na sessão 9 (ver
  `docs/relatorio-sessao-9.md`). A suíte Pest não alcança concorrência de verdade (uma conexão
  só, síncrona): qualquer mudança nessa Action ou nas Actions que a chamam precisa ser
  conferida com os scripts em `backend/scripts/concorrencia/` (dois processos reais contra o
  Postgres), não só com testes verdes.

## Como trabalhar neste projeto

- Ao criar entidade que envolva assistido, **leia `@docs/protecao-de-dados.md` antes de
  escrever a migration**. A classificação do dado define o tipo da coluna.
- Em dúvida sobre exposição de dado pessoal, escolha sempre a opção mais restritiva e
  registre a dúvida em vez de decidir sozinho.
- Não instale dependência nova sem justificar; a superfície de ataque importa mais aqui que
  conveniência.
