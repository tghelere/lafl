# Sessão 26 — Painel: acabamento visual e responsividade

Os sete itens de `docs/tarefas/10-acabamento-visual-do-painel.md`, entregues em doze commits.
Nenhuma linha de backend mudou: a sessão inteira é painel administrativo e bateria de ponta a
ponta. O requisito transversal da tarefa — **nada desalinhado** — está travado por teste onde é
mensurável: altura dos controles da barra de filtro (`layout/alinhamento.spec.ts`), centro
vertical do selo no título (`painel/cabecalho-de-tela.spec.ts`), degraus da trilha na mesma
linha de base (`painel/trilha-de-navegacao.spec.ts`) e altura dos alvos de toque
(`painel/responsividade.spec.ts`).

| Item | Commit |
|---|---|
| 1 — favicon, manifesto e título de aba por tela | `a059612` |
| 2 — item do menu aceso nas rotas filhas | `77d174a` |
| 3 — trilha de navegação sem degrau vazio | `2cc8fcf` (+ `63202be`, correção de um defeito anterior encontrado no caminho) |
| 4 — cabeçalho de tela único (`PageHeader`) | `c5505e7` |
| 5 — Source Sans 3 auto-hospedada | `801f26b` |
| 6 — ícones lucide | `d4ddc96` (+ `88ec803`, o teste que faltava) |
| 7a — menu lateral vira gaveta abaixo de 1024px | `a24df99` |
| 7b — listagens viram lista de cards abaixo de 768px | `d3eb2e2` |
| 7c — editor: barra rolável e Salvar no rodapé | `02e40e8` |
| 7d — alvos de toque de 44px | `93cc6eb` |

Mais três commits de fechamento, fora da tabela por não serem itens: o ajuste do teste de
ícones que a própria gaveta quebrou (ver "O que foi verificado"), os ponteiros para o ADR novo
nos arquivos em que a decisão é aplicada, e esta documentação.

---

## Item 1 — Identidade da aba (commit `a059612`)

Os cinco arquivos de ícone já estavam em `public/`, e só o SVG era declarado. O `index.html`
passa a declarar os três (`.ico`, `.svg`, `apple-touch`) na mesma ordem do site público, mais um
manifesto novo.

O título da aba era fixo em todas as telas. Cada rota passou a declarar `meta.title`, e um
`afterEach` do roteador escreve `<Tela> · Painel LAF`. **`afterEach`, e não `beforeEach`:** numa
navegação que acaba redirecionada — sessão expirada indo para `/login` — a aba ficaria com o
nome de uma tela que não chegou a abrir.

As cinco listagens de formulário são uma tela só, então o título vem da mesma
`SUBMISSION_RESOURCES` que a tela e o menu já usam, nunca de um segundo mapa escrito à mão.

## Item 2 — Menu aceso nas rotas filhas (commit `77d174a`)

Abrir o detalhe de um registro ou a edição de uma página apagava o item correspondente do menu:
quem marcava era o `.router-link-active` do vue-router, que compara URL — e
`/admin/paginas/{uuid}` não é `/admin/paginas`. A pessoa perdia a referência de onde estava
justamente na tela em que mais tempo passa.

Cada rota declara `meta.section` (`src/router/meta.ts`) e o menu acende o item cuja seção bate
com a da rota atual. **Não é comparação por prefixo de propósito:** "começa com" acenderia
`/admin/transparencia` em `/admin/transparencia-qualquer-coisa`.

Além da classe, o item aceso passou a declarar `aria-current="page"` — antes, nas rotas filhas,
não havia nem o realce visual.

## Item 3 — Trilha de navegação (commits `2cc8fcf` e `63202be`)

A edição de página mostrava `PÁGINAS / / EDITAR PÁGINA`: havia uma barra literal na marcação
**além** da que o CSS desenha no `::after`. As quatro trilhas do painel montavam cada uma a sua
`<nav>` à mão — foi assim que a barra sobrou em uma delas. Agora existe `AppBreadcrumb`, e o
separador continua sendo só do CSS: barra nenhuma é lida em voz alta nem copiada junto do texto.

O formato passou a ser `Páginas / Governança / Editar`, com o nome do registro no degrau do
meio. Como o nome só chega com a resposta da API, o degrau aparece como esqueleto enquanto
isso — nunca em branco —, anunciado como "Carregando…" para quem usa leitor de tela; se a
resposta não vier (404/403), o degrau some em vez de carregar para sempre.

