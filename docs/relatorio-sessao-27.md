# Sessão 27 — Pacote de conteúdo para o lançamento e biblioteca de mídia

Duas entregas, na ordem em que foram pedidas:

1. **Pacote de conteúdo** (`conteudo:exportar` / `conteudo:importar`), procedimento de
   lançamento em `docs/deploy.md` §10.1 e ADR 0023: a partir do lançamento, o banco é a fonte
   de verdade do conteúdo, e `InitialPages.php` é só semente.
2. **Biblioteca de mídia:** upload, derivadas, inserção no texto das páginas, substituição e
   exclusão pelo painel. ADR 0024.

| Etapa | Commit |
|---|---|
| Pacote de conteúdo (comandos + testes) | `d174106` |
| Procedimento de lançamento em `deploy.md` | `73a6a57` |
| ADR 0023 | `e91b4c2` |
| Mídia 1+2 — biblioteca e derivadas webp na API | `365a612` |
| Mídia 3 — imagem no texto: sanitizador, trava ao salvar, `srcset` público, proxy no site | `5d59ddc` |
| Mídia 1/4/5 — telas da biblioteca no painel | `1f43871` |
| Mídia 3 — inserir imagem pelo editor | `8f56e46` |
| Ponta a ponta do fluxo inteiro | `fcf75e2` |
| Teto de 30 MP, pela memória medida | `f1b8205` |
| Pacote de conteúdo leva as imagens (formato 2) | `645714d` |
| ADR 0024 e documentação | `07d6e7b` |

---

## Biblioteca de mídia, em uma página

- **O conteúdo guarda só `/midia/{uuid}`.** A leitura pública monta o `<img>` com `srcset`
  das derivadas atuais, `width`/`height` e `loading="lazy"`, dentro do cache de 10 minutos, que
  é esquecido quando a imagem muda. Substituir o arquivo não exige editar página, e o endereço
  não muda.
- **O site serve, a API decide.** `/midia/{uuid}[/{largura}.webp]` é proxy puro no Nitro, o
  mesmo desenho do PDF de transparência. A API responde com 404 para imagem impublicável, com
  cache revalidável por ETag, e o proxy repassa `If-None-Match`.
- **O EXIF sai por recodificação**, não por remoção de segmento. A orientação da câmera é
  gravada no pixel antes. Um teste com JPEG sintético contendo `Orientation` e GPS prova as duas
  coisas.
- **As derivadas são geradas no upload, síncronas**, na escala 400–1920 e nunca maiores que a
  original.
- **Pasta por versão** (`media/{uuid}/{versão}/`) e nome derivado: o nome do arquivo enviado
  não é guardado. Na substituição, a versão nova é gravada ao lado e a anterior é apagada depois
  do commit.
- **A exclusão é bloqueada em uso** e diz em quais páginas. Página na lixeira não bloqueia.
- **O sanitizador** aceita `figure`/`img`/`figcaption` com `src` só na forma canônica. Endereço
  externo, `data:`, `//host`, largura ou query string derrubam a imagem. Ao salvar, a página é
  recusada se usar imagem inexistente, impublicável ou sem texto alternativo.
- **Permissão:** a mesma de páginas. Upload, substituição, edição e exclusão ficam no
  `activity_log`, cada uma com evento próprio.
- **Painel:** `/admin/imagens` (grade, envio e detalhe com "Onde é usada"), e o botão "Imagem"
  do editor abre um `<dialog>` nativo. Os alvos de toque têm 44px abaixo de 64rem, com ícones
  lucide e `PageHeader`.

## Decisões tomadas sem consulta

1. **Foto de criança ou adolescente atendido é recusada no upload.** Esta é a principal, e a
   que mais merece revisão. O sistema não registra consentimento de imagem (Fase 2 bloqueada),
   então nenhuma dessas fotos poderia ir para o site, e guardá-las seria tratamento sem
   finalidade. A declaração é obrigatória e sem padrão. Marcar depois tira a imagem do site na
   hora e **não pode ser desfeito**, nem pelo painel nem por pacote. O CLAUDE.md manda, na
   dúvida, escolher o mais restritivo e registrar. **A dúvida que fica é de processo:** a
   declaração depende de quem sobe responder com verdade, e orientar a equipe é decisão da
   instituição. Registrada no ADR 0024.
