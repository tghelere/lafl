# Bateria de ponta a ponta (Playwright)

Testes que exercitam a pilha inteira — backend Laravel, painel administrativo e site público —
**em modo de produção**, contra o banco de verdade e num navegador de verdade.

Por que começar por aqui em vez de testes de componente: todos os defeitos encontrados no
painel até agora foram de integração — busca com `LIKE` sensível a maiúsculas no Postgres,
comportamento do editor de texto rico ao reserializar HTML, colisão de CSS, reuso de instância
de componente pelo vue-router entre criar e editar. Nenhum deles apareceria num teste de
componente com a API simulada.

## Rodar

```bash
cd e2e
npm install          # instala o Playwright e baixa o Firefox
npm run test:e2e
```

É só isso. O `webServer` do Playwright sobe os três serviços sozinho:

| Serviço | Porta | Como sobe |
|---|---|---|
| API (Laravel) | 8100 | `php artisan serve` com `APP_ENV=e2e` |
| Painel (Vue) | 5175 | `npm run build` + `vite preview` |
| Site (Nuxt) | 3100 | `npm run build` + `node .output/server/index.mjs` |

Portas próprias, distintas das de desenvolvimento (8000/5173/3000): dá para rodar a bateria com
o ambiente de desenvolvimento no ar na mesma máquina.

Pré-requisito único: o Postgres e o Redis do `docker-compose.yml` no ar
(`docker compose up -d postgres redis`). O banco de e2e é criado sozinho se ainda não existir.

Outros comandos úteis:

```bash
npm run test:e2e -- --headed          # ver o navegador
npm run test:e2e -- --debug           # inspetor do Playwright
npm run test:e2e -- tests/paginas     # só uma parte
npm run report                         # abrir o relatório HTML da última execução
```

## Banco

A bateria usa um **terceiro** banco, `lar_analia_franco_e2e`, separado do de desenvolvimento e
do da suíte Pest (ver `CLAUDE.md`, "Os três bancos"). O estado inicial é recriado a cada
execução pelo global setup, via `php artisan e2e:prepare`, que:

1. recusa rodar se o ambiente não for `e2e` **ou** se o banco resolvido não for
   `lar_analia_franco_e2e` — a mesma guarda dupla de `backend/scripts/concorrencia/`, porque o
   comando roda `migrate:fresh`;
2. cria o banco se ele ainda não existir;
3. roda `migrate:fresh`, o `RoleSeeder` e o `E2eSeeder`;
4. limpa o cache (índice do Redis próprio da bateria), o que zera o limitador de tentativas de
   login entre execuções.

As contas de teste vivem em `backend/database/seeders/E2eSeeder.php`, espelhadas em
`support/users.ts`. Todas usam a mesma senha (`senha-de-teste-e2e`) e o domínio reservado
`@e2e.local`; o seeder só roda no ambiente `e2e`, nunca em outro.

## Como os testes são escritos

- **Nada de `waitForTimeout`.** Só asserções que esperam sozinhas (`expect(...).toBeVisible()`,
  `expect.poll`). Espera fixa esconde corrida em vez de resolver.
- **Seletores por papel e por texto visível** (`getByRole`, `getByLabel`). `data-testid` só
  onde não houver alternativa — hoje não há nenhum. Onde o mesmo nome aparece duas vezes na
  tela (menu lateral e trilha de navegação), o localizador é delimitado pela região, não
  trocado por uma classe de CSS.
- **Cada teste cria os próprios dados** quando altera estado, e apaga o que criou. Nenhum teste
  depende do que outro deixou para trás nem da ordem de execução.
- **Login uma vez só por papel**, no global setup, reaproveitado via `storageState`. Só os
  testes que são *sobre* login passam pela tela de login, e com contas exclusivas — o limite é
  de 5 tentativas por minuto por IP+e-mail.
- **A asserção final é do lado do servidor** onde isso importa: o conteúdo de uma página é
  conferido pelo endpoint administrativo, e o resultado no site público pelo HTML que o
  servidor entregou. O que a tela mostra já passou pelo editor; comparar só a tela compararia a
  normalização do editor com ela mesma.

### Dois contextos de navegador no mesmo teste

Alguns testes precisam de duas pessoas usando o sistema ao mesmo tempo. Use sempre
`pageInCleanContext(browser)` (em `support/admin.ts`) para a segunda: dentro de um teste, o
`browser.newContext()` do `@playwright/test` **herda as opções do `test.use()`, inclusive o
`storageState`**. Sem um `storageState` vazio explícito, a segunda pessoa nasce com o cookie da
primeira e, ao fazer login, o Laravel migra a sessão e derruba a sessão do primeiro contexto —
com o sintoma aparecendo bem longe da causa.

### Forma canônica do editor

Duas páginas do `E2eSeeder` (`e2e-pagina-com-botao` e `e2e-pagina-com-link-externo`) guardam o
conteúdo na forma exata que o editor do painel produz. O teste de ida e volta abre cada uma,
salva sem alterar nada e exige que o `content` gravado não mude — é o que impede uma reescrita
silenciosa de páginas publicadas quando alguém abre e salva por reflexo.

Se o editor ou a allowlist do backend mudarem, esse teste fica vermelho de propósito. Para
regerar as constantes:

```bash
npm run test:e2e -- tests/paginas/editor.spec.ts   # vai falhar, mostrando o diff
cd ../backend && APP_ENV=e2e php artisan tinker --execute='foreach (App\Models\Page::whereIn("slug", ["e2e-pagina-com-botao","e2e-pagina-com-link-externo"])->get() as $p) { echo $p->slug."\n".$p->content."\n\n"; }'
```

Copie a saída para `CANONICAL_*` em `E2eSeeder.php` — e, antes de copiar, confira se a
diferença é normalização inofensiva ou perda de verdade (`class="btn"`, `target`, `rel`).

## Estrutura

```
playwright.config.ts   pilha sob teste, artefatos, repetições
global-setup.ts        prepara o banco e captura um storageState por papel
support/               endereços, contas, cliente da API, atalhos de tela e de editor
reporters/             relatório de testes instáveis
tests/auth/            login e o que cada papel enxerga
tests/usuarios/        criação, link de senha, desativação, troca da própria senha
tests/transparencia/   upload, publicação, filtro
tests/paginas/         editor, publicação no site, sanitização, busca
```

## Falha no CI

O job `e2e` publica `playwright-report` e `test-results` como artefato quando falha: trace,
captura de tela e vídeo de cada teste que quebrou. Para abrir um trace baixado:

```bash
npx playwright show-trace caminho/para/trace.zip
```

Repetição é no máximo uma, e só no CI. Teste que falhou e passou na repetição sai marcado como
**instável** num bloco próprio no fim da saída do job (ver `reporters/flaky-reporter.ts`) — não
é sucesso, é defeito de tempo esperando conserto.
