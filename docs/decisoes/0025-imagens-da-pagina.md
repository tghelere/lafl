# 0025 — Imagens da página: capa e galeria como ligação com a biblioteca

## Contexto

A biblioteca de mídia (ADR 0024) resolveu a imagem **dentro do texto**. As fotos das páginas de
seção (a galeria do bazar, a da educação infantil, a placa e os pares de antes e depois da
história, o destaque da página inicial, os cartões de "O que fazemos") continuaram como
arquivos fixos em `frontend-site/public/fotos/`, escolhidos no código de cada `.vue`. Nenhuma
dessas fotos podia ser trocada, corrigida ou retirada pelo painel.

O pedido da sessão 28 tinha três partes: levar essas fotos para a biblioteca preservando o que o
site mostrava, fazer as páginas referenciarem a biblioteca, e dar a cada página uma porta
própria ("Imagens desta página") sem duplicar arquivo nem metadado.

## Decisão

### 1. Uma tabela de ligação, `page_images`, com dois papéis

`page_id`, `media_id`, `role` (`App\Enums\PageImageRole`) e `position`. Só a ligação:
arquivo, texto alternativo e legenda continuam em `media`. A mesma foto em duas páginas é uma
imagem só, e corrigir o texto alternativo na biblioteca vale para as duas.

- **Galeria** (`gallery`): as fotos, em ordem, que a própria página mostra depois do texto.
- **Capa** (`cover`): uma por página, e **a página não a mostra**. É o que o site usa para
  representar a página em outro lugar. Hoje: os cartões de "O que fazemos" (capa de cada frente)
  e o destaque da página inicial (capa de "Quem somos"). Amanhã, naturalmente, o `og:image`.

A capa existe porque o que o site mostrava não cabia só em galerias. O cartão do bazar usava a
entrada, que é a **segunda** foto da galeria do bazar. E a página inicial, que não é página do
CMS, mostrava a fachada, que não estava em galeria nenhuma. Sem a capa, preservar o site exigiria
reordenar a galeria do bazar ou criar uma página fantasma para a home.

A home usar a capa de "Quem somos" é um acoplamento declarado: "Quem somos" é a página da
instituição, e a fachada da sede é a imagem da instituição. O painel diz isso ao lado da capa.

### 2. A página pública recebe as imagens prontas

`/api/v1/public/pages/{slug}` ganhou `images: {cover, gallery}`, cada imagem com `src`,
`srcset` (as derivadas que existem **agora**), `width`, `height`, `alt` e `caption`. Montado
dentro do cache de 10 minutos, como a expansão do texto, e esquecido pelo mesmo
`ForgetPagesUsingMedia`, que agora acha a imagem também na capa e na galeria. O ETag da resposta
passou a incluir as imagens: trocar o arquivo muda o `srcset` sem mudar o texto nem o
`updated_at`.

Corrigir o texto alternativo ou a legenda na biblioteca também esquece o cache das páginas que
usam a imagem. Antes desta decisão, só a marcação de assistido fazia isso, e bastava, porque o
texto do conteúdo é cópia. Com a capa e a galeria lendo o texto da biblioteca, o site ficaria
até dez minutos com o texto velho. O e2e de "Imagens desta página" pegou isso.

O `sizes` fica no site (`AppImagem.vue`, por contexto), porque é o layout que sabe quanto da tela
a imagem ocupa. A chave do cache subiu para `public-page:v2:` para que a entrada da versão
anterior, sem `images`, não fosse servida depois do deploy.

Imagem marcada como foto de assistido sai da capa e da galeria na leitura, e a galeria fecha o
buraco. Pôr na página uma imagem marcada é recusado.

### 3. As regras da biblioteca passam a enxergar capa e galeria

"Onde é usada" diz a página e o lugar (texto, capa, galeria). A exclusão é bloqueada com a
imagem em capa ou galeria de página fora da lixeira, como no texto. A ligação com página na
lixeira sai junto com a imagem (cascata), pelo mesmo motivo do ADR 0024: bloquear por ela seria
um beco sem saída.

### 4. Fotos iniciais por comando idempotente, como as páginas iniciais

