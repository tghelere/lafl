# Relatório — Sessão 11 (Conteúdo institucional e escopo de lançamento)

> Escopo fechado, definido em `docs/tarefas/01-conteudo-e-escopo-de-lancamento.md`: aplicar ao
> conteúdo do site as decisões que o cliente confirmou — o site nunca menciona o processo
> judicial de 2022, o escopo de lançamento é fechado, três datas de fundação/obra/inauguração
> da sede ficam confirmadas — e corrigir um punhado de textos e rótulos de navegação. Nenhuma
> funcionalidade nova.

## Etapa 1 — Registrar as decisões

`docs/contexto.md`: a seção "Histórico recente" foi reescrita em torno da decisão do cliente,
não do episódio em si. As duas lacunas que só existiam por causa da página
`quem-somos/o-lar-hoje` (status processual do caso, controles adotados desde 2022) deixam de
ser pendência de conteúdo do site — ficam registradas como podendo voltar a interessar se a
instituição um dia decidir tratar o assunto publicamente, mas não bloqueiam nem orientam nada
do lançamento. Os fatos do episódio continuam registrados, num parágrafo explicitamente
marcado como "nunca para publicação" — apagá-los do documento de contexto pareceu mais arriscado
que mantê-los isolados e rotulados, já que uma sessão futura pode precisar entender por que
certas frases nunca devem voltar ao site. Acrescentei também as três datas confirmadas
(fundação 1953, obra 1957, inauguração 1963) perto do parágrafo que já tratava da confusão
1953/1963.

`docs/roadmap.md`: a seção "BLOQUEIO DE PUBLICAÇÃO" foi removida, junto da referência a ela no
changelog da sessão que a criou (só o ponteiro morto — a narrativa histórica em si ficou
intacta).

## Etapa 2 — Remover toda menção a 2022

`quem-somos/o-lar-hoje` saiu do array do `ContentPagesSeeder` por completo. Como o seeder usa
`updateOrCreate` (que só cria/atualiza, nunca apaga o que sai do array), acrescentei
`Page::query()->withTrashed()->where('slug', 'quem-somos/o-lar-hoje')->forceDelete()` no início
de `run()` — `forceDelete` em vez de `delete()` porque `Page` usa `SoftDeletes`, e um soft
delete manteria o texto na tabela (só oculto) e não disparia o `cascadeOnDelete` de
`page_slug_history`. Um banco de desenvolvimento que já tinha essa página (mesmo em Draft) fica
limpo depois de rodar o seeder de novo, sem precisar de `migrate:fresh`.

As demais menções diretas e indiretas ao processo saíram de `quem-somos`, `quem-somos/nossa-historia`,
`quem-somos/governanca`, `quem-somos/missao-visao-valores` e `transparencia`. Um caso pediu
julgamento: a frase de Valores "durante a troca de diretoria e o fim do acolhimento
institucional em 2022" não é só uma data solta — "troca de diretoria" é exatamente uma das
formas indiretas que a decisão do cliente veta. Reescrevi para "a creche funciona sem
interrupção desde 2002", sem essa referência.

`nossa-historia` ganhou os três marcos confirmados (1953, 1957, 1963) na linha do tempo, em
ordem cronológica, substituindo o marco de 2022.

Rodei o grep pedido (`2022|judicial|ministério público|reconstru|acolhimento` em
`backend/database`, `frontend-site/app`, `frontend-admin/src`) depois das edições. Sobrou:

