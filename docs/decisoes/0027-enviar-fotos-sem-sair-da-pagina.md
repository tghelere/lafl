# 0027 — Enviar fotos sem sair da página, e a tela dizendo o que salva na hora

## Contexto

Com a biblioteca (ADR 0024) e "Imagens desta página" (ADR 0025), pôr uma foto nova no meio do
texto exigia: abrir a biblioteca numa aba nova pelo link do seletor, enviar, voltar à aba do
editor, buscar de novo e só então escolher. O percurso da sessão 32, feito como quem usa pela
primeira vez, está no relatório dela. O pedido: enviar de dentro do seletor (texto e capa),
soltar a foto direto no texto, a seção de fotos começando pelas ações, vocabulário de quem
nunca usou um CMS, e a diferença visível entre o que salva na hora (fotos) e o que exige
Salvar (texto).

Nenhuma mudança de API. `POST /media` e `POST /pages/{id}/images` já recebiam arquivo,
descrição e declaração num pedido só.

## Decisão

### 1. Um formulário de envio, três portas

`ImageUploadForm.vue` é a área de arrastar e soltar (o `<label>` do campo de arquivo), a
pré-visualização, a descrição, a legenda, o crédito e a declaração. Quem chama passa a função
de envio. Usado pelo seletor do texto (vai para a biblioteca), pelo da capa (vai para a capa)
e pelo "Adicionar foto" da seção (galeria ou capa).

**Enviar e descrever são o mesmo passo.** A API exige a descrição (texto alternativo) junto do
arquivo. Um passo de "descrever" depois do envio repetiria os mesmos campos. No seletor do
texto, a descrição digitada vai para a biblioteca **e** para a figura no texto, e a foto entra
no texto quando o envio termina.

### 2. O seletor abre em "Enviar do computador"

É o caso de quem chegou com uma foto nova, e o problema que motivou a mudança. "Escolher entre
as já enviadas" é a segunda aba, e carrega a biblioteca só quando alguém a abre. As abas são
`tablist` de verdade, com setas, porque são painéis no mesmo diálogo, e não rotas como na
Auditoria.

### 3. Soltar no texto: o ponto da soltura, não o cursor

`handleDrop` do editor abre o mesmo diálogo, já com a foto escolhida, e guarda a posição de
`posAtCoords` no momento da soltura. A foto entra ali: é onde o cursor de arrasto do
ProseMirror aparece. O diálogo é modal, então o documento não muda enquanto ele está aberto.

- Arrasto interno (`moved`, uma figura mudando de lugar) continua com o ProseMirror.
- Arquivo que não é foto também abre o diálogo, com a explicação, em vez de sumir em silêncio.
- De vários arquivos soltos juntos, só o primeiro é usado.
- Arquivo solto dentro do diálogo, mas fora da área, é ignorado. Sem isso, o navegador abriria
  o arquivo no lugar do painel, e o texto não salvo iria junto.

A declaração obrigatória e a recusa da foto de assistido são as da API, porque o envio é o
mesmo. O e2e confere que, sem resposta, a API recusa e nada entra no texto.

### 4. Vocabulário

"Foto" nas telas de edição de página. "Capa" passou a ser "Foto que representa esta página em
outros lugares do site", com a linha de onde ela aparece (vinda da API). "No meio do texto"
passou a ser "Fotos dentro do texto — para mover, use o editor acima". "Texto alternativo"
passou a ser "Descrição da foto", com a explicação de para quem ela serve. "Biblioteca" passou
a ser "Imagens", o nome do item do menu.

Ficaram como estavam: a declaração (`MediaDeclarationField`), cujo texto foi decidido na sessão
28 com cuidado de LGPD, e as telas da própria biblioteca, fora do pedido.

### 5. O que salva na hora e o que exige Salvar

A mesma forma de aviso (`.save-mode`) nas duas partes da tela, com conteúdo oposto. No
formulário: "só vai para o site quando você clica em Salvar", e o aviso muda quando há
alteração não salva. Ao lado do Salvar aparece o estado ("Alterações não salvas" / "Tudo
salvo"), em `role="status"`. Na seção de fotos: "Aqui tudo vai para o site na hora". O seletor
do texto avisa que a foto só aparece na página depois do Salvar.

O Salvar **não** passou a ser fixo no rodapé da tela larga. O ADR 0022 o deixa no fim do
formulário a partir de 48rem, e o aviso no topo do formulário cobre quem está escrevendo lá em
cima.

## Consequências

- O link "Envie a imagem numa aba nova" saiu do seletor.
- O formulário longo de envio saiu do fim de "Imagens desta página".
- `MediaUploadView` (a tela de envio da biblioteca) não usa o formulário novo. Unificar mudaria
  os rótulos daquela tela, e ela estava fora do pedido.
- Colar uma foto do computador (Ctrl+V) no texto ainda não envia. Seria o mesmo caminho do
  `handleDrop`, pelo `handlePaste`.