2. **`assisted_minor_id` não foi criado**, embora `docs/dominio.md` o previsse. Não teria uso
   possível antes de `consents` (o mesmo critério que adiou `pages.og_image_id`). O domínio foi
   atualizado.
3. **Derivadas síncronas, não em fila**, contra o que `docs/arquitetura.md` previa. O motivo
   está no ADR, com a medição.
4. **Teto de 30 MP, não 36.** Medido com `memory_limit=256M`: 30 MP chegam a ~162 MB de pico, e
   36 MP a ~192 MB, folga curta para o resto da requisição. O primeiro commit usava 36, e
   `f1b8205` corrige.
5. **A original não é pública.** Só as derivadas webp saem pelo site.
6. **O texto alternativo e a legenda da biblioteca são padrão, não vínculo.** O que foi inserido
   numa página fica gravado nela e não muda sozinho quando a biblioteca muda.
7. **O pacote de conteúdo foi para o formato 2.** Sem isso, o procedimento de lançamento escrito
   nesta mesma sessão levaria as páginas sem as fotos. Nenhum pacote do formato 1 foi usado em
   ambiente real, então o importador não lê mais o 1.

## O que foi verificado

- **Pest:** 552 testes verdes, com Pint e Larastan limpos. Para mídia, 50 testes de API e
  conteúdo, mais os do pacote.
- **Ponta a ponta:** a bateria inteira passou (229 testes antes do ajuste de
  `acesso-por-papel`, cuja falha era a esperada: o menu de `comunicacao` ganhou "Imagens").
  `tests/midia/biblioteca.spec.ts` percorre o fluxo pedido em Firefox, contra a pilha real:
  1. subir;
  2. inserir pela barra do editor;
  3. conferir o que foi gravado;
  4. ver no site na largura certa;
  5. substituir e ver o **mesmo endereço** entregar outro arquivo (SHA-256 diferente) e o
     `srcset` ganhar a largura nova;
  6. tentar excluir em uso como `direcao` e ler a recusa com o nome da página.

  O spec também cobre a recusa da foto declarada e a tela e o seletor em 360px, com alvo de 44px.
- **A miniatura por rota autenticada funciona:** o `<img>` do painel leva o cookie de sessão
  para a API, provado pelo `naturalWidth > 0` no e2e, no painel e no editor.
- **Builds:** site (`build` e `generate`) e painel (`build` e `lint`) verdes.

## O que precisa de conferência humana no navegador

Não abri as telas com os olhos. As asserções de e2e cobrem funcionamento, largura em 360px e
tamanho de alvo, mas não o acabamento visual. Vale abrir:

- `/admin/imagens` em tela larga e em celular: grade, selo "Fora do site" sobre a miniatura;
- o detalhe de uma imagem: duas colunas a partir de 768px;
- o seletor do editor, a figura selecionada no editor (contorno laranja);
- uma página do site com figura e legenda (espaçamento e cor da legenda).

## Pendente

- A tela "Auditoria" mostra só formulários recebidos. Os eventos de mídia estão no banco, mas
  não na tela.
- `pages.og_image_id` agora é possível e não entrou.
- As galerias fixas das páginas próprias de seção continuam em `public/fotos/`, fora do painel.
- A legenda inserida pelo editor é texto simples. O sanitizador aceita negrito nela, mas o
  editor não oferece.
- As medições de tempo e memória foram feitas nesta máquina. Na VPS, medir antes de mexer no
  teto.
- Orientar a equipe de comunicação sobre a declaração de foto de assistido: decisão da
  instituição, não do sistema.
- Se um teste do e2e falhar antes de guardar o uuid da imagem enviada, a pasta dela fica órfã em
  `backend/storage/app/private/media/`. O banco de e2e é recriado a cada execução, mas o disco
  não. Aconteceu uma vez nesta sessão, e a pasta foi apagada à mão.
