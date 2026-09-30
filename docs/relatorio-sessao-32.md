# Sessão 32 — Usabilidade das fotos no painel

Pedido: um caminho direto entre "tenho uma foto no computador" e "a foto aparece neste ponto
do texto". Decisões em `docs/decisoes/0027-enviar-fotos-sem-sair-da-pagina.md`. Um commit por
item.

| Item | Commit |
|---|---|
| 1 — enviar do computador pelo seletor do editor e da capa | `994e3e9` |
| 2 — soltar a foto no corpo do texto | `a890a5d` |
| 3 — "Imagens desta página" começa pelas ações, com envio em diálogo | `14e853e` |
| 4 — vocabulário de quem usa | `c0b460e` |
| 5 — o que salva na hora e o que exige Salvar | `91e3e9b` |

## Antes de mexer: o percurso como primeira vez

Fiz no navegador, com o banco de desenvolvimento, a tarefa "pôr uma foto do computador entre os
dois parágrafos de Estrutura", entrando pelo menu e sem atalho. Travei nestes pontos:

1. **O botão "Imagem" da barra não sugere envio.** Nada no editor diz que dá para trazer uma
   foto do computador.
2. **O seletor só mostra o que já existe.** A saída é o link "Envie a imagem numa aba nova".
   O ícone do link quebrava sozinho numa linha, separado do texto.
3. **A aba nova termina na ficha da imagem**, sem dizer "volte à outra aba".
4. **De volta ao editor, a grade continua velha.** A foto nova só aparece depois de clicar em
   Buscar, e nada pede isso.
5. **Depois de inserir, a tela não diz que falta salvar.** O Salvar está uma tela e meia
   abaixo. A frase mais visível sobre salvamento é a da seção de imagens ("Cada mudança aqui
   vai para o site na hora, sem precisar salvar a página"), que quem acabou de pôr uma foto lê
   como "então a foto já está no ar". Não está: a foto do texto depende do Salvar do texto.
6. **A foto inserida não aparece em "Imagens desta página"** até salvar, e nada explica por quê.
7. **A seção de imagens termina num formulário longo de envio**, depois de todas as listas.
   "Capa", "Para onde vai" e "Texto alternativo" são termos de quem já conhece CMS.

Apaguei do banco de desenvolvimento as duas fotos de teste que enviei no percurso. A página não
foi salva.

## O que mudou

1. **Seletor com "Enviar do computador"** (aba padrão) e "Escolher entre as já enviadas". No
   texto, a foto enviada vai para a biblioteca e entra no ponto do cursor. Na capa, vai direto
   para a capa. O foco vai para a descrição assim que a foto é escolhida.
2. **Soltar no texto** abre o mesmo envio já com a foto, e ela entra onde foi solta.
3. **"Adicionar foto"** no topo da seção abre o envio num diálogo, com a escolha entre galeria
   e foto que representa a página. "Escolher a foto que representa a página" fica ao lado.
4. **Vocabulário:** "Fotos desta página", "Galeria de fotos", "Foto que representa esta página
   em outros lugares do site" (com "Onde ela aparece: …"), "Fotos dentro do texto — para
   mover, use o editor acima", "Descrição da foto", "Trocar por outro arquivo", "Deixar de usar
   esta foto", "Abrir em Imagens", botão "Foto" na barra do editor.
5. **Salvamento visível:** aviso no topo do formulário, que muda quando há alteração não salva.
   Estado ao lado do Salvar ("Alterações não salvas" / "Tudo salvo"). "Aqui tudo vai para o
   site na hora" na seção de fotos. No seletor do texto, o aviso de que a foto só aparece na
   página depois do Salvar.

## Decisões tomadas sem consulta

1. **Enviar e descrever num passo só**, e não "envia, depois descreve". A API exige a descrição
   junto do arquivo, e um segundo passo repetiria os mesmos campos (ADR 0027, item 1).
2. **O seletor abre em "Enviar do computador"**, não na biblioteca.
3. **"Foto" em vez de "imagem"** em tudo o que a pessoa lê nessas telas. O pedido fala em foto,
   e o acervo é de fotos.
4. **Foto solta no meio de uma frase divide o parágrafo ali.** É onde o cursor de arrasto
   aparece. A alternativa, jogar a foto para antes ou depois do parágrafo, contradiria o que a
   tela mostra durante o arrasto.
5. **O Salvar continua no fim do formulário na tela larga** (ADR 0022). O aviso no topo cobre
   quem escreve lá em cima.
6. **A declaração e a tela de envio da biblioteca não mudaram** (ADR 0027, item 4).
7. **"Tirar da capa" virou "Deixar de usar esta foto"**, e a mensagem diz que a página ficou sem
   foto que a represente e que a foto continua guardada.

## Testes

- **Novos e2e:** envio pelo seletor do texto até o site. Envio pelo seletor da capa. Foto solta
  entre dois parágrafos: arquivo que não é foto é explicado, a API recusa o envio sem
  declaração e nada entra no texto, e o HTML gravado tem a figura exatamente entre os
  parágrafos. Estado de salvamento: foto adicionada na seção não deixa nada por salvar, e o
  texto alterado deixa. O teste de 360px da seção agora abre o diálogo e confere que ele cabe.
- **Prova de que o teste da soltura pega:** mudei o código para inserir no cursor de digitação
  em vez do ponto da soltura, e o teste passou. Estava errado: `getByText` resolvia o `<p>`
  inteiro, e o clique no meio dele punha o cursor no fim do primeiro parágrafo, o mesmo ponto
  da soltura. O teste agora leva o cursor ao começo (`Home`). Com a mutação, falha. Com o
  código real, passa.
- Os e2e existentes de biblioteca, capa, galeria e ordem foram atualizados para as abas e os
  rótulos novos. O preenchimento do envio pela seção virou um helper
  (`e2e/support/pageImages.ts`) no lugar de três cópias.

## O que foi verificado

- **Ponta a ponta:** **265 testes verdes** (3,5 min), a bateria inteira sozinha na máquina.
  Eram 261: os 4 novos são os dois envios pelo seletor, a soltura e o estado de salvamento.
- **Pest:** 604 verdes, Pint e Larastan limpos (backend sem mudança).
- **Builds:** painel (`build` e `lint`), site (`build` e `generate`) verdes.
- **Dependências:** nenhuma mudança em `package.json` nem `composer.json`. Os ícones novos
  (`ImageUp`, `RefreshCw`, `Zap`, `CircleAlert`) já vêm no `lucide-vue-next`.
- **No navegador:** percorri cada item no painel de desenvolvimento (capturas no percurso). O
  "Choose File / No file chosen" do campo de troca de arquivo é o idioma do navegador de teste.
  Num navegador em português, aparece traduzido.

## Pendente

- **Colar foto (Ctrl+V) no texto** ainda não envia. Seria o mesmo caminho da soltura.
- **Soltar de verdade, com o mouse, de uma pasta do sistema** não é automatizável no
  Playwright. O e2e despacha `dragover`/`drop` com o arquivo, que é o que o ProseMirror
  recebe. Vale uma conferência manual em Firefox e Chrome.
- **Leitor de tela de verdade** nas abas do seletor e no `role="status"` do Salvar. Continua o
  pendente da sessão 30.
