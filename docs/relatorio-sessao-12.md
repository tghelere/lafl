# Relatório da sessão 12 — números calculados em vez de texto fixo

Execução de `docs/tarefas/02-numeros-calculados.md`, mais um pedido avulso feito no início da
sessão (o comentário do ledger no CSS do site).

## O que foi entregue, por etapa

### 0. Pedido avulso e registro do escopo — `761303c`

O comentário da variante `entry` em `frontend-site/app/assets/css/components.css` citava "a
cronologia do Lar hoje", página retirada do escopo de lançamento na sessão 11. O comentário
agora descreve o que a variante faz e registra que ela está **sem uso nenhum no site** —
`LedgerLine.vue` ainda oferece a prop `variant`, mas nenhuma página passa `variant="entry"`.
Não removi o CSS nem a prop: isso é decisão de `docs/tarefas/03-alinhamento-visual.md`.

No mesmo commit entraram `docs/tarefas/02-numeros-calculados.md` e `docs/tarefas/README.md`,
como manda a regra do índice de tarefas.

### 1. Fonte única e serviço de cálculo — `29f8458`

`config/institution.php` passa a declarar os cinco marcos. **O formato do valor declara a
precisão**, e a precisão muda a conta:

| Valor | Precisão | Conta |
|---|---|---|
| `'1953-07-12'` | dia | aniversário respeitado — em 11/07/2026 ainda são 72 anos |
| `'1968'` | ano | diferença de ano |

`App\Services\InstitutionalFacts` calcula idade e a quantidade de documentos de transparência
publicados (mesmo critério da listagem pública: `published()` filtra o despublicado, o
SoftDeletes filtra o excluído). Tudo sai **já formatado em português**, com substantivo e
plural corretos e milhar com ponto — quem consome nunca monta a frase, que é o que impede
"71 documento".

Fuso `America/Sao_Paulo`, não o UTC da aplicação: das 21h à meia-noite de Londrina o UTC já
está no dia seguinte. Há teste específico para essa fronteira.

`App\Enums\ContentMarker` declara os cinco marcadores.

**11 testes Pest**: idade antes/no/depois do aniversário com tempo congelado, marco com só o
ano, fronteira de fuso, plural de 0/1/2 para idade e para contagem, milhar, contagem
acompanhando publicar/despublicar/excluir, formato inválido no config falhando alto.

### 2. Marcadores no conteúdo do CMS — `b49bac4`

`{{idade_associacao}}`, `{{idade_sede}}`, `{{idade_bazar}}`, `{{idade_cei}}` e
`{{documentos_transparencia}}` podem ser escritos dentro do texto pelo painel. São texto puro
e atravessam o editor Tiptap e o `ContentSanitizer` sem alteração — `{` e `}` não são sintaxe
de HTML, e há teste medindo isso em vez de supor.

As três regras que o desenho depende, cada uma com teste:

1. **Resolução só na leitura pública.** O endpoint administrativo devolve o marcador cru — se
   o painel recebesse "73 anos", o primeiro salvamento gravaria esse texto e o cálculo morreria
   em silêncio.
2. **Resolução depois do cache** de `ResolvePublicPageBySlug`. O cache guarda o conteúdo cru
   por dez minutos; a substituição roda sobre o que sai dele. Publicar, despublicar ou excluir
   um documento muda a contagem já na requisição seguinte.
3. **Marcador desconhecido recusado ao salvar** (422, mensagem em português listando os
   válidos), antes de gravar qualquer coisa.

Como a resposta agora muda sem a página ter sido editada, página com marcador passa a
`max-age=0, must-revalidate` e o ETag inclui o conteúdo **já resolvido** — quem revalida leva
304 enquanto o número não muda. Página sem marcador continua com `max-age=300`.

### 3. Endpoint público de fatos e home em SSR — `5e9c08a`

`GET /api/v1/public/institution-facts` devolve cada marco com ano, data (quando o dia é
conhecido), idade crua e idade formatada, mais a contagem do acervo crua e formatada.

A linha de registro da home usa esse endpoint: acabou o `date="58 anos"` fixo. **A home saiu
de `nitro.prerender.routes`** — prerenderizada, congelaria a idade no dia do build.

### 4. Lista de marcadores no painel — `3bed387`

`GET /api/v1/content-markers` (autenticado, mesma ability de ver páginas) devolve nome,
marcador, rótulo e valor de agora. O editor de páginas lista os cinco abaixo do campo de
conteúdo, com "hoje: 12 documentos" ao lado de cada um. Zero cálculo no painel.

### 5. Conteúdo — `d785633`

`transparencia` trocou "hoje com cerca de 70 documentos" por
"hoje com `{{documentos_transparencia}}`".

Varredura do repositório inteiro por contagem e idade fixas
(`grep -rnE "[0-9]+ (anos|documentos)"` mais "cerca de", "mais de N", "há N"). O que apareceu
além disso **não é cálculo e ficou literal**: faixa etária de criança (1 a 5 anos, 6 a 15
anos, acima de 60), fato histórico sobre Anália Franco (mais de 70 escolas e 23 asilos), 15
turmas do plano de trabalho 2026, repasse de R$ 2.819.892,84 e os anos de acontecimento
(1953, 1957, 1963, 1968, 2002, 2016).

### 6. Ponta a ponta — `10a95ed`

