# 0002 — PostgreSQL em vez de MySQL/MariaDB

## Contexto

O projeto depende de recursos que vão além de um CRUD relacional simples: blind index com
busca por token (`char(64)[]` com índice GIN, ver `docs/protecao-de-dados.md`), filtro de
transparência por ano/tipo, e busca interna no site. A escolha do banco precisa suportar isso
nativamente, sem gambiarra.

## Decisão

PostgreSQL.

- **Array nativo + GIN** para `name_tokens` do blind index de nome — busca por token inteiro
  sem tabela de junção nem dependência de motor de busca externo.
- **JSONB** com índice GIN, útil para dados semiestruturados que podem aparecer em
  `settings` e em metadados de mídia, sem exigir uma tabela nova por variação de schema.
- **Índices parciais** (`WHERE deleted_at IS NULL`, `WHERE status = 'published'`), mais
  baratos que indexar a tabela inteira nas listagens paginadas que dominam o sistema.
- **`CHECK` constraints** espelhando os enums PHP (ver `docs/convencoes.md`), reforçando no
  banco a mesma regra que o enum aplica na aplicação.
- Maturidade do driver e do Eloquent com Postgres já é equivalente à do MySQL no ecossistema
  Laravel atual; não há custo de adoção real.

## Alternativas descartadas

- **MySQL/MariaDB.** Sem array nativo — o blind index por token exigiria tabela de junção
  (`name_index_tokens`) só para reproduzir o que o GIN faz de forma nativa. JSON existe mas
  com suporte a índice mais limitado. Nenhuma vantagem compensa essa perda para este domínio.
- **SQLite em produção.** Adequado para teste local e CI rápido (usado em dev), mas sem os
  recursos de índice acima e sem o isolamento de acesso concorrente que produção exige.

## Consequências

- CI roda os testes contra Postgres real, não SQLite, para ter paridade com produção (ver
  `README.md`) — SQLite continua servindo para execução local rápida.
- Toda migration que use recurso específico do Postgres (array, GIN, `CHECK`) fica
  implicitamente presa a este banco; não há meta de portabilidade multi-SGBD.
