# Fotos do site — pipeline implementado

Estado atual (pós sessão de imagens/WhatsApp/mapa). Ver
`frontend-site/app/components/AppFoto.vue` e `frontend-site/app/data/fotos.ts`.

## Onde as imagens ficam

`frontend-site/public/fotos/{secao}/{slug}-{largura}.{webp,jpg}`.

Ficam em `public/`, não em `assets/`, de propósito: o caminho é montado em tempo de
execução a partir do slug e da largura (`AppFoto.vue`), e o Vite não analisa esse tipo de
caminho estaticamente para gerar variante — colocar em `assets/` não geraria nada, só
quebraria silenciosamente.

Cada foto tem `.webp` e `.jpg` nas mesmas larguras (fallback, não formato "melhor" — todo
navegador atual entende webp; o jpg existe para o `<img>` dentro do `<picture>`, exigido
pelo próprio elemento). A maior largura disponível é o limite real do arquivo original —
nunca amplie além disso.

EXIF já removido e orientação já gravada no pixel na preparação do arquivo. Nada disso
acontece em tempo de execução — não há otimizador de imagem nem IPX configurado neste
projeto, de propósito (ver decisão da sessão de imagens).

## Fonte de verdade: `app/data/fotos.ts`

Um objeto tipado, indexado por slug:

```ts
export type Foto = {
  secao: Secao
  alt: string
  largura: number  // do original, em px
  altura: number    // do original, em px
  larguras: number[] // larguras geradas, sem a unidade "w"
}
```

`secao` é união literal das seis seções (`home`, `historia`, `educacao-infantil`, `bazar`,
`quem-somos`, `transparencia`), não `string` — evita slug de seção errado passar
despercebido. `alt` é obrigatório no tipo.

## Como adicionar uma foto nova

Duas mudanças, na mesma alteração — uma sem a outra deixa o site quebrado ou a foto
inacessível:

1. Gere os arquivos em `public/fotos/{secao}/{slug}-{largura}.{webp,jpg}`, nas larguras que
   fizerem sentido para o tamanho do original (ver tabela abaixo). Remova o EXIF e grave a
   orientação no pixel antes de exportar — isso não é feito pelo pipeline do site.
2. Adicione a entrada em `app/data/fotos.ts`, com `secao`, `alt`, `largura`/`altura` do
   original e `larguras` exatamente iguais às que você gerou no passo 1.

Quais larguras gerar — a escala do projeto é 1920, 1280, 960, 640, 400; gere só até o
limite real do original (não amplie):

| Menor lado do original | Larguras a gerar |
|---|---|
| ≥ 1920px | 1920, 1280, 960, 640, 400 |
| ≥ 1280px | 1280, 960, 640, 400 |
| ≥ 960px | 960, 640, 400 |
| ≥ 640px | 640, 400 |
| < 640px | 400 (ou o que couber) |

Nunca invente uma largura maior que o original nem adicione um número em `larguras` sem o
arquivo correspondente existir — `AppFoto.vue` monta `srcset` diretamente dessa lista; um
número sem arquivo vira link quebrado.

## Como usar numa página

Nenhuma página referencia caminho de imagem diretamente — sempre pelo componente:

```vue
<AppFoto slug="fachada-sede" contexto="cheia" prioridade />
```

- `slug`: chave de `app/data/fotos.ts`. Slug inexistente lança erro em tempo de setup — como
  a maior parte das páginas é prerenderizada (ver `nuxt.config.ts`), isso quebra `nuxt
  generate`/`nuxt build`, não falha em silêncio no navegador de quem visita.
- `contexto`: `cheia` | `metade` | `terco` | `quarto` — fração aproximada da viewport que a
  imagem ocupa, define o atributo `sizes`.
- `prioridade`: só na primeira imagem visível de cada página (`fetchpriority="high"`, sem
  `loading="lazy"`). Todas as outras carregam `loading="lazy"` por padrão.

O componente emite `<picture>` com `<source type="image/webp">` e `<img>` de fallback em
jpg, ambos com `srcset` só das larguras que existem para aquela foto (nunca uma largura
inventada) e `width`/`height` explícitos na proporção do original, para não deslocar layout
durante o carregamento.

## Cache

`/fotos/**` serve com `Cache-Control: public, max-age=2592000` (30 dias), sem `immutable`
(ver `routeRules` em `nuxt.config.ts`) — os nomes de arquivo não têm hash de conteúdo, então
trocar uma foto mantendo o mesmo nome precisa continuar visível dentro da janela de cache.

## Catálogo — slug e texto alternativo

O texto alternativo abaixo já está escrito e em uso em `app/data/fotos.ts`. Não reescreva
sem necessidade — se mudar aqui, mude lá também, e vice-versa.

### Home — `home/`

**fachada-sede** — 5184×3888, larguras 1920, 1280, 960, 640, 400
> Fachada da sede do Lar Anália Franco, com o letreiro da instituição sobre a entrada principal

**fachada-sede-atual** — 903×1600, larguras 640, 400
> Entrada da sede do Lar Anália Franco vista do gramado da frente

### Nossa história — `historia/`

**fachada-antes** — 1024×768, larguras 960, 640, 400
> Fachada da sede antes da reforma, com pintura desgastada em cinza e azul

**fachada-depois** — 1024×768, larguras 960, 640, 400
> Fachada da sede depois da reforma, vista do jardim da entrada

