# 0021 — Leitura compartilhada, separada do status de atendimento; o status `new` deixa de existir

## Contexto

As cinco listagens de formulário recebido do painel tinham um único eixo de estado, o enum
`FormSubmissionStatus`:

| Valor | Rótulo |
|---|---|
| `new` | Novo |
| `in_progress` | Em andamento |
| `done` | Concluído |
| `discarded` | Descartado |

E `new` era o estado inicial de todo registro. Duas consequências, nenhuma delas intencional:

**1. `new` era o "não lido" disfarçado.** A tela Início contava pendências com
`where('status', 'new')`, e a especificação do painel (`docs/estrutura-site.md` §4.2) descreve o
Início como "novos interesses, coletas a agendar, **mensagens não lidas**". Ou seja: o produto
já queria "não lido", e o que existia era um status de atendimento sendo lido como se fosse
leitura.

**2. Sair de `new` exigia um ato que não é leitura.** Abrir o detalhe não mudava nada — a única
forma de um registro deixar de ser "Novo" era alguém escolher um status no formulário de
atendimento e salvar. Na prática isso significa que um registro aberto, lido e resolvido por
telefone continua contando como pendência para sempre, e que a pessoa que abriu a mensagem tem
de fazer uma segunda coisa, sem relação com o que fez, só para o contador parar de mentir.

Como ninguém marcava status por diligência, o contador do Início ia se tornando um número que a
equipe aprende a ignorar — que é o pior destino de um indicador.

## Decisão

**Leitura e atendimento passam a ser dois eixos independentes. `new` deixa de existir.**

### Leitura

Duas colunas em cada uma das cinco tabelas:

| Coluna | Significado |
|---|---|
| `read_at` | quando o registro foi aberto pela **primeira** vez. `NULL` = não lido |
| `read_by` | quem abriu naquela primeira vez |

Três decisões dentro disso:

**A leitura é da equipe, não de cada pessoa.** Não existe tabela de leitura por usuário. A
pergunta que a caixa de entrada precisa responder é "alguém já olhou isto?", não "eu já olhei
isto?" — numa equipe de poucas pessoas dividindo as mesmas cinco caixas, a segunda pergunta faz
cada uma ver a mesma mensagem como nova e o trabalho ser feito duas vezes. O custo é conhecido e
aceito: quem chega depois não tem como saber, pela listagem, se *ele* já viu aquele registro.

**Abrir o detalhe É a leitura.** Não há botão "marcar como lido" — ele nunca teria o que fazer,
porque para clicá-lo a pessoa já teria aberto a tela. Existe só o contrário, "Marcar como não
lido", que é o "deixo isto para depois" de quem abriu um registro e não pôde tratá-lo. Daí a
rota ser `DELETE /api/v1/{recurso}/{uuid}/read` e não haver `POST` correspondente: a leitura é o
recurso, e desmarcar é apagá-la.

**`read_at` é a primeira leitura, não a última.** Quem abre depois não sobrescreve. "Último
acesso" é outra informação, e ela já existe onde deve existir — no log de auditoria, que registra
todo acesso ao detalhe com autor, instante e IP.

### Status de atendimento

| Valor | Rótulo | Vem de |
|---|---|---|
| `in_progress` | Em atendimento | `new` + `in_progress` |
| `done` | Concluído | `done` |
| `archived` | Arquivado | `discarded` |

`in_progress` é o estado inicial. Isso é afirmação sobre a instituição, não sobre a pessoa: um
formulário recebido está sob responsabilidade da instituição desde que chega. Quem responde
"alguém já olhou?" é `read_at`, com precisão, e não um status.

Dois rótulos mudaram de propósito:

- **"Em andamento" → "Em atendimento"**: agora é o estado de chegada, e "atendimento" é a
  palavra que a instituição usa para o que faz com um formulário.
- **"Descartado" → "Arquivado"**: nada é descartado. O registro continua lá, e sai por retenção
  (`expires_at`), não por decisão de tela. "Descartado" sugeria exclusão.

**"Concluído", e não "Respondido".** O enum serve às cinco entidades: uma mensagem de contato se
responde, mas uma coleta do bazar se realiza e uma proposta de apoio se fecha. Pior: o expurgo do
endereço do doador — o dado mais sensível desta fase — é disparado por este caso
(`PickupRequest::scopeCompletedWithAddress`), então o rótulo precisa valer para uma coleta feita.

### Contadores

A tela Início e a navegação lateral contam **não lidos**, não mais `status = new`. As duas leem a
mesma store no painel (`stores/unreadCounts.ts`): dois números para a mesma coisa na mesma tela
seria pior que nenhum.

O painel nunca soma nem subtrai localmente — pede a contagem à API de novo. A leitura é
compartilhada, então outra pessoa pode tê-la mudado no mesmo minuto.

### Auditoria

`read_at` e `read_by` ficam **fora** do `logOnly` do model, de propósito: a primeira leitura já
gera o evento `viewed` do log de acesso, no mesmo instante e sobre o mesmo registro — logá-los
também daria duas linhas na tela de Auditoria para um acesso só.

Marcar como **não** lido tem evento próprio (`marked_unread`), porque é ato deliberado que não
decorre de acesso nenhum e que **apaga rastro visível na tela**: quem desmarca esconde da equipe
que o registro já havia sido aberto. É exatamente o tipo de coisa que a auditoria existe para
registrar.

## Consequências

- A migration de dados funde `new` e `in_progress`. É **reversível, mas com perda**: o `down()`
  devolve todos como `new`. A fusão é o objetivo da mudança, e a distinção que se perde é
  justamente a que passou a viver em `read_at`.
- O contador do Início muda de significado sem mudar de lugar. Para a equipe, o efeito prático é
  que ele passa a cair ao abrir um registro — sem exigir um segundo ato.
- `STATUS_OPTIONS` no painel tem três opções em vez de quatro. Nenhum filtro salvo em favorito
  com `?status=new` ou `?status=discarded` continua valendo: a API responde 422 em vez de
  devolver lista vazia em silêncio, que é o comportamento certo para um valor que não existe.
- Se um dia a leitura por usuário se mostrar necessária, ela é **acréscimo**, não substituição: a
  leitura da equipe continua sendo a que alimenta contador e filtro, e a por usuário viraria uma
  tabela à parte.

## Alternativas descartadas

**Manter `new` e acrescentar leitura.** Duas respostas para a mesma pergunta, que divergem na
primeira vez que alguém abre um registro e não muda o status — que é o caso comum, não o raro.

**Marcar como lido por botão explícito, sem marcar ao abrir.** Transfere para a pessoa a tarefa
de manter o contador honesto, que é exatamente o que já não funcionava com o status.

**Leitura por usuário (tabela `form_submission_reads`).** Responde a pergunta errada para esta
equipe (ver acima), e multiplica por número de usuários a consulta que alimenta os contadores em
toda navegação do painel.
