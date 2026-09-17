# 0013 — Paleta de marca derivada da logo substitui a paleta verde/ocre (0009)

## Contexto

`docs/decisoes/0009-direcao-visual.md` registrou uma paleta verde de quadra poliesportiva +
ocre de horta, com tipografia Bitter/IBM Plex Sans. O commit `6b55ccb` ("substitui paleta
verde pela identidade visual da marca") trocou essa paleta pela combinação amarelo/laranja
extraída da logo oficial do Lar Anália Franco, e trocou a tipografia por Poppins/Lora — mas
não veio acompanhado de ADR: o cabeçalho de `shared/design-tokens/tokens.css` ficou com a nota
"decisão desta sessão, ADR de substituição pendente de registro" desde então. Esta ADR fecha
essa pendência.

Nesta sessão (tarefa 04, Parte A), a implementação da logo revelou que os hexadecimais de
marca gravados em `tokens.css` — `#f9b62a` (amarelo) e `#f26f2e` (laranja) — não eram os
valores oficiais. Os valores conferidos em `shared/brand/lar-analia-franco/originais/lar-analia-franco-logo-2019.ai`
(página "Web") são `#FABB29` e `#F26E34` (ver `shared/brand/lar-analia-franco/LEIA-ME.md`).
A diferença é pequena (poucas unidades de RGB por canal) mas os tokens marcados "valor de
marca — não alterar" existem exatamente para não divergir do arquivo de identidade.

## Decisão

Adotar a paleta amarelo/laranja extraída da logo (já em uso desde `6b55ccb`) como a paleta de
marca vigente, com os dois valores de âncora corrigidos para bater com o `.ai` de identidade:

| Token | Valor antigo (aproximado) | Valor corrigido (oficial) |
|---|---|---|
| `--color-yellow-500` | `#f9b62a` | `#FABB29` |
| `--color-orange-500` | `#f26f2e` | `#F26E34` |

O restante da escala (50–900 de cada matiz) e a escala neutra não muda — a diferença entre os
dois valores de âncora é pequena o bastante (≤6 unidades de RGB por canal) para não justificar
regenerar as escalas inteiras; só os dois tokens marcados como valor de marca, e o que deriva
diretamente deles, precisavam de ajuste.

`--color-accent-hover` deriva de `--color-orange-500` reduzindo o L (HSL) no mesmo delta que o
valor antigo usava (calibrado, não é um degrau redondo da escala) — recalculado para
`#f05917` (era `#f05b11`) para manter a mesma relação "um pouco mais escuro que o 500" com o
novo valor de âncora.

### Razões de contraste recalculadas (WCAG 2.x, luminância relativa sRGB)

| Par | Valor antigo | Valor corrigido |
|---|---|---|
| Branco / `--color-yellow-500` | ≈ 1,79:1 | ≈ 1,72:1 |
| Branco / `--color-orange-500` | ≈ 2,96:1 | ≈ 2,98:1 |
| `--color-heading` on `--color-accent` (rótulo de botão) | 5,54:1 | 5,51:1 |
| `--color-heading` on `--color-accent-hover` (rótulo de botão, hover) | 4,85:1 | 4,80:1 |
| `--color-focus` on `--color-surface` (anel de foco) | 5,6:1 | 5,6:1 (inalterado — `--color-orange-700` não muda) |

Todos os pares de texto continuam ≥ 4,5:1; a restrição de `docs/protecao-de-dados.md` não se
aplica aqui (não é dado pessoal). `shared/design-tokens/tokens.css` é a fonte de verdade destes
valores — os comentários no próprio arquivo foram atualizados junto desta ADR.

## Status de 0009

`docs/decisoes/0009-direcao-visual.md` permanece como registro histórico da direção anterior
(inclusive a tipografia Bitter/IBM Plex Sans, também substituída por `6b55ccb`), com uma nota
de "substituída por esta ADR" no topo. Não foi reescrita: ADR é registro de decisão tomada em
determinado momento, não documentação viva — reescrever o conteúdo apagaria o motivo pelo qual
a paleta verde/ocre existiu e foi descartada.

## Consequências

- Nenhuma mudança de código além dos tokens em si (Parte A, Etapa 3 da tarefa 04) — componentes
  consomem os tokens semânticos, não a escala direta, então a correção não tocou
  `components.css` de nenhum dos dois frontends.
- `docs/decisoes/0009-direcao-visual.md` ganha uma nota de status apontando para esta ADR, sem
  alterar o restante do conteúdo.
