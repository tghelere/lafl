# Navegação — especificação

Documento de decisão. Vale para o header público do Nuxt, a gaveta mobile, a
sub-navegação de seção e o rodapé. Onde houver conflito entre este documento e o
que está implementado hoje, este documento vence.

## 1. Princípio

O topo abre portas; não reproduz o sitemap.

A profundidade mora em três lugares, nesta ordem:

1. **Header** — cinco seções. Só o primeiro nível, mais um segundo nível curto
   onde ele ajuda a escolher.
2. **Sub-navegação da seção** — a barra que já existe na página de Educação
   Infantil. É aqui que vivem Proposta pedagógica, Alimentação e saúde,
   Estrutura, Matrícula, Agendar coleta e companhia.
3. **Rodapé** — sitemap completo. Breadth é problema do rodapé, e ele ainda
   ajuda na indexação.

Se um link novo não couber nesses três lugares, o problema é de arquitetura, não
de menu.

## 2. Arquitetura de informação

Cinco itens de topo e um CTA. Todo item de topo é também uma página de destino
clicável — inclusive os que têm painel. Isso importa para SEO e é o que faz a
gaveta mobile funcionar sem truque.

| Item | Rota | Painel |
|---|---|---|
| Quem somos | `/quem-somos` | sim |
| O que fazemos | `/o-que-fazemos` | sim |
| Como ajudar | `/como-ajudar` | sim |
| Transparência | `/transparencia` | não |
| Contato | `/contato` | não |
| Doar (CTA) | `/doar` | — |

Conteúdo dos painéis:

**Quem somos** — Nossa história · Missão e valores · Governança · O Lar hoje

**O que fazemos** — três itens com subtítulo de uma linha:

- Educação infantil — *Creche e pré-escola, 1 a 5 anos*
- Escola de contraturno — *Adolescentes, turmas previstas para 2027*
- Bazar beneficente — *Sustenta o que o convênio não cobre*

**Como ajudar** — Doar · Doar itens ao bazar · Seja parceiro

Transparência e Contato não têm painel porque cada uma é uma página só com sua
própria sub-navegação. A seta aparece apenas onde existe painel — ela é uma
promessa, não enfeite.

`/o-que-fazemos` é rota nova: uma página de índice curta com os três pilares.
Ela substitui a exposição dos três no topo.

Fora do lançamento: Voluntariado, Empresas parceiras e Novidades do bazar
entram depois, dentro de `Como ajudar` e da sub-nav do bazar. Nenhum deles vira
item de topo.

## 3. Fonte única de dados

Toda a estrutura acima vive em **um** arquivo de configuração
(`app/config/navigation.ts` ou equivalente), tipado, consumido pelo header, pela
gaveta, pelo rodapé e pelo sitemap. Nenhum componente declara link no template.

```ts
type NavItem = {
  label: string
  to: string
  children?: { label: string; to: string; hint?: string }[]
}
```

`hint` é o subtítulo, usado hoje só em O que fazemos.

## 4. Tokens

Mapear para o tema Tailwind existente do projeto. Os hexes abaixo são o que está
no ar hoje e servem de referência; se o tema já tiver nomes para eles, usar os
nomes e não repetir o hex.

| Papel | Valor |
|---|---|
| Fundo do header | `#FAF9F7` |
| Texto do item | `#3F3833` |
| Texto do item ativo/hover | `#1C1917` |
| Fundo do item em hover/aberto | `#F0EBE3` |
| Superfície do painel | `#FFFFFF` |
| Borda hairline | `#E7E3DC` |
| Seta e subtítulo | `#A89C8F` / `#8A7F74` |
| Laranja do CTA | `#E4622D` |

Tipografia: Poppins 600 no wordmark; Lora 400 em todos os rótulos de navegação.
Nada de caixa alta.

## 5. Header desktop (≥ 1024px)

- Altura 72px, `position: sticky; top: 0`, `z-index: 50`, fundo sólido, borda
  inferior hairline permanente. Sem transparência, sem mudança de altura no
  scroll.
- Wordmark à esquerda, itens em seguida com `gap: 4px`, CTA empurrado para a
  direita com `margin-left: auto`.
- Item: `padding: 10px 12px`, `font-size: 15px`, `border-radius: 8px`,
  **`white-space: nowrap`**.
- O gatilho é o item inteiro — `<button>` envolvendo rótulo e seta. A seta leva
  `pointer-events: none` e `aria-hidden="true"`.
- Seta: 14px, `margin-left: 5px`, rotaciona 180° quando aberto, transição 150ms.
- Rótulo nunca quebra em duas linhas. Se quebrar, a arquitetura estourou e o
  conserto é remover item, não reduzir fonte.

## 6. Painel

- `position: absolute`, ancorado ao item: borda esquerda do painel alinhada com
  a borda esquerda do rótulo (compensar o padding de 12px do item).
- `top` = altura do header. Sem gap vertical entre header e painel — um vão de
  1px faz o menu fechar no meio do movimento do mouse.
