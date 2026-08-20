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
| Testes | Pest |

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

## Como trabalhar neste projeto

- Ao criar entidade que envolva assistido, **leia `@docs/protecao-de-dados.md` antes de
  escrever a migration**. A classificação do dado define o tipo da coluna.
- Em dúvida sobre exposição de dado pessoal, escolha sempre a opção mais restritiva e
  registre a dúvida em vez de decidir sozinho.
- Não instale dependência nova sem justificar; a superfície de ataque importa mais aqui que
  conveniência.
