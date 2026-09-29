# Sessão 30 — Ampliação de imagens no site

Uma funcionalidade, cinco exigências. Um commit por item, mais um preparatório (o crédito), e a
documentação com este relatório. ADR 0026.

| Item | Commit |
|---|---|
| 1 — imagem de conteúdo vira link para a foto grande (base progressiva) | `201f3f6` |
| 2 (preparação) — crédito da imagem, que a biblioteca não tinha | `69bf84c` |
| 2 — ampliação sobre a página: derivada, legenda, crédito, fechar, navegar, deslizar | `2730cf5` |
| 3 — acessibilidade: foco preso, foco devolvido, anúncio, rótulos | `8211c31` |
| 4 — celular: tela cheia, áreas seguras, 44px | `1b7dda2` |
| 5 — nenhuma dependência nova | registrado em cada commit |
| Auxiliar de e2e que a bateria pegou | `1b3fc8e` |

---

## Como funciona

- **A base é um link.** Toda imagem de conteúdo é um `<a href>` para a maior derivada. No texto,
  a API monta o link na leitura pública; na galeria, o site. Sem JavaScript, ou antes de a
  página hidratar, o clique abre a foto grande. Vi isso acontecer no site de desenvolvimento:
  um clique antes da hidratação abriu o `.webp`, que é o comportamento esperado.
- **A ampliação intercepta o link** (`AppAmpliacao.vue`, uma instância no layout), lendo tudo
  do próprio link: `srcset`, dimensões, texto alternativo, legenda da figura e crédito. Ctrl,
  Cmd, Shift ou o botão do meio seguem o link, para abrir em outra aba.
- **Derivada:** a maior cuja largura não passa do tamanho em que a imagem aparece, em pixels do
  aparelho. Se nenhuma couber, a menor. A escolha é refeita ao redimensionar. O e2e confere a
  escolha contra o tamanho real do palco, não contra um número fixo.
- **Grupos:** a galeria (inclusive a placa e os pares da história) é um grupo, e o texto da
  página é outro. A posição ("2 de 4") aparece quando o grupo tem mais de uma imagem.
- **Fecha** no botão "Fechar", no Esc e no clique fora. **Navega** pelas setas na tela, pelas
  setas do teclado e pelo deslize, e as pontas não passam.
- **Fica de fora, sem link:** logotipo, ícones, o mapa de `/contato`, o destaque da home e os
  cartões de "O que fazemos". O teste da home põe uma capa real em Quem Somos para conferir
  isso, e a tira no fim.

## Acessibilidade

- **Foco preso explícito:** Tab e Shift+Tab circulam dentro do diálogo. No Firefox, o Tab no
  último controle podia escapar para a barra de endereço, e o `<dialog>` sozinho não impede.
- **Foco devolvido ao link de origem**, a imagem clicada, por qualquer caminho de fechar e mesmo
  depois de navegar.
- **Na ponta,** o botão com foco desliga e o foco passa ao outro sentido, em vez de cair no
  `<body>`.
- **Anúncio `aria-live`** a cada troca ("Imagem 2 de 3: …"). A legenda e o crédito descrevem o
  diálogo (`aria-describedby`), e os rótulos são em pt-BR.
- **Achado pelo e2e:** o `showModal()` rodava antes de o Vue desenhar o conteúdo, e o foco
  inicial caía no próprio diálogo, sem nenhum controle dentro. Agora o conteúdo é desenhado
  antes, e o foco começa em "Fechar".
- O site não tinha a classe `.visually-hidden`; foi criada.

## Celular

A tela toda, sem borda, com espaço interno `max(espaço, env(safe-area-inset-*))` e controles de
44px nos dois sentidos. **Visto no navegador:** a 360px, "Imagem anterior" quebrava em duas
linhas. No celular, os rótulos visíveis viraram "Anterior" e "Próxima", e o nome acessível
segue o completo pelo `aria-label`.

## Decisões tomadas sem consulta — para revisar

1. **Crédito como campo novo** (`media.credit`, opcional). Sem ele, o "crédito quando houver"
   nunca apareceria. Ele se edita no painel junto do texto alternativo e da legenda, e viaja no
   pacote de conteúdo. No tipo do pedido do painel, ele é obrigatório, para nenhum formulário
   apagá-lo ao salvar outro campo.
2. **"Maior derivada que couber na tela"** foi lido ao pé da letra: a maior que não passa do
   tamanho exibido. Numa tela de alta densidade, isso já leva a derivada maior, porque a conta
   é em pixels do aparelho.
3. **Foco devolvido à imagem de ORIGEM**, como pedido, e não à última vista. Depois de navegar
   até a terceira foto, o foco volta à primeira.
4. **Sem `viewport-fit=cover`.** Os insets de área segura valem 0 hoje, e o navegador já mantém
   tudo na área segura. Ligar o `cover` exigiria revisar o site inteiro.
5. **As pontas não dão a volta.** Na última foto, "Próxima" desliga, em vez de voltar à
   primeira, para a posição ("3 de 3") dizer a verdade.
6. **O destaque da home não amplia**, por ser capa com papel de banner. A capa não aparece na
   própria página em lugar nenhum hoje.

## O que foi verificado

- **Pest:** 604 testes verdes, com Pint e Larastan limpos.
- **Ponta a ponta:** **257 testes verdes** (3,8 min), a bateria inteira sozinha na máquina. Eram
  244 na sessão 29, e os 13 novos estão em `tests/midia/ampliacao.spec.ts`: sem JavaScript,
  exclusões, teclado, 1280px, acessibilidade e 360px.
  **A primeira passada teve 2 falhas**, nos testes de sanitização: um auxiliar de teste
  (`serverPageContent`) procurava `<div class="page-content">` exato, e a div ganhou
  `data-grupo-ampliacao` no item 1. O site estava certo. Corrigi o auxiliar em commit próprio, e
  a segunda passada deu 257 de 257.
- **Builds:** site (`build` e `generate`) e painel (`build` com `vue-tsc`, e `lint`) verdes.
- **Dependências:** nenhum `package.json` nem `composer.json` mudou nesta sessão.
- **No navegador, com fotos reais do ambiente de desenvolvimento:** a galeria do bazar
  ampliada em 1280px ("2 de 4", imagem na altura disponível, página escurecida por trás) e a
  galeria da história em 360px, com legenda e rótulos curtos.

## Falta ver com os olhos

- Uma figura do meio do texto ampliada, com crédito, numa página real (só vi pelo e2e).
- Leitor de tela de verdade (NVDA ou VoiceOver). O e2e confere os atributos e a região
  `aria-live`, não a fala.

## Pendente

- **Pré-carga da imagem vizinha** na ampliação. Hoje a troca pode levar um instante em conexão
  lenta.
- **Um `og:image` a partir da capa** continua aberto (sessão 29).