**No detalhe de formulário a trilha é "Voluntários / Detalhe", sem o nome de quem enviou.** O
nome está na tela, sob a mesma Policy, mas a trilha é o que mais se copia para conversa e relato
de erro. Na dúvida sobre dado pessoal, a opção mais restritiva (`CLAUDE.md`).

O commit `63202be` corrige um defeito **anterior** a esta sessão, encontrado enquanto a trilha
era testada: `auditoria.spec.ts` abria a primeira linha da listagem de mensagens, que é sempre
uma não lida — e abrir um registro é o que o marca como lido. Isso gastava um dos dois não lidos
que o seeder deixa por tipo, e `formularios-leitura.spec.ts`, que roda depois na ordem
alfabética, ficava sem nenhum. Era uma falha que dependia da **ordem** da execução; conferida
numa árvore de trabalho no commit `bbbef5e`, onde os dois arquivos juntos falham igual.

## Item 4 — Cabeçalho de tela (commit `c5505e7`)

Cada tela montava o seu. A edição de página tinha um flex próprio; as de documento e de usuário
soltavam o selo num `<span>` avulso **abaixo** do título, que por isso caía para a linha de
baixo; as listagens penduravam o "+ Novo" dentro da barra de filtro, de onde ele saía
desalinhado dos campos.

`PageHeader` resolve os três: título, selo ao lado e ações à direita, na mesma linha. O selo fica
no centro vertical do título porque a linha é um flex com `align-items: center` — **não** por
margem calibrada à mão, que se desfaz assim que o tamanho do título muda. Título e selo andam
juntos quando a linha quebra; as ações é que descem.

## Item 5 — Source Sans 3 (commit `801f26b`)

O painel herdava a Lora do site. Faz sentido numa peça editorial; não numa tela de trabalho com
base de 14px, cheia de tabela, número e formulário, lida de perto por horas.

A fonte de corpo é sobreposta **só** no `tokens.css` do painel: o arquivo compartilhado com o
site continua intocado, e a `--font-display` (Poppins) continua vindo de lá, porque fonte de
título é de marca e é a mesma nas duas faces do sistema.

Source Sans 3 é variável no eixo `wght`: um arquivo por subconjunto cobre 400, 500 e 600, e o
descritor declara a **faixa** — declarar um valor fixo num arquivo variável faria o navegador
sintetizar o negrito em vez de interpolar o eixo.

## Item 6 — Ícones (commits `d4ddc96` e `88ec803`)

Entra `lucide-vue-next` com os ícones importados um a um — o pacote tem mais de mil, e um
`import * as` levaria todos para o bundle. Todo ícone passa por `AppIcon`: 18px, traço 1.75
(mais fino que o padrão, para não competir em peso com o texto de 13–14px) e `aria-hidden`
sempre, porque no painel o ícone nunca é a informação — ele acompanha um rótulo escrito.

O teste ficou faltando no commit do item e entrou em `88ec803`
(`e2e/tests/painel/icones.spec.ts`, 19 casos). O que ele trava:

- todo item do menu, todo card do Início, os botões com ícone de cada tela e todo selo têm um
  `svg.icon` — um nome fora do mapa de `src/config/icons.ts` não quebra nada, o `v-if` some com
  o ícone em silêncio e a tela fica só com o rótulo;
