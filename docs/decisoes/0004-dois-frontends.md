# 0004 — Dois frontends (Nuxt SSR/SSG + Vue SPA) em vez de um só

## Contexto

O site público tem SEO como requisito de missão (`CLAUDE.md`): é a ferramenta central da
estratégia de deslocar a percepção pública do episódio de 2022 (`docs/contexto.md`), e boa
parte da audiência (buscadores, WhatsApp, redes sociais ao gerar preview) não executa
JavaScript ou o executa com atraso significativo. O painel administrativo, ao contrário, é
uso interno autenticado e não precisa de nenhuma dessas garantias.

## Decisão

Dois projetos de frontend separados, descritos em `docs/arquitetura.md`:

- `frontend-site/` — Nuxt, SSR para páginas com formulário e ISR para conteúdo que muda com
  frequência moderada (notícias, documentos de transparência, vitrine do bazar), SSG para o
  restante.
- `frontend-admin/` — Vue 3 SPA puro, `noindex`, atrás de login, sem necessidade de
  renderização no servidor.

Ambos usam a mesma sintaxe (Vue 3 `<script setup>`, Pinia), o que mantém baixo o custo
cognitivo de manter dois projetos em vez de um.

## Alternativas descartadas

- **Um único Nuxt cobrindo site e painel.** Exigiria SSR (ou ao menos hidratação) para telas
  que nunca precisam disso, e misturaria a superfície pública, indexável, com a superfície
  autenticada num mesmo build — motivo a mais para separar dado exposto de dado protegido, na
  mesma linha de raciocínio que rege a separação de Resources públicos e administrativos
  (`docs/estrutura-site.md`, Parte 3).
- **SPA único (Vue puro) para tudo, com pré-renderização estática à parte para SEO.**
  Resolveria parcialmente o problema de indexação mas não o de meta tag e Open Graph
  dinâmicos por página vindos da API, nem o de conteúdo que não pode depender de JS — exigiria
  reconstruir, por fora, boa parte do que SSR já resolve nativamente.

## Consequências

- Dois `package.json`, dois pipelines de build e lint, duas superfícies de deploy — custo
  aceito conscientemente.
- CI builda os dois frontends a cada push (ver `README.md`).
- Contrato de API único (`/api/v1`) serve ambos, mas com Resources diferentes por público —
  ver Parte 3 de `docs/estrutura-site.md`: o Resource público nunca expõe autor, rascunho ou
  timestamp interno.
