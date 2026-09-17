# Relatório — Sessão 9 (Corrida do último super_admin, npm audit, fim do SQLite)

> Escopo fechado, definido no início da sessão: serializar de verdade a proteção do último
> super_admin, zerar o `npm audit` do site, e tirar o SQLite do projeto. Este relatório
> registra a prova da corrida (antes e depois) e as decisões tomadas sem consulta.

## Etapa 1 — A corrida do último super_admin

### O que estava errado

`AssertLastActiveSuperAdminSurvives` fazia `SELECT ... FOR UPDATE` nas linhas de `users` dos
super_admins ativos e contava a coleção travada. A trava era real, mas protegia a tabela
errada: **remover o papel não altera a linha de `users`, só a pivot `model_has_roles`.**

Em READ COMMITTED (padrão do PostgreSQL), a segunda transação até ficava bloqueada na trava —
mas, ao ser liberada, terminava aquele `SELECT` com o snapshot tirado no **início do próprio
comando**, anterior ao commit da primeira. Esse snapshot ainda enxergava o vínculo de papel
recém-apagado. Como as linhas de `users` não haviam sido modificadas, não havia nova versão de
linha para o PostgreSQL reavaliar (`EvalPlanQual`), e nada denunciava a diferença: as duas
transações contavam 2, as duas passavam.

### A prova (dois processos de verdade, sem gancho em produção)

Reproduzido com dois processos PHP concorrentes contra o PostgreSQL local, ambos rodando o
caminho de produção inteiro (`UpdateUser::handle` / `DeactivateUser::handle`). Nenhuma linha
de código de produção foi alterada para permitir o teste: o processo 1 abre uma transação
externa e chama a Action de verdade — o `DB::transaction()` de dentro dela vira `SAVEPOINT`,
comportamento padrão do Laravel quando já há transação aberta, então as travas ficam retidas
até o `COMMIT` de fora. Isso dá o ponto de pausa sem tocar na Action. A sincronização entre os
processos usa arquivos de sinal, e o processo 1 confirma em `pg_stat_activity`
(`wait_event_type = 'Lock'`) que o processo 2 realmente encostou na trava antes de commitar.

Cenário: A e B, os dois super_admins ativos. P1 remove o papel de B; P2, ao mesmo tempo,
remove o papel de A.

**Antes da correção** (determinístico, três execuções iguais):

```
[P1] operação feita, ainda SEM commit — travas retidas
[P1] P2 está bloqueado numa trava de linha (pg_stat_activity)
[P1] commit feito. super_admins ativos agora: 1
[P2] RESULTADO: operação PERMITIDA (sem exceção)

super_admins ATIVOS restantes: 0
  super-a@corrida.local   papéis=[]  ativo=sim
  super-b@corrida.local   papéis=[]  ativo=sim
>>> INVARIANTE VIOLADO: o sistema ficou sem nenhum super_admin ativo.
```

**Depois da correção:**

```
[P1] P2 está bloqueado numa trava de linha (pg_stat_activity)
[P1] commit feito. super_admins ativos agora: 1
[P2] RESULTADO: operação RECUSADA — Não é possível remover ou desativar o último
     super administrador ativo.

super_admins ATIVOS restantes: 1
  super-a@corrida.local   papéis=[super_admin]  ativo=sim
  super-b@corrida.local   papéis=[]             ativo=sim
>>> Invariante preservado: ainda existe super_admin ativo.
```

O caso misto — P1 remove o papel de B enquanto P2 **desativa** A — foi verificado nas duas
versões e se comporta igual: zerava antes, é recusado agora.

### A correção

Toda operação que pode reduzir o total de super_admins ativos passa primeiro por um mutex de
uma linha só: `SELECT ... FOR UPDATE` na linha do papel `super_admin` em `roles`. A linha não
é lida para nada — serve só como ponto único de encontro. Só depois disso vem a contagem, em um
**comando novo**: em READ COMMITTED cada comando tira snapshot novo, então essa contagem
enxerga tudo o que a transação anterior commitou, inclusive a remoção de papel que o snapshot
antigo escondia.

Por isso a contagem voltou a ser um `count()` comum, sem `FOR UPDATE`: quem garante exclusão
mútua é o mutex, não a trava das linhas contadas. As duas Actions chamadoras (`UpdateUser`,
`DeactivateUser`) já adquirem o mutex antes de qualquer escrita, o que mantém a ordem de
travamento consistente entre elas — sem risco de deadlock entre as duas.

### O que a suíte cobre

A corrida em si continua fora do alcance do Pest: o client de teste é síncrono, numa conexão
só. O que entrou como teste de regressão é a **ordem**: um teste captura as consultas com
`DB::listen` e falha se a contagem vier antes do mutex, ou se o `FOR UPDATE` na tabela de
papéis sumir. Conferido que ele fica vermelho quando o mutex é removido — não é tautologia.

