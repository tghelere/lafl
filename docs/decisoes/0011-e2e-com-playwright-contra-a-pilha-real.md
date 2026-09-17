# 0011 — Testes de ponta a ponta com Playwright contra a pilha real, em banco próprio

## Contexto

O painel administrativo chegou ao fim da sessão 9 com sete telas, seis fluxos de escrita e
**nenhum teste automatizado**. O backend tem 336 testes Pest; o painel tem zero. A pergunta
não era "testar ou não", era "começar por onde".

O histórico de defeitos do próprio projeto responde. Todos os quatro encontrados no painel até
aqui foram de integração:

| Defeito | Onde estava | Por que um teste de unidade não pegaria |
|---|---|---|
| Busca não achava "Bazar Beneficente" com `bazar` | `LIKE` sensível a maiúsculas no PostgreSQL | a suíte rodava em SQLite, que ignora caixa; nenhuma asserção sobre o componente estava errada |
| Conteúdo de página mudava ao abrir e salvar | reserialização de HTML pelo Tiptap | só aparece com o editor real montado, sobre o HTML real |
| Colisão de CSS entre componentes | folhas de estilo carregadas juntas | só aparece com a aplicação inteira montada |
| Tela de edição mostrando dados do registro anterior | vue-router reaproveitando a instância do componente ao trocar só o parâmetro | só aparece com o roteador real navegando entre rotas |

Nenhum desses quatro apareceria num teste de componente com a API simulada. Três deles
dependem de coisas que um teste de componente substitui por mentira: o banco, o navegador, o
roteador.

`CLAUDE.md` também é relevante aqui: a regra 1 diz que **o front não tem regra de negócio** e
que a permissão no front "só esconde botão; quem barra é a Policy". Um teste de componente com
API simulada verificaria o esconder-botão contra um mapa de acesso inventado pelo próprio
teste. O que interessa verificar é o mapa que a Policy calcula de verdade.

## Decisão

**Começar a cobertura do painel por uma bateria de ponta a ponta (Playwright, Firefox) contra
a pilha real em modo de produção**, com banco próprio.

Quatro escolhas dentro dessa:

### Pilha real, em modo de produção, nunca `dev`

A bateria sobe o backend Laravel, o **build** do painel servido por `vite preview` e o site
Nuxt em SSR (`nuxt build` + `node .output/server/index.mjs`). Nada de `npm run dev`: o modo de
desenvolvimento tem transformação de módulo, HMR e sourcemaps que não existem no ar, e é o
artefato de produção que precisa ser testado.

O site público entra na bateria junto com o painel porque metade dos fluxos do painel só tem
sentido completo quando se vê o outro lado: publicar um documento e ele aparecer, despublicar
e ele sumir, editar um parágrafo e a mudança chegar ao site, salvar HTML hostil e ele **não**
chegar.

### Banco próprio, o terceiro

`lar_analia_franco_e2e`, ao lado de `lar_analia_franco` (desenvolvimento) e
`lar_analia_franco_test` (Pest e os scripts de concorrência). Todos PostgreSQL 16, no mesmo
servidor do `docker-compose.yml`.

Isso **não** reabre a decisão 0002 nem a regra de "nunca um segundo banco": aquela regra é
sobre o **motor** — nunca SQLite, porque dois motores dão dois comportamentos e foi assim que
o bug do `LIKE` passou despercebido. Aqui o motor é um só; o que se multiplica é a base, e o
motivo é operacional: dois dos três processos rodam `migrate:fresh`, e apagar o banco de
desenvolvimento por engano é irreversível.

A alternativa de reaproveitar `lar_analia_franco_test` foi descartada porque tornaria
impossível rodar Pest e a bateria ao mesmo tempo — coisa que se quer fazer enquanto se
trabalha.

O único caminho que recria esse banco é `php artisan e2e:prepare`, com **guarda dupla**: recusa
rodar se o ambiente não for `e2e` **ou** se o banco resolvido não for `lar_analia_franco_e2e`.
É o mesmo desenho de `backend/scripts/concorrencia/bootstrap.php`, pelo mesmo motivo — a
checagem de ambiente cobre o engano comum, a do nome do banco é a que impede o desastre mesmo
se o arquivo de ambiente for editado um dia.

### Um navegador só: Firefox

O `README` já recomenda Firefox para conferência visual nesta máquina (extensões de privacidade
no Chrome quebram o cliente de dev do Vite). Uma bateria pequena rodando bem em um navegador
de verdade vale mais que a mesma bateria repetida em três — acrescentar Chromium é uma linha
em `projects` se um dia fizer falta.

### Um worker, sem paralelismo

O backend sob teste é o servidor embutido do PHP, e os testes compartilham um banco.
Paralelizar trocaria tempo de relógio por falha intermitente, que é o defeito que mais destrói
a confiança numa bateria de e2e. A bateria inteira leva ~44 s localmente, build incluído.

## Alternativas descartadas

- **Vitest + Vue Test Utils com a API simulada.** Rápido e barato, mas substitui por mentira
  exatamente as três coisas onde os defeitos apareceram (banco, navegador, roteador). Não está
  descartado para sempre: faz sentido quando houver lógica de apresentação com casos
  suficientes para valer isolamento — hoje não há, porque a regra de negócio mora na API.
- **Cypress.** Equivalente em capacidade. Playwright foi escolhido por rodar Firefox nativamente,
  ter `storageState` de primeira classe (é o que mantém a bateria longe do limite de tentativas
  de login) e não exigir serviço externo para ver trace de falha.
- **Testes de e2e contra o ambiente de desenvolvimento** (`npm run dev`, banco de
  desenvolvimento). Descartado por dois motivos independentes: testaria um artefato que nunca
  vai ao ar, e a bateria roda `migrate:fresh`.
- **Reaproveitar o banco da suíte Pest.** Ver acima: impediria rodar as duas coisas ao mesmo
  tempo, e os scripts de concorrência já disputam aquele banco.
- **Rodar a bateria só no CI.** Uma bateria que não roda na máquina de quem escreve o código
  vira ruído: a pessoa descobre a quebra vinte minutos depois, sem o navegador na frente. O
  comando local é um só (`npm run test:e2e`), e o CI roda exatamente ele.
- **Testar sem o site público, só o painel.** Metade das asserções que importam é sobre o que
  chega (ou não chega) ao site. Sem ele, "publiquei" viraria "o botão mudou de rótulo".

## Consequências

- **Toda funcionalidade nova do painel ganha um teste de ponta a ponta do fluxo principal** —
  registrado em `CLAUDE.md` como convenção. Um fluxo por funcionalidade, não cobertura
  exaustiva.
- O CI ganha um job de ~3 minutos e passa a ser o caminho crítico do pipeline (antes ~1 min).
  O primeiro lugar a otimizar, se incomodar, é o cache de `composer` e `npm`.
- Existe um terceiro arquivo de ambiente commitado (`backend/.env.e2e`), com exceção própria no
  `.gitignore`. Mesma condição do `.env.testing`: só valores fictícios, nunca chave real.
- Duas páginas sintéticas (`e2e-pagina-com-botao`, `e2e-pagina-com-link-externo`) guardam
  conteúdo na forma canônica do editor. Se o editor ou a allowlist do `ContentSanitizer`
  (decisão 0010) mudarem, o teste de ida e volta fica vermelho de propósito — é o alarme de que
  o conteúdo já publicado será reescrito na próxima vez que alguém salvar.
- A bateria depende do Postgres e do Redis do `docker-compose.yml` estarem no ar. Não é
  dependência nova: o desenvolvimento já exige os dois.
