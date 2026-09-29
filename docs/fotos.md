# Fotos do site

> **Desde a sessão 28, toda foto do site vem da biblioteca do painel.** Até ali, as fotos das
> páginas de seção eram arquivos fixos em `frontend-site/public/fotos/`, preparados à mão e
> usados por `AppFoto` a partir de `app/data/fotos.ts`. Os dois não existem mais. Ver
> `docs/decisoes/0024-biblioteca-de-midia.md` (biblioteca) e
> `docs/decisoes/0025-imagens-da-pagina.md` (capa e galeria).

## Onde cada foto aparece

| Onde no site | De onde vem |
|---|---|
| No meio do texto de qualquer página | `<figure><img src="/midia/{uuid}">` gravado no conteúdo pelo editor (ADR 0024) |
| Galeria de uma página (bazar, educação infantil, transparência, quem somos, nossa história e qualquer página do CMS) | galeria da página, em "Imagens desta página" no painel |
| Destaque da página inicial | capa da página **Quem somos** |
| Cartões de "O que fazemos" | capa da página de cada frente (Educação infantil, Bazar). Frente sem capa fica sem foto |
| Mapa de `/contato` | arquivo fixo, `public/fotos/contato/` — ver abaixo |

A API entrega capa e galeria prontas para um `<img>` (`images.cover` e `images.gallery` em
`/api/v1/public/pages/{slug}`, com `src`, `srcset`, `width`, `height`, `alt` e `caption`). No
site, `AppImagem.vue` desenha uma imagem e `AppGaleria.vue` a grade. O `sizes` é do site, por
`contexto` (`cheia`, `metade`, `terco`, `quarto`), porque só o layout sabe quanto da tela a
imagem ocupa.

`nossa-historia.vue` tem layout próprio: a primeira foto da galeria vira o destaque (a placa de
inauguração), e as outras seguem em pares de antes e depois.

## Ampliação

Toda imagem de conteúdo (galeria e figura do texto) é um link para a maior derivada e abre
ampliada sobre a página, com legenda, crédito e navegação na mesma galeria. Sem JavaScript, o
link abre a foto. O destaque da home, os cartões de "O que fazemos" e o mapa ficam de fora. Ver
`docs/decisoes/0026-ampliacao-de-imagens.md`.

## Como trocar ou acrescentar uma foto

Pelo painel, sem desenvolvedor: na edição da página, seção "Imagens desta página". Enviar põe a
foto no fim da galeria; substituir troca o arquivo em todo lugar onde a imagem aparece, sem
mudar o endereço; texto alternativo e legenda são da imagem e valem em todas as páginas que a
usam. A tela `/admin/imagens` é o acervo completo.

Upload, derivadas webp (400 a 1920, nunca maiores que a original) e remoção de EXIF são feitos
pela API, no envio. Nada disso precisa ser preparado à mão.

## Fotos iniciais

As 24 fotos que o site tinha até a sessão 28 estão em `backend/resources/initial-photos/`, cada
uma na maior largura que o site servia (a original de câmera nunca esteve no repositório). O
catálogo é `App\Support\Media\InitialPhotos`: texto alternativo, legenda e em que página cada
uma entra, como capa ou galeria. `php artisan midia:importar-fotos-iniciais` as leva para a
biblioteca. O comando é idempotente, e `migrate:fresh --seed` o roda em desenvolvimento.

Sete delas não estavam em página nenhuma e ficam só na biblioteca: `fachada-sede-atual`,
`recanto-da-amizade`, `recanto-vista`, `recepcao`, `horta-kids-vista`, `bazar-deposito` e
`bazar-leitura`.

Como `InitialPages`, o catálogo é só semente. Depois do lançamento, o que vale é o banco (ADR
0023), e as fotos chegam a produção pelo pacote de conteúdo, não pelo comando.

## A exceção: o mapa de `/contato`

`public/fotos/contato/mapa-enderecos-{1x,2x}.{webp,jpg}`, desenhado por `AppMapaEnderecos.vue`.
Não é foto: é um mosaico de blocos do OpenStreetMap gerado por script, com dois alfinetes
numerados, exibido em largura fixa. Por isso o `srcset` é por densidade (1x = zoom 18,
1088×450; 2x = zoom 19, 2176×900, cada densidade renderizada no zoom nativo, nunca
redimensionada), e a biblioteca, que gera derivadas por largura, não serve para ele. O critério
de enquadramento e a precisão dos pinos estão no comentário do componente. Atribuição
"© OpenStreetMap contributors" na legenda da página, não nos pixels.

Cache: `/fotos/contato/**` sai com `Cache-Control: public, max-age=2592000`, sem `immutable`,
porque o nome do arquivo não tem hash (`nuxt.config.ts`).

## Endereços antigos

Os outros endereços de `/fotos/` que existiram até a sessão 28
(`/fotos/{secao}/{chave}-{largura}.{webp,jpg}`) respondem 301 para a mesma foto em `/midia/`
(ADR 0025). A lista dos 144 está em `backend/tests/Fixtures/legacy-photo-paths.txt`, e a seção
antiga de cada foto, em `section` no catálogo `InitialPhotos`.
