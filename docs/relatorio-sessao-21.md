# Sessão 21 — SEO e os PDFs da transparência

Execução de `docs/tarefas/06-seo-e-pdfs-da-transparencia.md`.

O diagnóstico da tarefa era exato: o site existe, em boa parte, para que a prestação de contas
seja encontrada no Google, e **nada** do que faz um documento ser encontrado estava no lugar.
O `sitemap.xml` tinha uma URL — a home. Nenhuma página tinha canônico, `og:url` ou `og:image`.
E os PDFs saíam pela API como `attachment`, numa URL por uuid, que é o pior formato possível
para indexação: sem nome, em outro host, e entregue de um jeito que o buscador ignora.

## Ordem de execução

As etapas foram executadas na ordem **3 → 1 → 2 → 4**, não na ordem numérica. A etapa 1
(sitemap) precisa listar os documentos "pela URL nova da etapa 3" — a dependência é real, e
inverter as duas era a única forma de cada commit fechar uma etapa inteira, como
`docs/tarefas/README.md` exige. Cada etapa continua com o seu commit.

| Etapa | Commit |
|---|---|
| 3 — PDFs indexáveis | `1f1f7d1` |
| 1 — sitemap real | `cb7985b` |
| 2 — metadados e JSON-LD | `21ddc20` |
| 4 — página de documentos | `c1b172f` |

## Etapa 3 — o PDF ganhou endereço (commit `1f1f7d1`)

Antes: `GET {api}/api/v1/public/transparency-documents/{uuid}/download`, `attachment`.
Depois: `https://{site}/transparencia/documentos/2024/balanco-patrimonial-2024.pdf`, `inline`.

**O slug nasce na criação e nunca é recalculado.** Coluna `slug` UNIQUE, gerada de
`Str::slug(title)` por `App\Support\Transparency\DocumentSlug`, com `-2`, `-3`… em caso de
colisão (inclusive contra documento excluído por soft delete, que continua ocupando a linha e
o índice). Renomear o título depois muda o que se lê na página, não o endereço do arquivo — é
o ponto inteiro de persistir em vez de derivar a cada request.

**O ano é segmento da URL, não parte do slug.** Consequência desenhada: corrigir o ano de um
documento move o endereço canônico, e o endereço antigo passa a responder 301 para o novo.
Nenhum link publicado quebra, nem quando o metadado é corrigido.

**Quem serve é o Nitro; quem decide é a API.** A rota do site
(`server/routes/transparencia/documentos/[year]/[slug].ts`) é proxy puro para uma rota da API
que espelha o mesmo caminho segmento a segmento. 404, 301 de ano trocado e contagem de
download são decisão da API — regra 1 do `CLAUDE.md`. O PDF precisa estar no domínio do site
porque um arquivo em `api.dominio` conta como conteúdo de outro site na busca.

**O proxy repassa o `User-Agent` do visitante**, e é isso que dá sentido à contagem: a API não
soma mais acesso de robô conhecido (`App\Support\Http\KnownBots`, lista única e documentada).
O docblock da Action registra que a contagem é **aproximação** — User-Agent é texto que o
cliente escolhe, e um acesso não é uma pessoa.

Três detalhes que só apareceram medindo contra o build de produção:

- a rota precisou ser `[slug].ts` e não `[slug].get.ts`: com sufixo de método o Nitro casa só
  GET, e um `HEAD` (o que `curl -I` e vários rastreadores mandam antes de baixar) caía na 404
  do site;
- documento cuja linha existe mas cujo arquivo sumiu do disco responde 404, não 500;
- o 301 de ano trocado não soma download.

A migration preenche o slug dos documentos que já existem usando a mesma classe da Action (não
uma cópia da regra dentro dela), e foi conferida nos dois sentidos contra o banco de
desenvolvimento, que tinha 12 documentos reais: preencheu os 12, reverteu e preencheu de novo.

