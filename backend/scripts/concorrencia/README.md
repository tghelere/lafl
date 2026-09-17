# Reprodução da corrida do último super_admin

Scripts usados para reproduzir, com dois processos PHP de verdade contra o PostgreSQL, a
corrida que `App\Actions\Users\AssertLastActiveSuperAdminSurvives` existe para impedir: dois
super_admins removendo o papel (ou desativando) um ao outro ao mesmo tempo, de um jeito que
zeraria o total de super_admins ativos do sistema. Histórico completo, com a saída "antes" (o
bug) e "depois" (a correção) da sessão original, em `docs/relatorio-sessao-9.md`.

Não é teste automatizado — a suíte Pest usa uma conexão só, síncrona, e não reproduz
concorrência de verdade. Isto aqui é para conferir manualmente, depois de qualquer mudança em
`AssertLastActiveSuperAdminSurvives` ou nas Actions que a chamam, que a serialização continua
funcionando — a seção "Prova de que o script detecta o bug" abaixo mostra a saída real dos
dois casos, com a proteção quebrada de propósito e depois restaurada.

## ⚠️ Antes de rodar

- **Roda exclusivamente contra o banco de teste dedicado**
  (`lar_analia_franco_test`, ver `backend/.env.testing`) — **nunca** o banco de
  desenvolvimento. `bootstrap.php` força `APP_ENV=testing` sozinho, antes de qualquer
  bootstrap do Laravel, então não depende de quem roda lembrar de exportar nada; e confere o
  **nome do banco resolvido**, não só o ambiente — se por algum motivo a conexão não for
  exatamente `lar_analia_franco_test` (ex.: alguém sobrescreve `DB_DATABASE` por fora, ou
  `backend/.env.testing` é editado para apontar para outro lugar), o script recusa rodar e
  imprime o banco que encontrou.
- **`migrate:fresh` antes de cada execução** (dentro de `setup.php`) — o banco de teste é
  apagado e recriado do zero toda vez. É seguro porque é sempre o banco de teste (ver acima),
  nunca o de desenvolvimento.
- **Nunca rode isto enquanto a suíte Pest estiver rodando.** Os dois usam o mesmo banco de
  teste; o `migrate:fresh` deste script apaga as tabelas debaixo de qualquer teste com
  transação aberta no meio, e o inverso também vale — rodar a suíte no meio da corrida
  bagunça os dois. Rode um de cada vez.
- **Cria dois usuários de teste** (`super-a@corrida.local`, `super-b@corrida.local`), com
  papel `super_admin`, só no banco de teste.
- **Tudo é apagado ao final** — `run.sh` chama `cleanup.php` mesmo se a corrida falhar no meio
  ou for interrompida (`trap ... EXIT`). Como o próximo `setup.php` recria o banco do zero de
  qualquer forma, não há muito risco em não limpar, mas o script limpa mesmo assim, para quem
  quiser inspecionar o banco de teste logo depois de uma corrida.

## Como rodar

```bash
cd backend/scripts/concorrencia

./run.sh papel   # cenário 1: os dois removem o papel um do outro
./run.sh misto   # cenário 2: um remove o papel, o outro desativa
```

## Saída esperada

**Com a correção em vigor** (estado atual do código), nos dois cenários:

```
[P1] operação feita, ainda SEM commit — travas retidas
[P1] P2 está bloqueado numa trava de linha (pg_stat_activity)
[P1] commit feito. super_admins ativos agora: 1
[P2] RESULTADO: operação RECUSADA — Não é possível remover ou desativar o último
     super administrador ativo.

super_admins ATIVOS no sistema inteiro: 1
  super-a@corrida.local   papéis=[super_admin]  ativo=sim
  super-b@corrida.local   papéis=[]             ativo=sim
>>> Invariante preservado: ainda existe super_admin ativo.
```

`super_admins ATIVOS no sistema inteiro` conta o banco de teste inteiro — como
`setup.php` acabou de rodar `migrate:fresh`, os únicos super_admins que existem são os dois
que ele mesmo criou, então o total bate certinho com a demonstração.

## Prova de que o script detecta o bug

