# Relatório da sessão 25 — dados das listagens de formulário no painel

Três itens pedidos, três entregues, um commit por item. Suíte Pest (486 testes), Larastan,
Pint, build do site e do painel e bateria de ponta a ponta (116 testes) verdes ao fim de cada
um. Conferência visual feita no navegador, contra a pilha de desenvolvimento — o que foi visto
está listado no fim.

---

## Item 1 — "Recebido em" (commit `2e3a9ca`)

As cinco listagens não mostravam quando o registro chegou. A informação existia no banco e não
aparecia em lugar nenhum da tela.

**O que mudou:**

- Coluna "Recebido em" (dd/mm/aaaa HH:mm) nas cinco listagens e no detalhe.
- Ordenação fixa da mais recente para a mais antiga, sem reordenação pelo usuário.
- Filtros De/Até passam a interpretar a data no fuso de Londrina.

**A conversão de fuso é o ponto, não a coluna.** O servidor grava em UTC e fica nos Estados
Unidos (ver `docs/protecao-de-dados.md`, "Transferência internacional"); quem lê o painel está
em Londrina. Entre 21h e meia-noite as duas contas divergem em um dia inteiro. O filtro antigo
usava `whereDate('created_at', ...)`, que compara a data do UTC gravado: "De 28/09" deixava de
fora tudo o que chegou depois das 21h do dia 28, e incluía o que chegou depois das 21h do dia
27. Sem erro nenhum aparente.

**Decisões tomadas sem consulta:**

1. **A API entrega a data já formatada (`created_at_label`), não só o ISO.** O painel não
   converte fuso nem formata data. Pela regra 1 do `CLAUDE.md` — a API é a única fonte de
   verdade, inclusive das mensagens — e por uma razão prática: o navegador de quem usa o painel
   pode estar em qualquer fuso, e o fuso de exibição é decisão da instituição, não do
   navegador. `App\Support\InstitutionalTime` concentra fuso e formato, lendo
   `config('institution.timezone')`. O e-mail de notificação, que fazia a mesma conta à mão,
   passou a usá-lo: tela e e-mail não podem dizer horas diferentes.

2. **A listagem de coletas perdeu a ordenação por `scheduled_for`.** Era a única das cinco que
   não começava pelo que chegou por último — ordenava pela agenda de coleta. O pedido foi
   explícito ("todas essas listagens", "da mais recente para a mais antiga"), e a ordenação por
   agenda não sobrevive a ele como padrão fixo: um pedido novo nasceria no meio da lista. A data
   agendada continua como coluna, e "reordenar pelo cabeçalho" ficou registrado como pendência
   no roadmap — é lá que a agenda do bazar volta, se fizer falta.

3. **A entrada da listagem passou a valer por FormRequest** (`IndexFormSubmissionsRequest`).
   Antes, uma data mal formada na query string chegava ao `CarbonImmutable::parse()` e virava
   500. Agora é 422. Também centralizei filtros e paginação das cinco em
   `Concerns\ListsFormSubmissions` — o fuso e, no item 2, o filtro de leitura teriam sido a
   terceira e a quarta cópia do mesmo bloco.

---

## Item 2 — lido/não lido (commit `36ae398`)

Registrado em `docs/decisoes/0021-leitura-separada-do-status-de-atendimento.md`.

**O diagnóstico:** o status `new` era o "não lido" disfarçado. A tela Início contava pendências
com `where('status', 'new')`, e `docs/estrutura-site.md` §4.2 já descrevia o Início como
"mensagens não lidas" — o produto queria leitura, e o que existia era status de atendimento
sendo lido como se fosse leitura. Pior: abrir o detalhe não mudava nada. A única forma de sair
de "Novo" era alguém escolher um status e salvar. Um pedido lido e resolvido por telefone
contava como pendência para sempre, e um contador assim vira um número que a equipe aprende a
ignorar.

**O que mudou:**

- `read_at`/`read_by` nas cinco tabelas. Abrir o detalhe marca como lido; botão "Marcar como
  não lido" desfaz.
- Filtro Lidos/Não lidos; não lidos em negrito e com indicador na listagem.
- Cards do Início e contadores no menu lateral contam não lidos.
- Status revisado: `in_progress` ("Em atendimento", inicial), `done` ("Concluído"), `archived`
  ("Arquivado"). `new` deixou de existir; dados migrados.

**Decisões tomadas sem consulta:**

1. **A leitura é da equipe, não de cada pessoa.** Não há tabela de leitura por usuário. A
   pergunta que a caixa de entrada precisa responder é "alguém já olhou isto?" — numa equipe
   pequena dividindo as mesmas cinco caixas, a pergunta por usuário faz cada pessoa ver a mesma
   mensagem como nova e o trabalho ser feito duas vezes. O custo, aceito e registrado na ADR:
   quem chega depois não sabe, pela listagem, se *ele* já viu aquele registro.

