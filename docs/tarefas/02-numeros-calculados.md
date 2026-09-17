> **Modelo recomendado: Opus**

# 02 — Números calculados em vez de texto fixo

Leia `docs/tarefas/README.md`. Rode depois da tarefa 01.

## Problema

Contagens e idades estão escritas à mão e envelhecem: "cerca de 70 documentos" (o acervo real
muda a cada upload ou exclusão), "58 anos" do bazar fixo na home, e qualquer "instituição com X
anos". O pedido: **toda contagem e toda idade é calculada**, nunca digitada.

## Desenho pedido

### Fonte única dos fatos

`config/institution.php` com as datas confirmadas (ver `docs/contexto.md`):

| Chave | Data | Precisão |
|---|---|---|
| fundação da associação | 12/07/1953 | dia |
| início da obra da sede | 18/04/1957 | dia |
| inauguração da sede | 15/11/1963 | dia |
| início do bazar | 1968 | só ano |
| criação do CEI | 2002 | só ano |

Uma classe de serviço (ex.: `App\Services\InstitutionalFacts`) calcula:

- **idades** com data completa quando houver (aniversário respeitado — em 11/07 ainda não
  completou), e por diferença de ano quando só houver o ano (registrar essa imprecisão no
  docblock); fuso `America/Sao_Paulo`;
- **quantidade de documentos de transparência publicados** (mesmo critério da listagem
  pública — despublicado não conta).

Os valores saem **já formatados em português, com o substantivo e o plural corretos**:
"1 ano"/"73 anos", "1 documento"/"71 documentos", milhar com ponto. Assim o texto nunca vira
"71 documento" nem "1 documentos".

### Marcadores no conteúdo do CMS

O conteúdo das páginas é editado pelo pessoal da instituição, então o cálculo precisa funcionar
dentro do texto. Marcadores em texto puro, que atravessam o editor Tiptap e o `ContentSanitizer`
sem alteração:

`{{idade_associacao}}`, `{{idade_sede}}`, `{{idade_bazar}}`, `{{idade_cei}}`,
`{{documentos_transparencia}}` (nomes finais a seu critério, em português, curtos).

Regras obrigatórias:

1. **Resolvidos só na leitura pública.** O endpoint administrativo devolve o marcador cru.
   Se o painel recebesse o valor resolvido, o primeiro salvamento gravaria "73 anos" fixo no
   banco e o cálculo morreria em silêncio — cobrir isso com teste.
2. **Resolvidos depois do cache** de `ResolvePublicPageBySlug` (ou com invalidação correta):
   publicar, despublicar ou excluir um documento reflete na contagem já na requisição seguinte.
   Lembrar da armadilha de `cache.serializable_classes` registrada no roadmap.
3. **Marcador desconhecido é recusado ao salvar** (422, mensagem da API em português listando os
   válidos). Nunca chega ao site um `{{coisa}}` sem resolver.
4. **Painel mostra os marcadores disponíveis** no editor de páginas, com o valor atual de cada
   um, vindos da API (zero regra de negócio no front).

### Páginas fixas em `.vue`

Endpoint público (ex.: `GET /api/v1/public/institution-facts`) com os valores formatados e
crus. A home usa esse endpoint na linha de registro: fundação 1953 com a idade calculada, bazar
desde 1968 com a idade calculada (substitui o `date="58 anos"` fixo).

**A home sai de `nitro.prerender.routes`.** Prerenderizada, ela congelaria os números no dia do
build — exatamente o problema que esta tarefa resolve. Atualizar o comentário do
`nuxt.config.ts`.

## Etapas

1. Serviço, config e testes Pest (idade antes/depois do aniversário com tempo congelado, ano
   só, plural de 0/1/2, contagem acompanhando publicar/despublicar/excluir).
2. Marcadores: resolução na leitura pública, recusa de marcador desconhecido, admin devolvendo
   cru, testes.
3. Endpoint público de fatos + home em SSR + linha de registro calculada.
4. Painel: lista de marcadores com valor atual no editor de páginas.
5. Conteúdo: `transparencia` passa a dizer "hoje com {{documentos_transparencia}}" (sem "cerca
   de"); onde o seeder citar idade ou tempo de existência, usar marcador. Varrer o repositório
   por números de anos e contagens fixos (`grep -rnE "[0-9]+ (anos|documentos)"`) e converter o
   que for idade ou contagem. Valores de documento (repasse de R$ 2.819.892,84, 15 turmas do
   plano 2026) não são cálculo — ficam como estão.
6. E2e do fluxo principal: inserir um marcador no editor, salvar, ver o número no site; subir e
   publicar um documento e ver a contagem de `/transparencia` aumentar.

## Verificação

Pest, Pint, Larastan, builds dos dois frontends, bateria e2e completa, conferência no Firefox da
home, de `/transparencia` e do editor de páginas.