Rodado com a versão **anterior à correção** de `AssertLastActiveSuperAdminSurvives`
(`git show 638d4cb^:backend/app/Actions/Users/AssertLastActiveSuperAdminSurvives.php`,
trocada temporariamente no arquivo local, sem commitar — restaurada com `git checkout --`
logo em seguida), depois com a correção de volta. Saída real dos dois cenários, copiada
direto do terminal — não reconstruída.

### Antes da correção (bug presente)

`./run.sh papel`:

```
==================== SETUP (cenário: papel) ====================
[06:07:57 setup] banco: lar_analia_franco_test
[06:07:57 setup] super-a e super-b criados, ambos super_admin ativos
[06:07:57 setup] super_admins ativos agora (deve ser exatamente 2): 2

==================== CORRIDA ====================
  [06:07:57 P1] abrindo transação externa
  [06:07:57 P1] removendo super_admin de B (caminho real: UpdateUser::handle)
  [06:07:57 P1] operação feita, ainda SEM commit — travas retidas
  [06:07:57 P1] esperando P2 encostar na trava...
  [06:07:57 P1] P2 está bloqueado numa trava de linha (pg_stat_activity)
  [06:07:57 P1] commitando
  [06:07:57 P1] commit feito. super_admins ativos agora: 1
  [06:07:57 P2] P1 está com a trava; vou tentar remover super_admin de A (caminho real: UpdateUser)
  [06:07:57 P2] RESULTADO: operação PERMITIDA (sem exceção)

==================== ESTADO FINAL (antes da limpeza) ====================
[06:07:57 final] super_admins ATIVOS no sistema inteiro: 0
[06:07:57 final]   super-a@corrida.local        papéis=[] ativo=sim
[06:07:57 final]   super-b@corrida.local        papéis=[] ativo=sim
[06:07:57 final] >>> INVARIANTE VIOLADO: o sistema ficou sem nenhum super_admin ativo.
```

`./run.sh misto`:

```
==================== SETUP (cenário: misto) ====================
[06:08:03 setup] banco: lar_analia_franco_test
[06:08:03 setup] super-a e super-b criados, ambos super_admin ativos
[06:08:03 setup] super_admins ativos agora (deve ser exatamente 2): 2

==================== CORRIDA ====================
  [06:08:03 P1] abrindo transação externa
  [06:08:03 P1] removendo super_admin de B (caminho real: UpdateUser::handle)
  [06:08:03 P1] operação feita, ainda SEM commit — travas retidas
  [06:08:03 P1] esperando P2 encostar na trava...
  [06:08:03 P1] P2 está bloqueado numa trava de linha (pg_stat_activity)
  [06:08:03 P1] commitando
  [06:08:03 P1] commit feito. super_admins ativos agora: 1
  [06:08:03 P2] P1 está com a trava; vou tentar DESATIVAR A (caminho real: DeactivateUser)
  [06:08:03 P2] RESULTADO: desativação PERMITIDA (sem exceção)

==================== ESTADO FINAL (antes da limpeza) ====================
[06:08:03 final] super_admins ATIVOS no sistema inteiro: 0
[06:08:03 final]   super-a@corrida.local        papéis=[super_admin] ativo=não
[06:08:03 final]   super-b@corrida.local        papéis=[] ativo=sim
[06:08:03 final] >>> INVARIANTE VIOLADO: o sistema ficou sem nenhum super_admin ativo.
```

### Depois de restaurar a correção

`./run.sh papel`:

```
==================== SETUP (cenário: papel) ====================
[06:08:14 setup] banco: lar_analia_franco_test
[06:08:14 setup] super-a e super-b criados, ambos super_admin ativos
[06:08:14 setup] super_admins ativos agora (deve ser exatamente 2): 2

==================== CORRIDA ====================
  [06:08:15 P1] abrindo transação externa
  [06:08:15 P1] removendo super_admin de B (caminho real: UpdateUser::handle)
  [06:08:15 P1] operação feita, ainda SEM commit — travas retidas
  [06:08:15 P1] esperando P2 encostar na trava...
  [06:08:15 P1] P2 está bloqueado numa trava de linha (pg_stat_activity)
  [06:08:15 P1] commitando
  [06:08:15 P1] commit feito. super_admins ativos agora: 1
  [06:08:15 P2] P1 está com a trava; vou tentar remover super_admin de A (caminho real: UpdateUser)
  [06:08:15 P2] RESULTADO: operação RECUSADA — Não é possível remover ou desativar o último
       super administrador ativo.

==================== ESTADO FINAL (antes da limpeza) ====================
[06:08:15 final] super_admins ATIVOS no sistema inteiro: 1
[06:08:15 final]   super-a@corrida.local        papéis=[super_admin] ativo=sim
[06:08:15 final]   super-b@corrida.local        papéis=[] ativo=sim
[06:08:15 final] >>> Invariante preservado: ainda existe super_admin ativo.
```