2. **Não existe botão "marcar como lido".** Ele nunca teria o que fazer: para clicá-lo a pessoa
   já teria aberto a tela. Por isso a rota é `DELETE /{recurso}/{uuid}/read` e não há `POST`
   correspondente — a leitura é o recurso, e desmarcar é apagá-la.

3. **`read_at` é a primeira leitura, não a última.** Quem abre depois não sobrescreve. "Último
   acesso" é outra informação, e já vive no log de auditoria.

4. **"Concluído", e não "Respondido".** O pedido sugeriu "Em atendimento, Respondido,
   Arquivado". Adotei os três, trocando o do meio: o enum serve às cinco entidades — uma
   mensagem se responde, mas uma coleta se realiza e uma proposta de apoio se fecha. E é este
   caso que dispara o expurgo do endereço do doador
   (`PickupRequest::scopeCompletedWithAddress`), o dado mais sensível desta fase; o rótulo
   precisa valer para uma coleta feita. **Se "Respondido" for mesmo o termo que a equipe usa,
   é uma linha no enum para mudar.**

5. **"Em atendimento" é o estado inicial.** Soa estranho para um registro que ninguém abriu, e
   é deliberado: um formulário recebido está sob responsabilidade da instituição desde que
   chega, e quem responde "alguém já olhou?" agora é `read_at`, com precisão. Era exatamente a
   sobreposição que o pedido identificou.

6. **"Descartado" virou "Arquivado".** Nada é descartado — o registro continua lá e sai por
   retenção (`expires_at`), não por decisão de tela. O rótulo antigo sugeria exclusão.

7. **`read_at` ficou fora do `logOnly` do model.** A primeira leitura já gera o evento `viewed`
   do log de acesso, no mesmo instante e sobre o mesmo registro; logá-la também daria duas
   linhas na tela de Auditoria para um acesso só. Marcar como **não** lido tem evento próprio
   (`marked_unread`), porque é ato deliberado que apaga rastro visível na tela — quem desmarca
   esconde da equipe que o registro já havia sido aberto.

8. **Os contadores do menu e do Início leem a mesma store** (`stores/unreadCounts.ts`). Dois
   números para a mesma coisa na mesma tela seria pior que nenhum. E a contagem vem sempre da
   API, nunca de soma local: a leitura é compartilhada, outra pessoa pode tê-la mudado no mesmo
   minuto. A store é limpa no fim da sessão, senão quem entrasse depois na mesma aba veria por
   um instante os números do papel anterior.

**Sobre a migration:** a fusão de `new` com `in_progress` é reversível, mas com perda — o
`down()` devolve todos como `new`. A fusão é o objetivo da mudança, e a distinção que se perde
é justamente a que passou a viver em `read_at`. Conferida nos dois sentidos contra o Postgres.

---

## Item 3 — auditoria (commit `90880d7`)

### Onde os acessos são registrados hoje (confirmado)

Tudo em `activity_log`. Quatro origens — a tabela completa, com `log_name`, `event` e se grava
IP, entrou em `docs/protecao-de-dados.md`, seção "Auditoria":

| Origem | `log_name` | `event` | IP |
|---|---|---|---|
| `LogsSubmissionAccess`, em todo `show()` administrativo | `forms` | `viewed` | sim |
| `MarkSubmissionAsUnread` (novo nesta sessão) | `forms` | `marked_unread` | sim |
| `LogsActivity` dos models de formulário | `default` | `created`, `updated` | não |
| Contas e sessão (`Actions\Users\*`, `Listeners\Auth\*`) | `users`, `auth` | vários | conforme |

O registro existia desde a sessão 6 e **não havia como olhar para ele sem abrir o banco.** Um
log que ninguém lê não é auditoria; é armazenamento.

### O que mudou

- `GET /api/v1/audit-logs`, só leitura, com filtros por usuário (uuid da conta), tipo de
  formulário e período.
- Tela `/admin/auditoria`: data/hora, usuário, ação, tipo de formulário, registro (com link) e
  IP.
- Seção "Histórico de acessos" recolhível no detalhe, para `super_admin`.
- A frase do menu lateral ("Todo acesso ao detalhe...") saiu.
- O alerta destacado do detalhe virou uma linha discreta no rodapé do registro.

### Decisões tomadas sem consulta

1. **Só `super_admin`, e nem `direcao`.** `App\Policies\ActivityPolicy` com tudo `false`; passa
   só o bypass. Quem audita não pode ser quem é auditado — `direcao` aparece nas linhas do log,
   e dar a ela a chave do log tiraria do registro a única propriedade que o faz valer algo.

2. **Só leitura, e nunca deve deixar de ser.** Não há `create`, `update` nem `delete` na Policy
   nem no controller. Um log que o painel possa alterar não serve de log.