## Etapa 1 — o sitemap passou a descrever o site (commit `cb7985b`)

42 URLs no acervo de desenvolvimento, contra uma antes. Rotas fixas indexáveis + todas as
páginas publicadas do CMS + todos os PDFs publicados. **Todas as 42 foram requisitadas uma a
uma: todas respondem 200.** O XML foi validado com um parser de verdade, e não tem endereço
repetido.

Para isso nasceu `GET /api/v1/public/pages` — inventário mínimo (slug e `updated_at`),
paginado como toda listagem do projeto, sem conteúdo e sem título. Quem monta o sitemap
percorre as páginas até a última.

Três decisões que não são óbvias no código:

- **o sitemap saiu de `nitro.prerender.routes`.** Prerenderizado, ele congelaria o acervo no
  dia do build: publicar um balanço pelo painel exigiria novo deploy para o Google saber que
  ele existe. É o mesmo raciocínio que já tinha tirado dali as páginas do CMS e o `robots.txt`;
- **o slug antigo não entra.** `como-ajudar/doar` é servido em `/doar` e o endereço antigo
  responde 301 — listar o antigo seria pedir ao buscador que indexasse um redirect. O sitemap
  lista `/doar`;
- **API fora do ar responde 503, não um sitemap parcial.** Um arquivo truncado servido com 200
  declara que o acervo encolheu; com 503 o buscador tenta de novo e mantém o último bom.

## Etapa 2 — metadados num lugar só (commit `21ddc20`)

As 17 páginas passaram a chamar `usePageSeo` (`app/composables/usePageSeo.ts`): título,
descrição, canônico absoluto, `og:url`, `og:type`, `og:locale` `pt_BR`, `og:site_name`,
`og:image` (1200×630, cópia documentada da imagem padrão da marca) e cartão
`summary_large_image`. Nenhuma página monta tag à mão.

Página `noindex` sai só com título, descrição e a diretiva de robô. Canônico e cartão existem
para indexar e compartilhar, e formulário e confirmação de envio não podem ser nem uma coisa
nem outra — emitir canônico numa página que pede para não ser indexada é mandar dois sinais
que se contradizem.

**JSON-LD `NGO` na home**, com CNPJ como `identifier`, logo PNG, telefone, `foundingDate`
vindo da API (a data mora em `config/institution.php`; nenhuma página do site digita marco
institucional) e **os dois locais distintos** — Sede/CEI na Av. Anália Franco e Bazar na Rua
Rosa Siqueira. O contraturno aparece só na descrição, como programa em preparação: sem
`makesOffer`, sem `hasOfferCatalog`, sem `Service`. Há teste de ponta a ponta que falha se
alguém acrescentar qualquer um dos quatro.

### O defeito de deploy que esta etapa encontrou

`/o-que-fazemos` e `/politica-de-privacidade` **saíram do prerender**, e o motivo vale ser
lido inteiro porque não estava escrito em lugar nenhum:

As três tags novas são absolutas e saem de `NUXT_PUBLIC_SITE_URL`. O pacote de deploy é **um
só** para homologação e produção — `deploy.yml` gera o pacote sem nenhuma `NUXT_PUBLIC_*` no
ambiente, de propósito, e `docs/deploy.md` afirma que "nenhum dos dois frontends precisa ser
rebuildado por ambiente". Isso é verdade para SSR e **falso para rota prerenderizada**: o
prerender roda no build e grava o que houver ali. Medido: buildando com o `.env` local, as
três tags saíram gravadas com `localhost:3000` dentro do HTML estático; sem `.env`, sairiam
relativas — e `og:image` relativo nenhum rastreador de link resolve.

Conferido depois da correção subindo o **mesmo** `.output` com outro `NUXT_PUBLIC_SITE_URL`:
as três tags saem com o domínio certo, decidido em tempo de execução.

