# 0022 — Responsividade do painel: dois limiares, gaveta com foco preso e tabela que vira card

## Contexto

Até a sessão 26 o painel administrativo não tinha **nenhuma** media query. Ele foi desenhado
para uma janela larga: coluna de navegação fixa de 15rem, tabelas de até seis colunas em
`white-space: nowrap` e controles de 36 a 40px de altura.

Em 390px o resultado era previsível: a navegação comia metade da largura útil, a tabela rolava
de lado dentro do seu contêiner (com a última coluna sempre cortada), a barra de filtro quebrava
em campos de meia linha e nenhum alvo chegava perto dos 44px que um polegar acerta.

Isso importa porque o painel **é** usado no celular: a coordenação abre uma mensagem recebida
entre uma coisa e outra, e não da mesa dela. Não é a tela principal de trabalho — é a tela de
consulta rápida, que é justamente onde errar o toque custa mais.

## Decisão

### Dois limiares, e só dois

| Limiar | O que muda |
|---|---|
| **48rem (768px)** | tabela ↔ lista de cards; filtros empilhados ↔ em linha; barra do editor rolável ↔ quebrada em fileiras; Salvar grudado no rodapé ↔ no fim do formulário; respiro do conteúdo e da barra superior |
| **64rem (1024px)** | navegação lateral: gaveta ↔ coluna fixa; alvos de toque de 44px ↔ densidade de mouse |

São os mesmos dois valores que o site público já usa (`frontend-site/app/assets/css/
components.css`). Não existe um terceiro: cada limiar novo é mais um estado para conferir a cada
mudança de tela, e dois já cobrem o que a instituição usa (celular, tablet, notebook).

**A faixa de toque vai até 64rem, e não até 48rem.** Tablet é tela de dedo tanto quanto celular,
e é justamente ali que a listagem já voltou a ser tabela, com as ações de linha de volta.

### O limiar de 64rem está escrito duas vezes, de propósito

O CSS decide o **desenho** (coluna no fluxo ou gaveta fora dele) e o composable
`src/composables/useNavDrawer.ts` decide o **comportamento** (prender o foco, fechar com Esc,
cortinado, travar a rolagem de fundo). Não há como um ler o outro sem inventar um atributo só
para isso, e um `matchMedia` com a mesma string da media query é mais honesto que um
`ResizeObserver` medindo largura à mão. Os dois lados estão comentados apontando um para o
outro.

### A gaveta prende o foco, mas não se declara diálogo

Enquanto aberta, ela cobre o conteúdo. Sem prender o foco, o Tab seguiria pelos links da tela
**atrás** do cortinado — invisíveis, mas focáveis —, e quem navega por teclado perderia o rastro
do foco na primeira tecla.

Fechada, ela é `visibility: hidden` e não apenas um `transform` para fora da tela: deslocar sem
esconder deixa tudo focável do mesmo jeito.

Ela **não** declara `role="dialog"`: prende o foco como um diálogo, mas não tem título próprio
nem `aria-modal`, e anunciar-se como diálogo criaria uma expectativa que ela não cumpre. O par
correto para um menu que se revela é o que o botão já declara — `aria-expanded` e
`aria-controls`.

### A tabela vira lista de cards, e o rótulo vai junto do valor

Abaixo de 48rem, cada linha das nove telas de tabela do painel vira um card: o nome do registro
como título e cada valor abaixo, com o rótulo da sua coluna ao lado. O rótulo vem de
`data-label` no `<td>`, desenhado pelo `::before`, e o `<thead>` some — mantê-lo faria o leitor
de tela anunciar cada nome de coluna duas vezes.

O card mostra **todas** as colunas da tabela, não um recorte: esconder alguma seria decidir por
conta própria qual informação importa menos, e essa é decisão de quem usa o painel.

Este é o único bloco `max-width` do arquivo; o resto é mobile-first. A forma normal do
componente é a tabela, e escrevê-la ao contrário exigiria desfazer regra por regra — `display`,
`padding`, `border`, `white-space` — no caminho de volta. Um bloco que descreve a exceção e some
inteiro se ela deixar de existir diz melhor o que está acontecendo.

### 44px vêm dos tokens de altura de controle, não de regra por componente

Abaixo de 64rem, `--control-height-sm` e `--control-height-md` passam a valer `--touch-target`
(2.75rem). Botão, campo e select chegam aos 44px sem que nenhuma tela precise saber disso — e,
como as duas continuam **iguais entre si** dentro de cada faixa, o alinhamento da barra de
filtro (o requisito transversal da tarefa 10) não muda em largura nenhuma. Só o que tem altura
própria — item do menu, paginação, botão do editor, link do card, degrau da trilha — recebe
`min-height` em um bloco único no fim de `components.css`.

## Consequências

- **Toda coluna nova de listagem precisa de `data-label` no `<td>`.** Sem ele, o valor aparece
  no card sem rótulo nenhum. `e2e/tests/painel/responsividade.spec.ts` pega isso: ele lê o
  `content` **calculado** do `::before`, não o atributo no HTML — o atributo provaria só que
  alguém o escreveu, não que ele chegou à tela.
- **Todo controle novo usa as alturas de controle dos tokens**, nunca `height` em pixel. Quem
  escreve `height: 36px` num botão está criando um alvo de 36px no celular.
- **Tela nova do painel entra na lista de telas do teste de alvos de toque.** O teste mede o que
  está na tela que ele abriu; uma tela fora da lista não é medida por ninguém.
- A densidade de tela larga está travada nos dois sentidos: nada abaixo de 44px em 390px, e 36px
  na barra de filtro em 1280px. O painel continua sendo uma tela de trabalho onde há mouse.
- Entre 1024px e ~1200px a tabela mais larga ainda rola dentro do próprio contêiner — a coluna
  fixa volta em 1024px e as colunas em `nowrap` não cabem no que sobra. Não é regressão (o
  `overflow-x: auto` é anterior), e resolver exige decidir quais colunas somem ou quebram nessa
  faixa. Registrado no roadmap.

## Alternativas consideradas

**Um limiar só (768px), com a navegação encolhendo para uma coluna de ícones entre 768 e
1024px.** Descartada: uma coluna de ícones sem rótulo obriga a decorar nove símbolos, e a
sessão 26 acabara de adotar ícones justamente como **reforço** do rótulo escrito, nunca no lugar
dele (ver `src/components/AppIcon.vue`).

**Rolagem horizontal da tabela também no celular, sem virar card.** É o que já acontecia, e é o
motivo da tarefa: a última coluna — o status, que é o que se vai conferir — fica fora da tela
até alguém descobrir que aquela faixa arrasta.

**Um componente `<DataList>` que renderiza tabela ou cards conforme a largura.** Traria estado
de largura para dentro do Vue em nove telas, com um `matchMedia` por tela, para produzir a mesma
marcação que o CSS já reorganiza sozinho. O CSS é o lugar certo para uma decisão que é só de
apresentação.