- `Termo de Colaboração 06/2022` (duas ocorrências) e três títulos de documento no
  `TransparencyDocumentsSeeder` ("Balanço patrimonial 2022", "Estatuto social" ano 2022, "Ata
  de assembleia geral — eleição de diretoria 2022") — nomes/metadados de documento real do
  acervo de transparência, mesma categoria do exemplo dado na tarefa. Ficam.
- Um comentário meu no topo do `ContentPagesSeeder` explicando a decisão — necessário para
  quem ler o código depois.
- "acolhimento" na missão (o antigo serviço, sem menção a 2022 nem ao processo) — descreve o
  tipo de operação que a instituição fazia antes, não o caso judicial.
- `LedgerLine.vue`: um exemplo de formato de data no JSDoc ("2022–26") — sem relação nenhuma
  com o assunto, falso positivo do grep.

Por consistência, também tirei a linha `/quem-somos/o-lar-hoje` da tabela de rotas de
`docs/estrutura-site.md` e reescrevi o parágrafo do §1.4 que justificava URL própria para essa
página "dado o peso reputacional... o episódio de 2022" — não estava no escopo literal da
Etapa 2 (que só falava do grep em código, não em `docs/`), mas deixar essa referência viva
contradiria a decisão 1 na própria documentação de arquitetura.

## Etapa 3 — Páginas fora do lançamento

`missao-visao-valores`, `educacao-infantil/dia-da-crianca` e `educacao-infantil/depoimentos`
passam a `PageStatus::Draft` e saem de `navigation.ts`/`siteNav.ts`. A primeira também perdeu a
frase "Este texto é um rascunho de trabalho" do conteúdo público — o status agora é o único
controle, como já valia para `o-lar-hoje`.

`/como-ajudar/voluntariado` e `/contraturno/apoiar` ganharam `robots: 'noindex, nofollow'` via
`useSeoMeta`, confirmado por `curl` na tag `<meta name="robots">` renderizada.

"Seja parceiro" virou "Parceiros" no menu. `como-ajudar` perdeu voluntariado e apoio
empresarial do texto e da `meta_description`. `siteNav.ts` aponta `Doar` direto para `/doar`
em vez de passar pelo 301 de `/como-ajudar/doar`.

## Etapa 4 — Textos

Faixa etária do contraturno corrigida na home e no `hint` de `navigation.ts` (que também
aparece em `/o-que-fazemos`, conferido em navegador). "Avise-se" virou "cadastre-se para ser
avisado" no `ContentPagesSeeder`. O parágrafo da fachada em `nossa-historia.vue` foi reescrito
em tom neutro, como pedido.

**Achado durante a conferência em navegador, fora do texto literal da tarefa:** a home tinha
*duas* afirmações de completude sobre o acervo de transparência, não uma — a tarefa citava
"o acervo de prestação de contas está todo publicado" (corrigida), mas a nota da linha de
números logo acima também dizia "veja o acervo **completo** em Transparência". Corrigi as duas,
pelo mesmo motivo: a contagem real do acervo é entregável da tarefa 02.

## Verificação

- Pint, Larastan (`--memory-limit=512M`) e Pest: verdes (336 testes, 784 asserções) — inclui
  `ContentSanitizerTest`, que garante que todo `content` editado continua em forma canônica do
  editor.
- `frontend-site`: `npm run build` e `npm run generate` verdes.
- `frontend-admin`: `npm run lint` e `npm run build` verdes — nada mudou neste pacote, rodado
  por completude.
- Bateria de ponta a ponta (`e2e/`, Playwright + Firefox): 25 testes, todos verdes, **sem
  precisar ajustar nenhum teste** — o teste de ida e volta do editor (`tests/paginas/editor.spec.ts`)
  abre justamente `contraturno` e `quem-somos/nossa-historia`, duas páginas editadas nesta
  sessão, e confirma que salvar sem alterar não muda o `content` gravado.
- `php artisan migrate:fresh --seed` + `cache:clear` rodados no banco de desenvolvimento
  (`lar_analia_franco`).
- Conferência visual: `quem-somos/o-lar-hoje`, `quem-somos/missao-visao-valores`,
  `educacao-infantil/dia-da-crianca` e `educacao-infantil/depoimentos` devolvem HTTP 404;
  `robots: noindex, nofollow` confirmado em `voluntariado` e `apoiar`; ausência das frases
  banidas confirmada por `curl` em `governanca`, `nossa-historia`, `quem-somos` e
  `transparencia`; rótulo "Parceiros" e texto "cadastre-se para ser avisado" confirmados. Home
  e `/o-que-fazemos` abertos via MCP do Playwright contra o servidor de desenvolvimento real —
  **esse navegador roda em Chromium neste ambiente, não Firefox** (`navigator.userAgent`
  conferido). A cobertura real em Firefox veio da bateria de ponta a ponta, que passou pelas
  três páginas de conteúdo citadas acima através do editor de verdade. Ninguém abriu o site num
  Firefox interativo fora do Playwright — vale uma passada humana antes de publicar,
  especialmente no menu "O que fazemos" com o hint mais longo do contraturno (conferido sem
  quebra de layout no Chromium, mas não visto em Firefox).

## Decisões tomadas sem consulta

1. `forceDelete` em vez de `delete()` para `quem-somos/o-lar-hoje` no seeder — soft delete
   deixaria o texto sobre 2022 fisicamente na tabela, só oculto, o que pareceu contrariar o
   espírito da decisão do cliente.
2. Reescrita da frase de Valores sobre "troca de diretoria" (não só a data) — julguei que
   "troca de diretoria" é ela mesma uma referência indireta ao processo, coberta pela decisão 1
   do cliente, mesmo sem a palavra "judicial" do lado.
3. Atualização de `docs/estrutura-site.md` (linha da rota e §1.4) — fora do escopo literal da
   Etapa 2, mas a alternativa (deixar rationale citando "o episódio de 2022" num documento de
   arquitetura ativo) pareceu pior.
4. Segunda frase de completude do acervo de transparência na home, além da citada na tarefa —
   mesma categoria de problema, resolvida junto.

## Pendências e o que precisa de conferência humana

- Ver navegador humano (não automação) antes de publicar: nenhuma passada foi feita fora do
  Chromium do MCP e do Firefox da bateria de e2e.
- `docs/tarefas/02-numeros-calculados.md` até `09-documentacao.md`, mais `docs/tarefas/README.md`
  e `shared/brand/`, estão sem commit — eram untracked já no início desta sessão (não criados
  por ela) e pertencem ao escopo de sessões futuras; deixados como estavam, não commitados por
  esta sessão.
- Tudo o mais listado como pendente em `docs/roadmap.md` continua igual; nada desta sessão
  alterou funcionalidade, só conteúdo e navegação.
