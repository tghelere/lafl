> **Modelo recomendado: Sonnet**

# 01 — Conteúdo institucional e escopo de lançamento

Correções de texto e de navegação. Nenhuma funcionalidade nova. Leia `docs/tarefas/README.md`.

## Decisões do cliente que regem esta tarefa

1. **O site não menciona o processo judicial de 2022 em nenhuma página** — nem de forma
   indireta ("se reconstruir", "troca de diretoria por decisão judicial", "quem busca
   informação sobre o processo").
2. **Escopo de lançamento fechado:** nossa história, governança, educação infantil,
   contraturno, bazar, como ajudar, transparência e contato. Voluntariado, empresas parceiras,
   novidades do bazar e fotos com rosto de criança ficam para depois.
3. **Datas confirmadas:** associação fundada em 12/07/1953; obra da sede iniciada em
   18/04/1957; sede inaugurada em 15/11/1963 (placa na parede da sede).
4. **Contraturno:** crianças e adolescentes de 6 a 15 anos — nunca "adolescentes" sozinho.

## Etapa 1 — Registrar as decisões

Em `docs/contexto.md`, registrar as decisões 1 a 3 acima como fatos confirmados pelo cliente,
substituindo o que as contradiz (seção "Histórico recente", lacuna de status processual,
"Controles adotados após 2022" — essa lacuna deixa de ser pendência de conteúdo do site).
Em `docs/roadmap.md`, remover a seção "BLOQUEIO DE PUBLICAÇÃO" e todo item que dependa dela.

## Etapa 2 — Remover toda menção a 2022 do conteúdo

No `ContentPagesSeeder`:

- `quem-somos/o-lar-hoje`: remover a página do array por completo (não basta Draft). Como o
  seeder usa `updateOrCreate`, acrescentar remoção explícita dessa linha quando existir, para o
  banco de desenvolvimento não manter o texto.
- `quem-somos`: remover "inclusive os pontos em que precisou se reconstruir".
- `quem-somos/governanca`: remover o parágrafo sobre 2022.
- `quem-somos/nossa-historia`: remover o marco 2022; acrescentar 1953 (fundação da
  associação), 1957 (início da obra da sede) e 1963 (inauguração da sede), em ordem.
- `quem-somos/missao-visao-valores`: remover as duas menções a 2022.
- `transparencia`: remover a frase sobre "quem busca informação sobre o processo de 2022".
- Atualizar o cabeçalho do seeder que diz que nenhuma página publica ano de fundação.

Remover os comentários "O Lar hoje removido daqui... devolver quando voltar a Published" de
`app/config/navigation.ts` e `app/config/siteNav.ts`.

Depois: `grep -rniE "2022|judicial|minist[eé]rio p[uú]blico|reconstru|acolhimento"` em
`backend/database`, `frontend-site/app` e `frontend-admin/src`. O que sobrar precisa ter motivo
(ex.: "Termo de Colaboração 06/2022" é nome de documento e fica). Listar no relatório.

## Etapa 3 — Páginas que não vão ao ar no lançamento

- `quem-somos/missao-visao-valores`, `educacao-infantil/dia-da-crianca` e
  `educacao-infantil/depoimentos` passam a `PageStatus::Draft` (texto de rascunho, conteúdo de
  uma frase e página sem depoimento, respectivamente) e saem de `navigation.ts` e `siteNav.ts`.
  O texto "Este texto é um rascunho de trabalho" sai do conteúdo público da página de missão —
  o status de rascunho é controle interno.
- `/como-ajudar/voluntariado` e `/contraturno/apoiar`: continuam existindo, mas com
  `robots: 'noindex, nofollow'` via `useSeoMeta`.
- Menu "Seja parceiro" → rótulo "Parceiros" (a página lista parceiros, não oferece parceria).
- `como-ajudar`: remover voluntariado e apoio de empresas do texto e da `meta_description`.
- `siteNav.ts` aponta `Doar` para `/como-ajudar/doar`, que é um 301 para `/doar` — apontar
  direto para `/doar`.

## Etapa 4 — Textos

- Home (`index.vue`): "prepara uma escola de contraturno para adolescentes" → para crianças e
  adolescentes de 6 a 15 anos. Mesma correção no `hint` de `navigation.ts` (aparece no menu e em
  `/o-que-fazemos`).
- Home: "o acervo de prestação de contas está todo publicado" → frase que não afirme
  completude (a contagem real vem na tarefa 02).
- `contraturno`: "avise-se para saber assim que as inscrições abrirem" → "cadastre-se para ser
  avisado quando as inscrições abrirem".
- `nossa-historia.vue`: o parágrafo da fachada "em laranja vivo... dia em que a tinta ficou
  pronta" soa como nota interna. Reescrever neutro: as fotos registram reformas concluídas e
  não mostram necessariamente o estado atual da sede.
- Conferir `meta_description` de toda página alterada.

## Verificação

Pest, Pint, Larastan, `npm run build`/`generate` do site, `lint`/`build` do painel, bateria e2e
(algum teste pode depender de `o-lar-hoje` ou dos itens de menu — ajustar o teste, não a regra).
`php artisan migrate:fresh --seed` + `cache:clear` e conferir no Firefox as páginas alteradas.
