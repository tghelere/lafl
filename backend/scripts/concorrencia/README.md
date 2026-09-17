# Reprodução da corrida do último super_admin

Scripts usados para reproduzir, com dois processos PHP de verdade contra o PostgreSQL, a
corrida que `App\Actions\Users\AssertLastActiveSuperAdminSurvives` existe para impedir: dois
super_admins removendo o papel (ou desativando) um ao outro ao mesmo tempo, de um jeito que
zeraria o total de super_admins ativos do sistema. Histórico completo, com a saída "antes" (o
bug) e "depois" (a correção), em `docs/relatorio-sessao-9.md`.

Não é teste automatizado — a suíte Pest usa uma conexão só, síncrona, e não reproduz
concorrência de verdade. Isto aqui é para conferir manualmente, depois de qualquer mudança em
`AssertLastActiveSuperAdminSurvives` ou nas Actions que a chamam, que a serialização continua
funcionando.

## ⚠️ Antes de rodar

- **Só roda com `APP_ENV=local`.** Os scripts recusam rodar em qualquer outro ambiente
  (`bootstrap.php` confere `app()->environment('local')`) — nunca rodar isto contra staging
  ou produção.
- **Cria dois usuários reais** (`super-a@corrida.local`, `super-b@corrida.local`) no banco de
  desenvolvimento configurado no seu `backend/.env`, com papel `super_admin`.
- **Desativa temporariamente** qualquer outro super_admin ativo que já exista no banco
  (inclusive o `dev@laranaliafranco.local` do `DevSuperAdminSeeder`) — sem isso, um terceiro
  super_admin sempre sobraria de pé e a corrida nunca chegaria a zero, mascarando o que se
  quer demonstrar. Só `deactivated_at` é tocado; papel, senha e e-mail continuam intactos, e a
  reativação é automática ao final (`run.sh` chama `cleanup.php` mesmo se a corrida falhar no
  meio ou for interrompida com Ctrl+C).
- **Tudo é revertido ao final**: os dois usuários de teste são apagados (junto com o registro
  de auditoria que geraram) e quem foi desativado temporariamente volta a ficar ativo.
- Se o processo for morto de um jeito que o `trap` do bash não capture (`kill -9`, queda de
  energia), rode a limpeza manualmente: `php cleanup.php`. É idempotente — pode rodar de novo
  sem risco, inclusive sem nada pendente.

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

`super_admins ATIVOS no sistema inteiro` conta o banco todo, não só os dois usuários de
teste — por isso ele bate com o total real do banco (`1`) só porque `setup.php` já isolou o
cenário desativando qualquer outro super_admin ativo.

**Se a proteção for quebrada de novo** (ex.: alguém reverte o mutex para travar as linhas de
`users` em vez da linha do papel), a saída muda para:

```
[P2] RESULTADO: operação PERMITIDA (sem exceção)

super_admins ATIVOS no sistema inteiro: 0
>>> INVARIANTE VIOLADO: o sistema ficou sem nenhum super_admin ativo.
```

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
| `bootstrap.php` | Sobe a aplicação real, confere `APP_ENV=local`, funções auxiliares |
| `setup.php` | Desativa temporariamente outros super_admins ativos, cria super-a e super-b |
| `p1.php` | Processo 1 — sempre remove o papel de super_admin de B |
| `p2-remove-papel.php` | Processo 2, cenário "papel" — remove o papel de A |
| `p2-desativa.php` | Processo 2, cenário "misto" — desativa A |
| `final.php` | Imprime o estado depois da corrida, antes da limpeza |
| `cleanup.php` | Reativa quem foi desativado, apaga super-a e super-b — idempotente |
| `run.sh` | Orquestra os processos e garante a limpeza (`trap ... EXIT`) |
