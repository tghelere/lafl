# 0026 — Ampliação de imagens no site: link para a foto grande, `<dialog>` nativo e sem biblioteca

## Contexto

Com as fotos na biblioteca (ADR 0024 e 0025), o site mostra galerias e figuras no texto em
tamanho de coluna. O pedido da sessão 30: toda imagem de conteúdo abre ampliada sobre a página,
com legenda e crédito, navegação na mesma galeria, acessível por teclado e leitor de tela, em
tela cheia no celular, e sem dependência nova injustificada. Sem JavaScript, a imagem precisa
continuar abrindo.

## Decisão

### 1. A base é um link

Toda imagem de conteúdo é um `<a href>` para a **maior derivada**, marcado com `data-ampliar`:

- no texto, a API monta o link na leitura pública (`ExpandContentImages`), com o crédito em
  `data-credito`;
- na galeria, o site monta o link (`AppImagem` com `grupo`), com o `full` que a API entrega.

Sem JavaScript, ou antes de a página hidratar, o clique abre a foto grande, que é o que o
pedido chama de ampliação progressiva. O nome do link é "Ampliar imagem: {texto alternativo}".

Ficam de fora, sem link: logotipo, ícones, o mapa de `/contato` e as capas mostradas fora da
própria página (destaque da home, cartões de "O que fazemos"), que funcionam como banner. A
capa não aparece na própria página em lugar nenhum hoje (ADR 0025). Se um dia aparecer, basta
passar `grupo` ao `AppImagem`.

### 2. A ampliação intercepta o link

`AppAmpliacao.vue`, uma instância no layout, escuta cliques no documento. Link com
`data-ampliar` abre a ampliação, exceto com Ctrl, Cmd, Shift ou o botão do meio, que seguem o
link para abrir em outra aba. Tudo o que ela mostra é lido do próprio link: `srcset`, dimensões,
texto alternativo, a `figcaption` da figura e o `data-credito`. Nada novo trafega.

**Grupos:** imagens com o mesmo `data-grupo` (galeria, inclusive a placa e os pares da
história) ou dentro do mesmo `[data-grupo-ampliacao]` (o texto da página) navegam entre si.

**Derivada:** a imagem aparece do tamanho que o palco permite, e a derivada escolhida é a
maior cuja largura não passa desse tamanho em pixels do aparelho. Se nenhuma couber, vale a
menor. A escolha é refeita ao redimensionar.

### 3. `<dialog>` nativo, sem biblioteca

O `showModal()` dá o fundo inerte, o Esc e a camada acima de tudo. O resto é pouco código:

- **foco preso explícito:** Tab e Shift+Tab circulam dentro, porque no Firefox o Tab podia
  escapar para a barra de endereço;
- **foco devolvido ao link de origem** ao fechar, mesmo depois de navegar;
- **anúncio `aria-live`** a cada troca;
- **deslize** por eventos de ponteiro: horizontal, com pelo menos 50px, e sem contar como
  clique fora.

Bibliotecas de lightbox (PhotoSwipe, GLightbox e outras) resolveriam isso com mais código do que
o componente inteiro, e trariam o próprio markup, CSS e superfície de ataque. O CLAUDE.md pede
justificar cada dependência, e aqui não havia o que justificar. Os ícones são do `@lucide/vue`,
que o site já usava.

### 4. Celular

Abaixo de 48rem, a ampliação ocupa a tela toda, com espaço interno de
`max(espaço, env(safe-area-inset-*))` e controles de 44px. O site não usa
`viewport-fit=cover`, então hoje os insets valem 0, e o navegador já mantém tudo na área segura.
Ligar o `cover` no site inteiro exigiria revisar todas as páginas, e não era o pedido.

### 5. Crédito

"O crédito quando houver" pedia um lugar para o crédito, que a biblioteca não tinha.
`media.credit` é opcional e se edita no painel junto do texto alternativo e da legenda. Viaja no
pacote e aparece na ampliação, embaixo da legenda.

## Consequências

- O HTML público do texto mudou (a imagem ganhou o link), e a chave do cache de página subiu
  para `public-page:v3:`.
- Os seletores com escopo que miravam `img` no `AppImagem` precisaram de `:deep(img)`, porque a
  raiz do componente ampliável é o `<a>`.
- A ampliação baixa só a derivada escolhida. Não há pré-carga da vizinha: a troca de imagem pode
  levar um instante numa conexão lenta.
