# 0008 — Papéis modelados por tipo de dado, não por cargo

## Contexto

O enum de papéis herdado do desenho de acolhimento (`social_work`, `psychology`, `pedagogy`,
`coordination`, `administrative`, `content_editor`) descrevia cargos de uma operação que não
existe mais (`docs/contexto.md`: o acolhimento institucional foi encerrado em 2022). Era
preciso um novo desenho de papéis para o sistema atual — creche, contraturno e bazar — sem
repetir o mesmo acoplamento entre estrutura de permissão e organograma.

## Decisão

Papéis definidos pelo **tipo de dado que tocam**, não pelo cargo de quem os ocupa
(`docs/estrutura-site.md` §4.4, `docs/dominio.md`):

```php
enum Role: string
{
    case SuperAdmin = 'super_admin';
    case Direcao = 'direcao';
    case Atendimento = 'atendimento';
    case Bazar = 'bazar';
    case Comunicacao = 'comunicacao';
}
```

Três níveis de dado no sistema — conteúdo público, formulário recebido (dado de adulto),
cadastro de assistido (dado de menor, Fase 2) — e cada papel é definido pela combinação de
níveis que pode tocar, não pelo nome de uma função dentro da instituição.

Motivação central: organograma muda: um cargo pode passar a acumular funções, ser
terceirizado, ou ser dividido, mas a classificação do dado (`docs/protecao-de-dados.md`) não
muda com isso. Acoplar permissão a cargo obriga a redesenhar o enum toda vez que a instituição
reorganiza uma função — como já aconteceu uma vez neste projeto.

Consequência mais concreta da decisão: `comunicacao` — o papel mais provável de ser
terceirizado, por lidar só com conteúdo público — **não enxerga nenhum formulário recebido**,
nem mesmo em modo leitura. Isso é modelado como propriedade do papel, não como exceção de
Policy, e exige teste Pest explícito provando a ausência de acesso (`CLAUDE.md`,
`docs/estrutura-site.md` §4.4).

## Alternativas descartadas

- **Manter papéis por cargo**, redesenhando o enum anterior para os cargos atuais
  (`secretaria`, `bazar`, `comunicacao_social`, etc.). Reproduziria o mesmo problema que
  acabou de custar um redesenho completo do enum: o próximo rearranjo de organograma da
  instituição forçaria outra migration de papéis, além de tornar o mapeamento
  papel → dado acessível implícito e disperso pelo código, em vez de explícito na própria
  definição do papel.
- **Um único papel administrativo genérico com permissões granulares por checkbox no
  cadastro de usuário.** Mais flexível em tese, mas move a decisão de "quem pode ver dado de
  formulário" para configuração ad hoc por usuário, sem uma classificação central — o oposto
  do princípio de que autorização decide por Policy, nunca por checagem inline ou config solta
  (`docs/convencoes.md`).

## Consequências

- Dividir um papel amplo demais no futuro (ex.: separar `atendimento` em `secretaria` e
  `contato`) é simples; juntar dois papéis depois é que dá trabalho — por isso a nota de
  desenho em `docs/estrutura-site.md` §4.4 recomenda começar granular apenas quando a
  necessidade aparecer, não antecipadamente.
- `secretaria_cei` entra como papel novo quando a Fase 2 (cadastro de assistidos) for
  implementada, para separar quem vê dado de criança de quem vê formulário de adulto — ainda
  não existe porque a entidade que justificaria sua existência ainda não existe.
- `super_admin` é a única conta que gerencia usuários e papéis; `direcao` enxerga tudo
  operacional mas não concede acesso — conceder acesso é sempre ato deliberado, nunca
  decorrência de outro papel amplo.
- O enum de papéis é agrupamento de conveniência para a interface e para seeders; a fonte da
  verdade de autorização continua sendo a Policy de cada recurso, nunca uma checagem inline de
  papel (`docs/convencoes.md`).