## Etapa 2 — npm audit

Era **uma** vulnerabilidade, no `frontend-site`, e tinha correção:

| Pacote | Severidade | Tipo | Correção |
|---|---|---|---|
| `svgo` 4.0.2 | high | transitiva, de build | sim, 4.1.0 |

Dois avisos, ambos sobre o `removeScripts` do svgo deixar passar conteúdo executável
(GHSA-w27v-7q3p-w38r, GHSA-4vpr-x523-8j87). Caminho:
`nuxt > @nuxt/vite-builder > cssnano > cssnano-preset-default > postcss-svgo > svgo` — o
minificador de CSS do build. Não roda em request nenhum; o `.output/` já sai minificado.

`npm audit fix` (sem `--force`) resolveu dentro das faixas já declaradas: **`package.json` não
mudou**, só o lock — `svgo` 4.0.2 → 4.1.0, mais `css-select` e `css-what`, que são dependências
internas do próprio svgo. `build` e `generate` conferidos depois, os dois passam.

**Nada sobrou sem correção**, então o CI continua auditando tudo, sem `--omit=dev`: relaxar o
portão agora seria afrouxar uma verificação que está passando. O `frontend-admin` já estava
limpo (zero vulnerabilidades, com e sem `--omit=dev`) — nunca teve o problema, o que bate com
o job dele estar verde no CI enquanto o do site falhava.

## Etapa 3 — Fim do SQLite

A suíte já rodava só em Postgres desde a sessão anterior; o resto do projeto ainda não. Manter
SQLite como atalho de desenvolvimento mantinha vivo exatamente o que causou o bug de busca:
dois bancos, dois comportamentos.

Removido: a seção "sem Docker" do README que mandava trocar para `DB_CONNECTION=sqlite`; as
guardas `DB::getDriverName()` em `FormSubmissionColumns::addStatusCheckConstraint()` e nas
migrations de `pages` e `transparency_documents`; a conexão `sqlite` de `config/database.php`;
o `touch database.sqlite` do `post-create-project-cmd`.

Trocado: o fallback `env('DB_CONNECTION', 'sqlite')` do esqueleto do Laravel virou `'pgsql'`,
em `config/database.php` e nos dois pontos de `config/queue.php`.

Dois ganhos além da limpeza:

1. O `CHECK` que espelha o enum no banco **sempre** é criado agora. Antes ele sumia em silêncio
   em qualquer banco que não fosse Postgres. Conferidas as sete constraints no banco de
   desenvolvimento depois de `migrate:fresh --seed`.
2. Um `DB_CONNECTION=sqlite` esquecido em algum `.env` passa a falhar na hora, com o nome do
   driver no erro, em vez de funcionar em silêncio.

No lugar da seção removida, o README ganhou o caminho equivalente sem Docker de verdade: subir
só `postgres`/`redis`/`mailpit` do compose e rodar PHP e Node na máquina.

## Decisões tomadas sem consulta

1. **Mutex na linha do papel, não `pg_advisory_lock`.** O advisory lock resolveria o mesmo
   problema e seria até mais barato, mas exige escolher e documentar um número mágico global, e
   não aparece em `pg_locks` ligado a nenhuma tabela — mais difícil de diagnosticar depois. A
   linha do papel `super_admin` já existe, é única, e o nome dela diz o que está sendo
   protegido.

2. **Contagem voltou a ser `count()` no banco.** A sessão anterior tinha trocado por
   `get()->count()` para contornar a recusa do Postgres em combinar `FOR UPDATE` com agregação.
   Sem o `FOR UPDATE` na contagem, essa restrição deixa de existir e o `count()` comum é o mais
   direto — a exclusão mútua passou a ser responsabilidade do mutex.

3. **Conexão `sqlite` removida de `config/database.php`, não só o fallback.** A instrução pedia
   "deixando o caminho PostgreSQL como único". Deixar a conexão definida faria um
   `DB_CONNECTION=sqlite` continuar funcionando em silêncio, que é justamente o modo como o
   segundo comportamento de banco volta sem ninguém decidir.

4. **CI não passou a usar `--omit=dev`.** A instrução previa isso para o que sobrasse sem
   correção; não sobrou nada.

## Pendência conhecida

`backend/database/database.sqlite` (385 KB, de 26/08) continua no disco — resíduo local do
caminho antigo, ignorado pelo git (`backend/database/.gitignore`) e não referenciado por mais
nada depois desta sessão. Não foi apagado por ser arquivo local de quem desenvolve, não do
repositório; pode ser removido à vontade.