3. **A tela mostra só formulário.** A mesma tabela guarda login, logout e alteração de conta;
   misturar tudo daria uma tela que não responde pergunta nenhuma. Auditoria de conta ficou
   como pendência no roadmap.

4. **A tela nunca mostra o `attribute_changes` do spatie.** Ela diz QUE houve alteração, não o
   quê. Exibir o diff seria a cópia em texto puro que a criptografia existe para evitar — é a
   promessa central de `docs/protecao-de-dados.md`. Há teste Pest afirmando que nome, mensagem
   e anotação interna não aparecem no corpo da resposta.

5. **Nenhum id de linha de log no payload.** `activity_log.id` é sequencial. A tela é só
   leitura e não tem rota por entrada, então o id não serve para nada e expô-lo quebraria a
   regra 4 do `CLAUDE.md` sem ganho. A chave de linha do Vue é composta no componente.

6. **Nota, não alerta, no rodapé do registro.** O pedido já apontava a direção; o motivo é que
   a frase é verdade permanente sobre todo registro, e um banner destacado em toda tela deixa
   de ser lido depois da terceira vez. A frase do menu saiu pelo mesmo motivo — ocupava espaço
   permanente para dizer uma vez o que agora está no lugar certo.

7. **`App\Models\Contracts\FormSubmission` nasceu aqui.** O `subject` de `activity_log` é
   relação polimórfica e chega como `Model` genérico. Sem um tipo que diga "formulário recebido
   tem identificador público", a única forma de ler o uuid ali seria `getAttribute('uuid')` com
   cast de string — acesso destipado exatamente onde a regra 4 precisa valer.

---

## Cobertura

- **Pest:** 486 testes. Novos: `FormSubmissionListingDateTest` (rótulo e as duas pontas do
  filtro na faixa em que UTC e São Paulo divergem), `FormSubmissionReadStateTest` (leitura
  compartilhada entre duas contas, primeira leitura não sobrescrita, auditoria do desmarcar,
  independência de leitura e status), `FormAuditLogTest` (recusa para os seis papéis de área,
  filtros, ausência de dado pessoal e de id sequencial no payload).
- **Ponta a ponta:** 116 testes. Três arquivos novos em `e2e/tests/painel/` — um por item, cada
  um cobrindo o fluxo principal. O seeder de e2e passou a criar formulários dos cinco tipos,
  incluindo um registro-marco recebido em 16/01/2026 01:30 UTC, que a tela tem de mostrar como
  15/01/2026 22:30 — é assim que a bateria confere o fuso sem recalcular a conta em TypeScript.
- Quatro testes existentes foram ajustados a mudanças de interface (rótulos de status, texto do
  aviso do Início, contadores no menu). Nenhum foi afrouxado.

---

## Conferência visual (feita)

Navegador aberto contra a pilha de desenvolvimento, autenticado como `super_admin`. Conferido:

- **Início** — cards com a contagem e o qualificador "não lidos"; contador `16` ao lado de
  "Mensagens de contato" no menu; item "Auditoria" em Configurações; a frase antiga sumiu do
  rodapé do menu.
- **Listagem** — coluna "Recebido em" preenchida, ordem decrescente, filtro "Leitura", linhas
  não lidas em negrito com o ponto laranja. Os registros migrados aparecem como "Em
  atendimento".
- **Detalhe** — ao abrir, o contador do menu caiu de 16 para 15 sozinho; "Lido por" preenchido
  com nome e horário; botão "Marcar como não lido" ao lado do selo de status; nota discreta e
  cinza no rodapé, no lugar do banner.
- **Histórico de acessos** — recolhido por padrão, abre com as entradas do registro (o acesso
  recém-feito e "Recebido pelo site · site público · sem IP").
- **Auditoria** — tabela com as seis colunas, links "Abrir registro" funcionando, e um registro
  já expurgado aparecendo honestamente com "—" no tipo e no link.

## O que precisa de conferência humana

1. **Os rótulos de status com a equipe.** "Em atendimento", "Concluído" e "Arquivado" são
   escolha minha (ver decisão 4 do item 2). Se a palavra que a instituição usa for outra, é uma
   linha no enum e uma migration de dados.
2. **A perda da ordenação por data agendada na listagem de coletas** (decisão 2 do item 1) —
   vale perguntar a quem usa o bazar se aquela ordem fazia falta no dia a dia.
3. **Formato das datas nos campos De/Até.** São `<input type="date">` nativos: o navegador
   decide o formato pelo idioma dele. Num navegador em português aparece dd/mm/aaaa; num em
   inglês, mm/dd/aaaa. Não é escolha do código e não mudou nesta sessão.
4. **O comportamento em telas estreitas** não foi conferido nesta sessão. A tabela rola na
   horizontal como as outras (`.table-wrapper`), e o contador do menu usa `margin-left: auto`
   dentro do link — vale um olhar num celular real.