`./run.sh misto`:

```
==================== SETUP (cenário: misto) ====================
[06:08:20 setup] banco: lar_analia_franco_test
[06:08:20 setup] super-a e super-b criados, ambos super_admin ativos
[06:08:20 setup] super_admins ativos agora (deve ser exatamente 2): 2

==================== CORRIDA ====================
  [06:08:20 P1] abrindo transação externa
  [06:08:20 P1] removendo super_admin de B (caminho real: UpdateUser::handle)
  [06:08:20 P1] operação feita, ainda SEM commit — travas retidas
  [06:08:20 P1] esperando P2 encostar na trava...
  [06:08:20 P1] P2 está bloqueado numa trava de linha (pg_stat_activity)
  [06:08:20 P1] commitando
  [06:08:20 P1] commit feito. super_admins ativos agora: 1
  [06:08:20 P2] P1 está com a trava; vou tentar DESATIVAR A (caminho real: DeactivateUser)
  [06:08:20 P2] RESULTADO: desativação RECUSADA — Não é possível remover ou desativar o último
       super administrador ativo.

==================== ESTADO FINAL (antes da limpeza) ====================
[06:08:20 final] super_admins ATIVOS no sistema inteiro: 1
[06:08:20 final]   super-a@corrida.local        papéis=[super_admin] ativo=sim
[06:08:20 final]   super-b@corrida.local        papéis=[] ativo=sim
[06:08:20 final] >>> Invariante preservado: ainda existe super_admin ativo.
```

Confirmado depois, via `git status`, que nenhuma alteração ficou pendente em
`AssertLastActiveSuperAdminSurvives.php` — a troca para a versão antiga foi só no arquivo em
disco, nunca commitada.

## Como funciona a sincronização

`p1.php` abre uma transação **externa** (`DB::beginTransaction()`) antes de chamar a Action de
produção — o `DB::transaction()` de dentro dela vira `SAVEPOINT` (comportamento padrão do
Laravel quando já há transação aberta), então a operação roda exatamente como em produção e as
travas ficam retidas até o `COMMIT` de fora. Isso dá o ponto de pausa sem tocar em nenhuma
linha de código de produção.

A sincronização entre os dois processos usa arquivos de sinal em `.signals/` (ignorados pelo
git): `p1.php` sinaliza `p1-locked` assim que termina a operação (ainda sem commitar), e
`p2-*.php` espera esse sinal antes de tentar a sua própria operação. `p1.php` confirma em
`pg_stat_activity` (`wait_event_type = 'Lock'`) que o outro processo realmente encostou numa
trava antes de commitar — não é um `sleep` arbitrário torcendo para o timing dar certo.

## Arquivos

| Arquivo | Papel |
|---|---|
| `bootstrap.php` | Força `APP_ENV=testing`, sobe a aplicação real, confere o banco resolvido, funções auxiliares |
| `setup.php` | `migrate:fresh` + `RoleSeeder`, cria super-a e super-b |
| `p1.php` | Processo 1 — sempre remove o papel de super_admin de B |
| `p2-remove-papel.php` | Processo 2, cenário "papel" — remove o papel de A |
| `p2-desativa.php` | Processo 2, cenário "misto" — desativa A |
| `final.php` | Imprime o estado depois da corrida, antes da limpeza |
| `cleanup.php` | Apaga super-a e super-b — idempotente |
| `run.sh` | Orquestra os processos e garante a limpeza (`trap ... EXIT`) |