- **o mesmo recurso tem o mesmo desenho no menu e no card do Início**, que é a razão de o mapa
  existir. O pareamento é pelo endereço do link, não pelo rótulo: as duas telas escrevem o mesmo
  recurso com palavras diferentes ("Avisos de interesse no contraturno" no card, "Avisos do
  contraturno" no menu), e comparar texto faria o teste quebrar a cada ajuste de redação;
- todo ícone é `aria-hidden="true"` e `focusable="false"`, e o nome acessível do botão continua
  sendo só o rótulo;
- todo ícone é desenhado em 18px, medido na caixa renderizada — `.icon` fixa largura e altura em
  CSS além do atributo, e é o resultado das duas coisas juntas que importa.

---

## Item 7 — Responsividade

Quatro faixas, quatro commits, um por faixa, para que uma sessão interrompida no meio deixasse
salvo o que já estava pronto. O painel não tinha **nenhuma** media query antes desta sessão.

As convenções que saíram daqui — os dois limiares, a gaveta com foco preso, a tabela que vira
card e os 44px vindos dos tokens de altura de controle — estão registradas em
`docs/decisoes/0022-responsividade-do-painel.md`, com as alternativas descartadas e o que elas
exigem de toda tela nova do painel.

### 7a — Gaveta abaixo de 1024px (commit `a24df99`)

Abaixo de 64rem a navegação sai do fluxo e vira gaveta, aberta pelo botão Menu da barra
superior. A partir de 64rem nada muda: é a mesma coluna fixa de sempre.

O comportamento está em `src/composables/useNavDrawer.ts`:

- fecha ao navegar, ao clicar no cortinado, no botão Fechar e com Esc;
- devolve o foco ao botão Menu **quando o foco estava dentro da gaveta** — fechar clicando no
  cortinado deixa o foco no `<body>`, e puxá-lo para o botão nesse caso seria mover o foco de
  quem estava usando o mouse;
- **prende o foco enquanto está aberta.** Não é enfeite: a gaveta cobre o conteúdo, e sem isso o
  Tab seguiria pelos links da tela atrás do cortinado — invisíveis, mas focáveis;
- fechada, é `visibility: hidden` e não só um `transform` para fora da tela. Deslocar sem
  esconder deixa tudo focável do mesmo jeito;
- trava a rolagem de fundo enquanto está aberta.

A barra superior mantém usuário e Sair, com o nome em reticências: numa barra de 390px quem cede
espaço é o nome, nunca os dois botões.

**Decisões tomadas sem consulta:**

1. **O limiar de 64rem está escrito duas vezes**, no CSS e no composable. Um decide o desenho, o
   outro o comportamento (foco, Esc, cortinado, trava de rolagem), e não há como um ler o outro
   sem inventar um atributo só para isso. Está comentado nos dois lados, um apontando para o
   outro.
2. **A gaveta não declara `role="dialog"`.** Ela prende o foco como um diálogo, mas anunciar-se
   como diálogo traria expectativas de modal que ela não cumpre (não tem título próprio nem
   `aria-modal`). O botão que a abre declara `aria-expanded` e `aria-controls`, que é o par certo
   para um menu que se revela.
3. **O foco, ao abrir, vai para o primeiro elemento focável da gaveta** (a marca), não para o
   botão Fechar. É o comportamento padrão de um contêiner que recebe foco, e o Fechar fica a um
   Shift+Tab dele.
4. **Existe um botão Fechar dentro da gaveta**, além do Menu na barra. O Menu fica atrás do
   cortinado quando a gaveta está aberta — fechar sem tirar a mão de onde ela está é o gesto
   esperado.

### 7b — Listagens viram lista de cards abaixo de 768px (commit `d3eb2e2`)

Uma tabela de até seis colunas em 360px só existe de duas formas — rolando de lado ou com a
fonte ilegível —, e as duas obrigam a pessoa a caçar o que veio ver. Abaixo de 48rem cada linha
das **nove** telas de tabela do painel vira um card: o nome do registro como título, e cada valor
abaixo com o rótulo da sua coluna ao lado.

**Decisões tomadas sem consulta:**

1. **O rótulo de cada valor vem de `data-label` no `<td>`, desenhado no `::before`** — e o
   `<thead>` some junto. Manter os dois faria o leitor de tela anunciar cada nome de coluna duas
   vezes. O teste lê o `content` **calculado** do `::before`, não o atributo no HTML: o atributo
   provaria só que alguém o escreveu, não que ele chegou à tela.
2. **A primeira célula não leva rótulo.** "NOME" acima do próprio nome é ruído, e a linha inteira
   aproveita melhor a largura como título do card.
3. **O card mostra todas as colunas da tabela, não um recorte.** O pedido cita "nome, Recebido
   em, status, indicador de não lido", que são exatamente as colunas das cinco listagens de
   formulário; as outras quatro telas (páginas, transparência, usuários, auditoria) têm colunas
   próprias, e esconder alguma seria decidir por conta própria qual informação importa menos.
4. **Este bloco é `max-width`, ao contrário do resto do arquivo, que é mobile-first.** Está
   comentado no CSS: a forma normal deste componente é a tabela, e escrevê-la ao contrário
   exigiria desfazer regra por regra — `display`, `padding`, `border`, `white-space` — no caminho
   de volta. Um bloco só, que descreve a exceção e some inteiro se um dia ela deixar de existir,
   diz melhor o que está acontecendo.

Os filtros empilham em largura inteira na mesma faixa: lado a lado, em 360px, um `select` de
9rem mais um botão já estouram a linha.

### 7c — Editor de páginas (commit `02e40e8`)

São nove botões de formatação. Quebrados em três fileiras num celular, empurram para fora da
tela justamente o texto que está sendo escrito — abaixo de 48rem a barra passa a rolar de lado,
em uma linha só, com os botões em `flex: none` (sem isso eles encolheriam até o rótulo quebrar,
em vez de deixar a barra rolar).

O Salvar gruda no rodapé da tela na mesma faixa. O formulário tem editor, marcadores e dois
campos de busca: no celular são várias telas de rolagem, e um botão que só existe lá embaixo
obriga a percorrer tudo de volta a cada alteração. **É `sticky`, não `fixed`** — quando a página
chega ao fim, ele volta a ser o último elemento do cartão, sem cobrir nada.

### 7d — Alvos de toque de 44px (commit `93cc6eb`)

44px é o mínimo do critério 2.5.8 da WCAG 2.2. Um item de menu de 34px e um botão de paginação
de 32px são alvos que se erram com o polegar.

Botão, campo e select chegam lá pelas **duas alturas de controle**, que passam a valer
`--touch-target` abaixo de 64rem — e como continuam iguais entre si dentro de cada faixa, o
alinhamento da barra de filtro (requisito transversal da tarefa) não muda em largura nenhuma. O
que tem altura própria — item do menu, marca, paginação, botão do editor, link que abre o
registro no card, degrau da trilha e o nome na barra superior — ganhou `min-height` no mesmo
bloco.

**Decisões tomadas sem consulta:**

1. **A faixa vai até 64rem, não até 48rem.** Tablet é tela de toque tanto quanto celular, e é
   justamente ali que a listagem já voltou a ser tabela, com as ações de linha de volta.
2. **Acima de 64rem o painel mantém a densidade de sempre.** É tela de trabalho, lida de perto;
   44px por controle empurraria a listagem para fora da primeira dobra sem ganho nenhum. O teste
   trava os dois lados: nada abaixo de 44px em 390px, e 36px na barra de filtro em 1280px.
3. **O nome na barra superior virou `inline-flex`, não `flex`.** O `<span>` em volta corta o
   texto com reticências, e isso só funciona enquanto ele continuar sendo um bloco com conteúdo
   **em linha** — um filho `flex` viraria item de flex e levaria as reticências embora.

---

## Validação com capturas (item 7, último ponto)

Cinco larguras × quatro telas = 20 capturas, contra a pilha de desenvolvimento, em
`.playwright-mcp/depois/` (`depois-<tela>-<largura>.png`). A pasta é ignorada pelo git de
propósito — é saída de conferência manual em navegador, como a de `.playwright-mcp/antes/`, que
guarda o estado anterior à sessão.

| Tela | 360 | 390 | 768 | 1024 | 1440 |
|---|---|---|---|---|---|
| Início | ✓ | ✓ | ✓ | ✓ | ✓ |
| Listagem (mensagens de contato) | ✓ | ✓ | ✓ | ✓ | ✓ |
| Detalhe de um registro | ✓ | ✓ | ✓ | ✓ | ✓ |
| Edição de página | ✓ | ✓ | ✓ | ✓ | ✓ |

**Nenhuma das 20 telas tem rolagem horizontal** (`scrollWidth - clientWidth === 0` medido em
cada uma). O que as capturas mostram, faixa a faixa:

- **360 e 390:** barra superior com Menu, nome truncado e Sair; listagens como cards com rótulo
  por valor; detalhe em coluna única; editor com barra rolável e Salvar colado no rodapé.
- **768:** gaveta ainda ativa, listagem já de volta como tabela — a tabela larga rola **dentro**
  do seu contêiner, sem arrastar a página.
- **1024 e 1440:** idênticas ao estado anterior à responsividade, com a coluna fixa de volta e a
  densidade de controle de sempre. A comparação com `.playwright-mcp/antes/antes-inicio-1440.png`
  mostra o que mudou em tela larga nesta sessão: a fonte (item 5) e os ícones (item 6), nada de
  layout.

## O que foi verificado

- `npm run lint` e `npm run build` (vue-tsc) do painel, verdes ao fim de cada faixa.
- `npx tsc --noEmit` da bateria de ponta a ponta.
- Bateria completa de ponta a ponta contra a pilha real (API Laravel, painel e site em modo de
  produção, Firefox): **227 testes, todos verdes, em 3,5 minutos** — 116 ao fim da sessão 25,
  227 agora. A primeira execução completa pegou uma falha de verdade no teste de
  ícones desta mesma sessão: ele media **todos** os `svg.icon` do DOM, e o botão Fechar da
  gaveta passou a existir na marcação em qualquer largura, escondido por CSS acima de 64rem —
  o ícone dele mede 0×0 ali, o que não é ícone fora de tamanho, é ícone que não está na tela.
  O teste passou a medir só o que está desenhado.
- Conferência manual no navegador, contra a pilha de desenvolvimento, do que nenhuma asserção
  cobre bem: a gaveta abrindo e fechando, o cortinado, a barra do editor rolando com o dedo, e as
  20 capturas acima.

**Nenhum arquivo do backend mudou nesta sessão** (`git diff --stat` do intervalo inteiro
confirma: 49 arquivos em `frontend-admin`, 9 em `e2e`, 2 em `docs` e o `.gitignore`). O
checklist de fechamento foi cumprido mesmo assim, e as três verificações passaram:
Pint `passed`, PHPStan `0 errors`, Pest **486 testes**. Build do site público verde.

## Cobertura nova de ponta a ponta

| Arquivo | Casos | O que trava |
|---|---|---|
| `e2e/tests/painel/icones.spec.ts` | 19 | ícone presente, mesmo desenho por recurso, `aria-hidden`, 18px |
| `e2e/tests/painel/responsividade.spec.ts` | 34 | gaveta (foco preso, Esc, clique fora, fecha ao navegar, trava de rolagem), cards nas nove listagens, rótulo desenhado, filtros empilhados, barra do editor, Salvar no rodapé, 44px em seis telas, nada transbordando em 360px, densidade preservada em 1280px |

## Observações e pendências

1. **Entre 1024px e ~1200px a tabela das listagens mais largas ainda rola dentro do seu
   contêiner.** É o comportamento que já existia (`overflow-x: auto` no `.table-wrapper`) e não é
   regressão: a coluna fixa de 15rem volta em 1024px e a tabela de cinco colunas em `nowrap` não
   cabe no que sobra. A página não rola de lado; a última coluna é que fica cortada até alguém
   arrastar a tabela. Sair disso exigiria decidir quais colunas somem ou quebram nessa faixa —
   decisão de produto, não de CSS, e por isso fica registrada em vez de resolvida.
2. **A gaveta abre com o foco na marca**, que é o primeiro elemento focável dela. Se a preferência
   for abrir com o foco no botão Fechar, é uma linha em `useNavDrawer.ts`.
3. O painel agora tem quatro limiares escritos à mão (48rem e 64rem, em CSS e em JS). Se
   aparecer um quinto, vale um arquivo de breakpoints — hoje seria cerimônia para dois valores.
4. **A bateria de e2e precisa da máquina só para ela.** Uma execução completa falhou em bloco
   a partir do teste 140, com timeout de 45s e a tela de **login** nos artefatos, porque eu
   estava rodando `php artisan test`, `phpstan` e o build do site ao mesmo tempo: o
   `php artisan serve` sobrecarregado não respondeu ao `GET /api/v1/auth/user` a tempo e a SPA
   caiu para `/login`. Os 137 testes anteriores, sem concorrência, passaram, e a execução
   seguinte, sozinha, ficou verde. O `CLAUDE.md` diz que a bateria "pode rodar junto de
   qualquer um dos dois" — o critério ali é o **banco**, e isso continua verdadeiro; o que não
   cabe junto é a CPU. Vale um aviso nessa seção do `CLAUDE.md`, que não alterei por ser
   arquivo de instrução do projeto.
5. **Dois comentários defasados foram corrigidos de passagem** (só comentário, nenhuma regra):
   os `tokens.css` do painel e do site diziam "ADR de substituição pendente de registro" sobre a
   paleta, mas essa pendência foi fechada pelo ADR 0013 em sessão anterior. Agora os dois
   apontam para ele. O do site está fora do escopo desta sessão e foi mexido mesmo assim porque
   é a mesma frase, no mesmo lugar, e corrigir só metade seria pior.