`e2e/tests/paginas/numeros-calculados.spec.ts`, três fluxos: escrever `{{idade_bazar}}` no
editor e ver o número no site (com o painel continuando a guardar o marcador cru); marcador
inventado recusado ao salvar sem gravar nada; publicar um documento aumentando a contagem de
`/transparencia` e excluí-lo devolvendo ao valor anterior.

O valor esperado vem de `/api/v1/content-markers`, nunca recalculado em TypeScript — nenhuma
regra de negócio mora no teste. O PDF sintético saiu do spec de transparência para
`e2e/support/fixtures.ts`, agora que dois specs sobem arquivo.

## Decisões tomadas sem consulta

1. **`InstitutionalFacts` NÃO é singleton do container.** Registrei como singleton primeiro e
   dois testes quebraram: dentro de um teste o container sobrevive entre requisições, e a
   contagem memorizada de antes de publicar o documento continuava valendo. Isso não é um
   defeito de teste — é o mesmo comportamento que Octane teria em produção. A instância que o
   grafo de dependências injeta já dura exatamente uma requisição, que é o tempo em que os
   valores são estáveis. O motivo está no docblock da classe, para ninguém "otimizar" de volta.
2. **A home não mostra as duas linhas calculadas se a API falhar**, em vez de cair para um
   número fixo de reserva. Número errado numa página de prestação de contas é pior que número
   a menos.
3. **Marcador desconhecido que já esteja gravado no banco vai cru para o site**, não some. Não
   deveria existir (a escrita recusa); se existir, aparecer é melhor que apagar o trecho em
   silêncio.
4. **Espaço em volta do nome é tolerado** (`{{ idade_bazar }}` funciona). Quem escreveu assim
   quis o mesmo marcador.
5. **`{{documentos_transparencia}}` no lugar exato onde estava "cerca de 70 documentos"**, sem
   reescrever o resto da frase — a redação é do cliente, aprovada na sessão 11.
6. **`headquarters_construction_started` (18/04/1957) está em `config/institution.php` mas não
   tem marcador.** A tarefa pediu quatro idades e o início da obra não é uma delas. O endpoint
   público expõe a idade dele junto com as outras, por ser um caminho de código só.
7. **`GET /api/v1/content-markers` sem paginação.** É um enum de cinco casos; mesmo tratamento
   de `/roles`. Diferente de `/roles`, passa por API Resource, como manda a regra 5 do
   `CLAUDE.md`.

## O que ficou de fora, e por quê

- **O CSS da variante `entry` do ledger e a prop `variant` de `LedgerLine.vue` continuam
  existindo sem uso.** Remover é decisão da tarefa 03.
- **Nenhuma frase do tipo "o bazar existe desde 1968" virou marcador.** Ano de acontecimento
  não envelhece; trocar por "há {{idade_bazar}}" seria reescrever texto aprovado pelo cliente
  sem ele ter pedido.
- **`institution_stats`** (previsto em `docs/estrutura-site.md`) continua não existindo. É
  outro problema — número que a instituição informa e atualiza à mão, não derivado. Registrei
  a distinção naquele documento e no ADR.

## Precisa de conferência humana

- **O dia do início do bazar (1968) e da criação do CEI (2002).** Enquanto for só o ano, a
  idade fica um ano adiantada de 1º de janeiro até o aniversário real. Registrado como
  `[LACUNA]` no roadmap. Confirmando o dia, o conserto é trocar `'1968'` por `'1968-MM-DD'` em
  `config/institution.php`.
- **O texto do bloco de marcadores no editor** ("Números que se calculam sozinhos" e a frase
  de explicação) — escrito para quem escreve o conteúdo na instituição, não para
  desenvolvedor. Vale alguém de lá ler.
- **A escolha dos rótulos** ("Tempo de funcionamento do bazar" vs. "Idade do bazar").

## Verificação

Tudo verde:

| O quê | Resultado |
|---|---|
| Pest | 364 testes, 875 asserções |
| Pint | limpo |
| Larastan | 0 erros (precisa de `--memory-limit=1G` nesta máquina; com o default de 128M o processo paralelo estoura) |
| `frontend-site`: `build` e `generate` | ok — `generate` prerenderiza 18 rotas, e a home não está mais entre elas |
| `frontend-admin`: `lint` e `build` | ok |
| Bateria de ponta a ponta | 28 testes, Firefox, `npm run test:e2e` |

Conferência visual no ambiente de desenvolvimento, via MCP do Playwright (**Chromium nesta
máquina, não Firefox** — a cobertura de Firefox veio da bateria de ponta a ponta), depois de
`db:seed --class=ContentPagesSeeder` + `cache:clear`:

- **home** — "1968 / Bazar beneficente em funcionamento desde / 58 anos" e "1953 / Fundação da
  associação / 73 anos";
- **`/transparencia`** — "hoje com 12 documentos" (12 é o que o `TransparencyDocumentsSeeder`
  planta no banco de desenvolvimento);
- **editor de `/transparencia` no painel** — o campo de conteúdo mostra
  `{{documentos_transparencia}}` cru, e o bloco abaixo lista os cinco marcadores com
  "hoje: 73 anos / 62 anos / 58 anos / 24 anos / 12 documentos".

Reseedei só o `ContentPagesSeeder`, **não** `migrate:fresh --seed`: o que mudou foi conteúdo
de página, e apagar o banco de desenvolvimento não era necessário.