As cinco `/obrigado/*` continuam prerenderizadas — sendo `noindex`, não emitem nenhuma tag
dependente do ambiente.

## Etapa 4 — a página de documentos diz o que está mostrando

`<title>` e descrição refletem o recorte: "Balanços de 2024 — Transparência — Lar Anália
Franco", "Relatórios anuais de 2023 — …", "Documentos — …" sem filtro, com "— página 2"
quando for o caso.

O canônico carrega o filtro e descarta o resto: `page=1` não entra (é o mesmo endereço sem
parâmetro nenhum), `page=2` entra (é outra página de resultados de verdade — apontá-la para a
primeira esconderia do buscador tudo que não cabe na página 1), e **parâmetro que a API ignora
também não entra**: `?type=nao-existe` devolve o acervo inteiro, e sem essa regra cada valor
inventado viraria uma URL nova com conteúdo idêntico.

Os links de paginação já eram âncoras comuns com o filtro embutido na URL; ganharam
`rel="prev"`/`rel="next"`. Para que isso passasse a ser testável, o `E2eSeeder` cresceu de 16
para 21 documentos: a listagem pública mostra 20 por página, então antes a paginação do site
nunca aparecia na bateria.

## Verificação

| O quê | Resultado |
|---|---|
| Pest | 440 testes, verdes (eram 425 no início da sessão) |
| Pint e PHPStan | limpos (PHPStan com `--memory-limit=1G`, ver roadmap) |
| `npm run build` do site | ok |
| `npm run lint` e `npm run build` do painel | ok |
| Ponta a ponta | 91 testes, 21 novos; verdes (`c763b5a` corrigiu um teste de outra área que falhou) |
| `curl -I` do PDF | `200`, `application/pdf`, `inline; filename=balanco-patrimonial-2024.pdf`, `max-age=3600` |
| Sitemap | XML válido por parser, 42 `<loc>`, sem repetição, todas respondendo 200 |
| JSON-LD | validado contra o vocabulário schema.org oficial |

O JSON-LD merece uma linha a mais: o validador do Google (`validator.schema.org`) não aceitou
o envio por linha de comando, então a validação foi feita baixando o vocabulário oficial
(`schemaorg-current-https.jsonld`) e conferindo **recursivamente** que cada `@type` existe e
que cada propriedade se aplica ao tipo em que foi usada, seguindo a cadeia de herança. Zero
erros — `NGO` → `Organization` → `Thing`, e as doze propriedades usadas pertencem a essa
cadeia. É uma conferência mais estrita do que a do validador online.

### A bateria de ponta a ponta falhou duas vezes, e as duas foram investigadas

A primeira execução completa deu **89 passando, 2 falhando** — nenhuma das duas nos testes
novos, e nenhuma reproduzível.

**1. `politica-de-privacidade.spec.ts` — defeito de verdade, no teste.** Ele enviava uma
mensagem de contato e pegava "a primeira da listagem" (`per_page=1`) assumindo que seria a
dele. Recebeu a do teste anterior. A causa é mecânica e vale para o sistema inteiro:
`created_at` é gravado com precisão de **segundo** (`timestamps()` do Laravel cria
`timestamp(0)` no Postgres — conferido no `information_schema`), a listagem ordena **só** por
ele, e as duas mensagens nasceram com 0,9 s de diferença. Empate exato; a ordem que o Postgres
devolve num empate não é definida. O teste foi corrigido para procurar pela própria mensagem.
**A listagem continua sem desempate**, o que com `LIMIT/OFFSET` pode repetir ou pular linha
entre páginas — registrado no roadmap como pendência real, não silenciado.

