# 0024 — Biblioteca de mídia: imagem por endereço canônico, derivadas no upload e foto de assistido recusada

## Contexto

Até a sessão 27, o painel não permitia adicionar, trocar ou remover imagem nenhuma. As fotos do
site eram arquivos em `frontend-site/public/fotos/`, preparados à mão (docs/fotos.md), e o
conteúdo das páginas não tinha como mostrar imagem: o sanitizador derrubava `figure` e não
aceitava `img`. Sem manutenção do desenvolvedor, que não vai existir, o site ficaria com as fotos
de hoje para sempre.

Três restrições do projeto moldam qualquer solução:

- **Regra 6 do CLAUDE.md:** nenhuma foto de assistido é publicável sem consentimento de imagem
  vigente, e a trava é na API. **O sistema ainda não registra consentimento** — a entidade
  `consents` é da Fase 2, bloqueada até o inventário de dados (docs/roadmap.md).
- **Regra 7:** EXIF removido de todo upload de imagem, sem exceção.
- **O conteúdo vive no banco e viaja entre ambientes por pacote** (ADR 0023). Uma referência de
  imagem que dependa de host, porta ou caminho do servidor quebra na importação.

## Decisão

### 1. A página guarda só `/midia/{uuid}`

O editor grava `<figure><img src="/midia/{uuid}" alt="…"><figcaption>…</figcaption></figure>`.
Sem largura, sem versão, sem host. A leitura pública
(`App\Actions\Media\ExpandContentImages`) expande isso no `<img>` completo: `srcset` com as
derivadas que existem **agora**, `sizes`, `width`/`height` e `loading="lazy"`.

É o que torna a substituição barata: trocar o arquivo muda o `srcset` que o site recebe (o cache
das páginas que usam a imagem é esquecido) e o que cada endereço entrega. **Nenhuma página
precisa ser editada**, e o endereço público não muda.

O sanitizador aceita `src` **só** nessa forma canônica (`MediaSourceAttributeSanitizer`). Imagem
de outro servidor seria um rastreador de quem visita o site, fugiria da remoção de EXIF e
poderia mudar de conteúdo sem ninguém saber. `AssertContentImagesArePublishable` recusa, ao
salvar, uma imagem que não existe, que é impublicável ou que não tem texto alternativo.

### 2. Servida pelo domínio do site, decidida pela API

`/midia/{uuid}[/{largura}.webp]` é rota do Nitro e faz **proxy puro** para
`/api/v1/public/media/...`. É o mesmo desenho do PDF de transparência (ADR 0017), pelos mesmos
motivos: arquivo fora do webroot, conteúdo contado como do próprio site, regra só na API. A API
responde 404 para a imagem impublicável, e isso a tira do ar na hora, mesmo com HTML antigo em
cache. Largura pedida que não existe cai na maior que cabe, então endereço indexado não vira 404
depois de uma troca por imagem menor.

Cache `public, max-age=600, must-revalidate` com ETag do SHA-256, **sem `immutable`**, porque o
endereço é estável de propósito. O proxy repassa `If-None-Match`, e a revalidação custa um 304.

### 3. Derivadas no upload, síncronas — não em fila

A original é **decodificada e recodificada** com GD: o arquivo gravado nasce do zero e não leva
nenhum bloco do enviado (EXIF, GPS, XMP, miniatura embutida). A orientação da câmera é gravada no
pixel antes. As derivadas webp saem da escala do projeto (400, 640, 960, 1280, 1920), nunca
maiores que a original.

`docs/arquitetura.md` previa "thumbnails em fila". A decisão aqui é o contrário, e a troca é
consciente:

- **Síncrono:** a imagem está pronta quando o upload responde. Não há estado "processando", nem
  página publicada com imagem ainda sem derivada, nem substituição que deixa o site meio
  velho e meio novo.
- **O custo é limitado:** até 10 MB e 30 megapixels (conferido pelo cabeçalho, antes de
  decodificar), com o upload feito por uma ou duas pessoas da instituição, de vez em quando. Uma
  fila traria worker, estado e tela de espera para economizar poucos segundos.

Medido na sessão 27 (máquina de desenvolvimento, `memory_limit=256M` como em produção, JPEG
sintético, processamento completo: decodificação, original e derivadas):

| Imagem | Tempo | Pico de memória |
|---|---|---|
| 12 MP (4000×3000) | 1,02 s | 82 MB |
| 20 MP (5472×3648) | 1,43 s | 121 MB |
| 30 MP (6000×5000) | 1,97 s | 162 MB |
| 36 MP (6000×6000) | 2,34 s | 192 MB |

O teto ficou em 30 MP, e não em 36, pela folga: 192 MB mais o resto da requisição chegam perto
dos 256M. A VPS pode ser mais lenta que a máquina onde isto foi medido, e vale medir lá antes
de mudar o teto.

Se um dia o volume ou o tamanho mudarem, a fila entra em `StoreMedia`/`ReplaceMediaFile` sem
mudar o formato do conteúdo.

O preço declarado da recodificação: JPEG em qualidade 90 perde um pouco, e o perfil de cor
embutido sai (foto em Display P3 fica levemente menos saturada). PNG é sem perda.

### 4. Pasta por versão, nome derivado

Arquivos em `media/{uuid}/{versão}/original.{ext}` e `…/{largura}.webp`, no disco `local`. O
caminho é **calculado**, nunca coluna: o nome do arquivo enviado não chega ao disco nem ao banco.
A substituição grava a versão nova ao lado, troca o número numa transação com a linha travada e
só então apaga a anterior. Em nenhum instante o endereço público aponta para arquivo inexistente.