As 24 fotos de `public/fotos/` foram para `backend/resources/initial-photos/`, na maior largura
que o site já servia, e `App\Support\Media\InitialPhotos` é o catálogo: texto alternativo e
legenda copiados do que o site exibia, e o lugar de cada uma. `midia:importar-fotos-iniciais`
processa cada foto como um envio pelo painel (recodificação, derivadas) e a põe na página.

- **Idempotente pela coluna `media.origin_key`**: a foto que já veio do catálogo é pulada
  inteira. Rodar de novo não duplica, e não pisa no que a equipe mudou (arquivo trocado, texto
  corrigido, foto tirada da galeria).
- Sem a página de destino, a foto não entra, e o comando falha dizendo que falta
  `conteudo:importar-inicial`. Uma capa já ocupada é mantida.
- `migrate:fresh --seed` roda o comando em desenvolvimento. A bateria de e2e, não: ela começa
  com a biblioteca vazia.
- Produção não roda o comando. As fotos chegam pelo pacote de conteúdo, que foi para o
  **formato 3**, com a capa e a galeria de cada página e o `origin_key` de cada imagem. O
  importador recusa um pacote 2, que levaria as páginas sem as fotos, e recusa a foto inicial
  que já exista no destino com outro uuid.

As 24 foram conferidas uma a uma e declaradas como **não** mostrando criança ou adolescente
atendido: são prédios, salas vazias, o bazar e a equipe (adultos).

Sete fotos de `public/fotos/` não estavam em página nenhuma. Entram só na biblioteca, à
disposição da equipe.

Em `public/fotos/` ficou só o mapa de `/contato`, que não é foto (docs/fotos.md).

### 5. Duas portas, um depósito

O painel ganha "Imagens desta página" na edição de cada página: capa, galeria e as imagens do
texto, com enviar (que põe a foto no fim da galeria), substituir o arquivo, editar texto
alternativo e legenda, e tirar da galeria. Tudo pelas mesmas rotas da biblioteca. Não há arquivo
nem metadado por página. `/admin/imagens` continua como o acervo completo.

A imagem **do texto** aparece na lista, mas o texto alternativo e a legenda dela são editados no
editor: pelo ADR 0024, o que foi inserido no texto fica gravado na página, e a biblioteca só
oferece o padrão. Mudar isso seria outra decisão.

## Alternativas descartadas

- **Galeria como HTML no conteúdo**, com as figuras no fim do texto. A equipe teria de montar a
  grade à mão no editor, e o layout de cada seção (a placa em destaque, os pares) deixaria de ser
  do site.
- **Posições nomeadas por layout** ("destaque-da-home", "par-1-antes"). Cada layout novo
  exigiria enum novo, e "Enviar" numa página não teria para onde ir.
- **Metadado por ligação** (texto alternativo e legenda próprios de cada página). É exatamente a
  duplicação que o pedido proibiu, e faria a correção de um texto alternativo ter de ser repetida
  em cada página.
- **Manter `public/fotos/` e só registrar as fotos na biblioteca.** O site continuaria servindo o
  arquivo fixo, e trocar pelo painel não mudaria nada.

## Consequências

- Os endereços antigos `/fotos/{secao}/{slug}-{largura}.{webp,jpg}` deixam de existir (404). Uma
  imagem que um buscador tenha indexado por esse endereço sai do índice até ser encontrada de
  novo em `/midia/`.
- **A capa é escolhida pelo painel** (sessão 29): trocar por imagem da biblioteca (o seletor do
  editor, em modo de escolha, sem o passo de descrever), enviar uma foto nova direto para a capa,
  ou tirar a capa. A tela diz onde o site mostra a capa daquela página. Esse mapa fica na API
  (`App\Support\Content\CoverPlacements`), e não no painel, pela regra 1 do CLAUDE.md, e
  precisa acompanhar `index.vue` e `o-que-fazemos.vue` quando o site mudar onde usa capa.
- **A galeria se reordena pelo painel** (sessão 29): "Mover para cima" e "Mover para baixo" em
  cada foto, trocando com a vizinha. A troca passa por uma posição temporária por causa da
  restrição única, com a linha da página travada. Pelo teclado, o foco volta ao mesmo botão da
  mesma foto depois do movimento (ou ao outro sentido, se ela chegou à ponta), e a posição nova
  é anunciada numa região `aria-live`. A ordem é a `position` que o pacote de conteúdo já leva.