**2. `usuarios.spec.ts`, troca da própria senha — não explicada.** O login da conta dedicada
mostrou "Não foi possível entrar. Tente novamente." (erro genérico do painel). Nada no log da
API, e nenhuma das explicações prováveis se sustenta: o limite de login é 5/min por
IP+e-mail e essa conta faz um login só na bateria inteira. O trace que teria a resposta HTTP
foi perdido quando a reexecução parcial limpou `test-results/`. Nas execuções seguintes —
isolada e completa — passou. **Fica registrado como falha observada uma vez e não
reproduzida**, não como resolvida. Nada da sessão toca autenticação, sessão ou usuários.

## Decisões tomadas sem consultar

1. **Ordem 3 → 1 → 2 → 4**, pela dependência explicada acima.
2. **Slug sem o ano** (o ano é segmento da URL), com 301 quando o ano muda.
3. **Unicidade do slug é global**, não por ano: a URL é resolvida só pelo slug, e o ano é
   conferido depois. Simplifica o 301 e evita dois documentos disputarem o mesmo endereço.
4. **`User-Agent` ausente conta como robô.** Navegador sempre manda o cabeçalho; o que chega
   sem ele é script. É a escolha conservadora para um número que a instituição vai ler como
   "quantas pessoas baixaram".
5. **Assinatura genérica de robô exige delimitador** (`bot/`, `bot;`, `bot)`): "bot" solto
   casaria com "CUBOT", marca de celular que aparece em User-Agent de navegador real. Tem
   teste.
6. **`config('forms.site_base_url')` reaproveitado** para montar a URL canônica do PDF, em vez
   de criar uma variável nova. Mesmo precedente do `admin_base_url`, que o link de definição
   de senha já usa fora do contexto de formulário. O comentário do arquivo registra o segundo
   consumidor.
7. **A imagem Open Graph foi copiada** de `shared/brand/` para `frontend-site/public/og/`, em
   vez de importada pelo Vite. Mesmo critério já documentado para favicon e logo de e-mail:
   é consumida fora do grafo de módulos JS, por URL absoluta, por um rastreador. A tabela de
   cópias manuais entrou no `LEIA-ME.md` da marca.
8. **Página `noindex` não emite canônico nem Open Graph** (a tarefa pedia "todas as páginas";
   a leitura restritiva é não mandar sinal contraditório).

Fechamento: `ac72df4` (documentação e as duas ADRs). Conferência visual feita no navegador
(Playwright MCP, Firefox): a página de documentos filtrada renderiza com o título certo, e a
URL do PDF **abre no leitor do próprio navegador**, com `balanco-patrimonial-2024.pdf` no topo
— que é o comportamento que o `curl -I` sozinho não mostra.

## O que ficou de fora, e por quê

- **`posts` no sitemap** — a entidade não existe. Anotado no roadmap, junto do item marcado
  como feito.
- **Marcação por página (`WebPage`, `BreadcrumbList`)** — a tarefa pediu `NGO` na home, e só.
- **Desempate na ordenação das seis listagens** — é correção de outra área, registrada no
  roadmap com o diagnóstico completo.
- **Nada foi publicado em homologação.** A tarefa não pede deploy, e um push na `main` publica
  sozinho.

## O que precisa de conferência humana

1. **A imagem de compartilhamento.** Abrir `https://{site}/og/og-padrao.png` e, depois do
   deploy, colar uma URL do site no WhatsApp para ver o cartão montado. É o único artefato
   desta sessão cuja aparência nenhuma suíte atesta.
2. **Os títulos da página de documentos, em português de gente.** "Prestações de contas do
   convênio de 2024 — Transparência — Lar Anália Franco" é correto e comprido; se a
   instituição preferir outra forma, o plural de cada tipo está num lugar só
   (`typeOptions`, em `app/pages/transparencia/documentos.vue`).
3. **O texto do JSON-LD sobre o contraturno**, que é o campo onde uma frase errada vira
   promessa pública de vaga.
4. **Depois do deploy:** enviar o `sitemap.xml` no Google Search Console e conferir um PDF com
   a ferramenta de inspeção de URL. Só ali se confirma que o arquivo é indexável de verdade.