A versão anterior é **apagada**, não guardada, e a exclusão apaga a imagem sem lixeira. Muitas
vezes substituir ou excluir é corrigir uma foto que não devia estar no ar, e uma cópia escondida
no disco não cumpriria isso.

### 5. Foto de criança ou adolescente atendido: declaração obrigatória, "sim" recusado

`docs/dominio.md` previa `assisted_minor_id` com "gate bloqueando por padrão". Sem a entidade de
assistidos, essa coluna nunca poderia ser preenchida, e o bloqueio nunca seria acionado. **A
trava que funciona hoje é uma declaração de quem sobe:**

- `depicts_assisted_minor` é **obrigatória, sem valor padrão**. A tela oferece "Não" e "Sim,
  mostra", nenhuma marcada de início.
- **O critério é a pessoa hoje, não a data da foto** (revisto na sessão 28). A pergunta é se a
  imagem mostra alguém que *hoje ainda é criança ou adolescente e que é ou foi atendido* pela
  instituição. Até a sessão 27 ela dizia só "criança ou adolescente atendido", e recusaria o
  acervo de 1963 inteiro, cujas crianças hoje passam dos 60 anos. Acervo em que todos os
  retratados já são adultos responde "Não". Foto recente de atendidos continua "Sim", e
  recusada. Na dúvida sobre a idade de alguém hoje, "Sim". A tela mostra esses três casos, e a
  recusa da API também explica o do acervo.
- **"Sim" é recusado no upload (422).** Sem consentimento registrado, a foto não tem finalidade
  possível no site, e guardá-la seria tratamento de dado de menor sem base, justamente o que a
  Fase 2 bloqueada existe para organizar.
- **Marcar depois é a correção prevista.** A imagem deixa de ser publicável na hora: a API
  responde 404, a figura some do HTML público com a legenda, e salvar uma página que ainda a use
  é recusado. **A marcação não se desfaz**, nem pelo painel nem por pacote de conteúdo: só um
  consentimento registrado poderia liberar a imagem.
- **Marcar exige confirmação forte** (sessão 28). A tela abre um diálogo com as consequências e
  as páginas onde a imagem está, e só libera o botão com a frase "tirar do site" digitada. A API
  recusa a marcação sem `confirm_marking` aceito (UpdateMediaRequest), para que um valor trocado
  no corpo da requisição não marque nada por engano.
- A imagem marcada **não viaja** no pacote de conteúdo.

Quando `consents` existir, `Media::isPublishable()` passa a consultar o consentimento de imagem
vigente, e só ele muda. O upload com "sim" deixa de ser recusado quando houver como vincular a
foto ao assistido e ao consentimento.

**Interpretação a confirmar com a instituição:** o pedido falava em "criança ou adolescente
atendido hoje". A regra adotada alcança também quem **já foi** atendido e ainda é menor, porque
continua hipervulnerável, e o CLAUDE.md manda escolher o mais restritivo na dúvida. Se a
instituição entender que só o atendido atual conta, a mudança é só de texto.

**Dúvida registrada, não resolvida aqui:** a declaração depende de quem sobe responder com
verdade, e nenhuma trava técnica reconhece uma criança numa foto. A mitigação é de processo:
orientar a equipe de comunicação e rever a biblioteca periodicamente. Definir isso é decisão da
instituição com quem responde pela LGPD, não do sistema.

### 6. Permissão e auditoria

A mesma matriz de `pages`: `comunicacao` e `direcao` enviam, substituem e editam; só `direcao`
exclui. Upload, substituição, edição e exclusão vão para o `activity_log` (`log_name` `media`),
cada um com evento próprio (`uploaded`, `replaced`, `updated`, `deleted`) e com quem fez. A tela
"Auditoria" do painel ainda mostra só formulários recebidos. Os registros de mídia estão no
banco, mas não na tela (pendência no roadmap).

### 7. Exclusão bloqueada enquanto em uso

A recusa diz **quais páginas** usam a imagem. Página na lixeira não conta: ela não está no ar, e
o painel não oferece como restaurá-la. Bloquear por causa dela seria um beco sem saída.

## Alternativas descartadas

- **Guardar no conteúdo o `<img>` completo, com `srcset`.** A substituição exigiria reescrever
  cada página que usa a imagem, e o HTML carregaria o host de um ambiente para o outro.
- **Servir `storage/app/public` pelo Nginx (link simbólico).** É o mais rápido e poria o arquivo
  no webroot, sem como tirar do ar a foto marcada: o arquivo continuaria respondendo.
- **`intervention/image` ou `spatie/image`.** GD já está no servidor e faz tudo o que é preciso.
  Dependência nova seria superfície de ataque para economizar umas cem linhas.
- **Derivadas em fila.** Ver item 3.
- **Coluna `assisted_minor_id` já agora.** Não teria uso funcional possível antes da Fase 2 (o
  mesmo critério que adiou `pages.og_image_id`), e daria a falsa impressão de uma trava que nada
  aciona.
- **Aceitar a foto de assistido e só impedir a publicação.** Guardaria dado sensível de menor sem
  finalidade. Recusar é o mais restritivo, e o CLAUDE.md manda escolher o mais restritivo na
  dúvida.
- **Nome do arquivo ou slug na URL pública.** Seria melhor para busca, mas o nome enviado costuma
  trazer dado pessoal ("foto da Maria.jpg"), e a instituição não vai manter um slug por imagem.
  O texto alternativo já descreve a imagem para o buscador.