**patio-antes** — 1024×768, larguras 960, 640, 400
> Pátio interno antes da reforma, com parede de tinta descascada

**patio-depois** — 5184×3888, larguras 1920, 1280, 960, 640, 400
> Pátio interno depois da reforma, com parede pintada de branco e faixa geométrica colorida

**placa-inauguracao** — 903×1600, larguras 640, 400
> Placa de bronze na parede da sede: obra iniciada em 18 de abril de 1957 e inaugurada em 15 de novembro de 1963

**recanto-da-amizade** — 1196×880, larguras 960, 640, 400
> Entrada do Recanto da Amizade, área arborizada com mesas no terreno da instituição

**recanto-vista** — 5184×3888, larguras 1920, 1280, 960, 640, 400
> Área arborizada do Recanto da Amizade, com mangueiras e mesas de concreto

**recepcao** — 1024×768, larguras 960, 640, 400
> Recepção da sede, com parede listrada em laranja e amarelo

### Educação infantil — `educacao-infantil/`

**horta-kids** — 1600×1200, larguras 1280, 960, 640, 400
> Horta Kids: canteiros feitos com pneus coloridos diante de um muro com desenho de crianças plantando

**horta-kids-vista** — 1600×1200, larguras 1280, 960, 640, 400
> Vista ampla da Horta Kids, com canteiros de pneu no gramado e painéis de paletes no muro

**sala-multiuso-brinquedos** — 1280×720, larguras 1280, 960, 640, 400
> Brinquedoteca da sala multiuso, com fantasias, carrinhos e material de faz de conta

**sala-multiuso-conto** — 720×1280, larguras 640, 400
> Sala multiuso com tapete de tatame colorido, cantinho da Hora do Conto e Casa do Faz de Conta

**sala-multiuso-imaginacao** — 1280×720, larguras 1280, 960, 640, 400
> Cantinho Mundo da Imaginação, com brinquedos, casa de bonecas e tatame colorido

### Bazar beneficente — `bazar/`

**bazar-deposito** — 903×1600, larguras 640, 400
> Depósito de móveis do bazar, com mesas, cadeiras e armários organizados

**bazar-entrada** — 903×1600, larguras 640, 400
> Entrada do Bazar Beneficente, com toldo azul e carrinhos de compra ao lado

**bazar-leitura** — 903×1600, larguras 640, 400
> Setor de livros do bazar, com estantes cheias e bancos para leitura

**bazar-moveis** — 899×1599, larguras 640, 400
> Setor de móveis do bazar, com sofás, poltronas e utensílios à venda

**bazar-placa** — 903×1600, larguras 640, 400
> Placa externa do Bazar Beneficente do Lar Anália Franco, com telefone e endereço

**bazar-salao** — 899×1599, larguras 640, 400
> Salão do bazar com araras de roupas, manequins e chapéus expostos

### Quem somos — `quem-somos/`

**equipe-formacao** — 1600×1200, larguras 1280, 960, 640, 400
> Equipe do Lar Anália Franco reunida em sessão de formação no auditório

### Transparência — `transparencia/`

**almoxarifado-alimentos** — 768×1024, larguras 640, 400
> Almoxarifado de alimentos, com prateleiras organizadas de arroz, feijão e mantimentos

**almoxarifado-limpeza** — 768×1024, larguras 640, 400
> Almoxarifado de materiais de limpeza e higiene, com prateleiras organizadas

### Contato — `contato/`

**mapa-enderecos** — 1x 1088×450 (zoom 18), 2x 2176×900 (zoom 19), mesmo enquadramento

Única exceção do catálogo que não é foto, e a única que não passa por `AppFoto` — passa por
`AppMapaEnderecos.vue`. Mosaico de blocos (tiles) do OpenStreetMap, composto e gerado por
script (não fotografado), com dois alfinetes pequenos (ponto sólido + número ao lado, não
balão) desenhados por cima. Exibido em largura fixa (a do bloco dos dois cartões de
`/contato`), por isso o srcset é por densidade de pixel (1x/2x) em vez de por largura — cada
densidade renderizada direto no zoom nativo do OSM, nunca redimensionada depois (redimensionar
foi a causa do texto de rua borrado numa versão anterior). Altura baixa de propósito — o mapa é
apoio, não protagonista, ver decisão de reduzi-la numa sessão de correção em
`app/data/fotos.ts`. Pinos não centralizados verticalmente: folga acima do pino 2 (o mais ao
norte), Avenida Anália Franco encostada na borda inferior, sem folga, abaixo do pino 1. Pino 1
(Sede/CEI) usa a coordenada
exata de um POI já nomeado no OSM. Pino 2 (Bazar) não tem ponto de numeração de casa mapeado
no OSM para "Rua Rosa Siqueira, 152" — usa o centroide do trecho de rua com o mesmo CEP da
Sede/CEI, precisão de rua/quadra, não de fachada exata (ver comentário em
`app/data/fotos.ts`, e a ressalva "localização aproximada" no `alt` abaixo e na legenda da
página). Atribuição "© OpenStreetMap contributors" exibida como legenda na página
(`contato.vue`), não desenhada nos pixels.

> Mapa de ruas do Jardim Aeroporto, em Londrina/PR, com dois alfinetes numerados: 1, a
> Sede/CEI Anália Franco, na Avenida Anália Franco; 2, o Bazar Beneficente (localização
> aproximada), na Rua Rosa Siqueira, a poucas quadras de distância