- Largura mínima 220px; 290px no painel de O que fazemos.
- `background` branco, borda hairline, `border-radius: 10px`, `padding: 8px`,
  sombra discreta (`0 8px 24px rgb(0 0 0 / .08)`), `z-index: 40`.
- Link do painel: 14px, `padding: 9px 12px`, `border-radius: 6px`, hover
  `#F5F1EA`. Subtítulo 12px em bloco abaixo do rótulo.
- **Clamp de borda:** se a borda direita do painel passar de
  `viewport - 16px`, alinhar o painel pela direita do item. Nunca deixar
  transbordar, nunca virar faixa de largura total.
- Um painel aberto por vez.

## 7. Abrir e fechar

| Gatilho | Comportamento |
|---|---|
| Mouse entra no item | abre após 80ms |
| Mouse sai do item ou do painel | fecha após 150ms |
| Mouse entra em outro item com painel aberto | troca imediatamente, sem delay |
| Clique no item | navega para a página da seção |
| `Enter`/`Espaço` no item | abre o painel |
| `Escape` | fecha e devolve o foco ao item |
| Clique fora | fecha |
| Foco sai do painel por `Tab` | fecha |
| Mudança de rota | fecha |

O delay de fechamento existe para o percurso diagonal do mouse até o painel. Sem
ele o menu fecha na cara do usuário.

Em ponteiro coarse (`@media (hover: none)`) não há hover: o primeiro toque abre
o painel, o segundo navega.

## 8. Acessibilidade

- Gatilho é `<button>` com `aria-expanded` e `aria-controls`.
- O painel é uma `<ul>` de links dentro de `<nav aria-label="Navegação principal">`.
  **Não usar `role="menu"` / `role="menuitem"`** — isso é para menus de
  aplicação e sequestra as setas do teclado.
- Foco visível em tudo: `outline: 2px solid #1C1917; outline-offset: 2px`.
  Nada de `outline: none`.
- Alvo de toque mínimo de 44px na gaveta mobile.
- `@media (prefers-reduced-motion: reduce)`: sem transição de rotação da seta e
  sem animação de abertura da gaveta; os estados continuam funcionando.

## 9. Gaveta mobile (< 1024px)

Ponto de quebra é `lg` (1024px). Abaixo disso vai para hambúrguer — não espremer
a barra.

- Gaveta em tela cheia, entrando da direita, 200ms.
- Cabeçalho da gaveta: wordmark à esquerda, botão fechar (ícone X,
  `aria-label="Fechar menu"`) à direita.
- Cinco linhas de 48px. **Todos os acordeões fechados ao abrir.** Um aberto por
  vez.
- A linha inteira alterna o acordeão; o rótulo da seção é link para a página da
  seção, com alvo de toque próprio. Se ficar ambíguo na implementação, preferir:
  linha alterna, e o primeiro filho do acordeão é "Visão geral de {seção}".
- Sub-itens recuados 10px, 15px, sem borda entre eles.
- CTA Doar fixo no rodapé da gaveta, largura total.
- Trava o scroll do body enquanto aberta, prende o foco dentro, `Escape` fecha,
  fecha na mudança de rota.
- Transparência e Contato aparecem como linhas simples, sem seta.

Nenhuma tela exibe o sitemap inteiro expandido.

## 10. Sub-navegação de seção

O padrão já implementado em Educação Infantil é o correto. Estender, sem
redesenhar, para Quem somos, O que fazemos, Contraturno, Bazar, Como ajudar e
Transparência.

- Primeiro item é sempre a visão geral da seção e leva o nome da seção.
- Item ativo com peso e cor mais escura; os demais em `#3F3833`.
- Abaixo de `md`, rolagem horizontal **dentro da própria barra**
  (`overflow-x: auto`, `scrollbar-width: none`), com máscara de fade nas
  bordas. Nunca deixar a página inteira rolar na horizontal.

## 11. Regras globais

- `html { overflow-x: clip }`.
- Auditar e remover todo `100vw` — com barra de rolagem vertical presente,
  `100vw` é maior que a largura útil e é a causa usual de rolagem horizontal.
  Usar `100%`.
- Nenhum `min-width` fixo no container do header.
- Container do header: `max-width: 1200px`, `padding-inline: 24px` (16px abaixo
  de `md`).

## 12. Aceitação

A entrega está pronta quando tudo abaixo passa:

1. Em 1024px, 1280px e 1440px nenhum rótulo quebra em duas linhas e nenhum
   painel toca a borda da viewport.
2. De 320px a 1920px, em passos de 20px, não existe rolagem horizontal em
   nenhuma página.
3. O painel abre clicando em qualquer ponto do item, não só na seta.
4. Ir do item até um link do painel na diagonal não fecha o painel.
5. Navegação completa por teclado: `Tab` percorre, `Enter` abre, `Escape` fecha
   e devolve o foco, foco sempre visível.
6. A gaveta mobile abre com cinco linhas visíveis e nada expandido.
7. Com `prefers-reduced-motion`, nada anima e tudo continua utilizável.
8. Header, gaveta e rodapé leem do mesmo arquivo de configuração.
9. Lighthouse de acessibilidade em 100 na home e numa página de seção.
