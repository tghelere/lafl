# 0028 — Texto do site ocupa a largura útil do container

**Status:** aceita (sessão 35)

## Contexto

Títulos e parágrafos tinham `max-width` próprio (`68ch` em `.prose`; `46rem`, `22ch` e `58ch` no
hero e nas notas da home; `44rem` na página de erro; `34rem` em "obrigado"). Em telas largas o
texto terminava bem antes da borda direita da foto ou do grid logo abaixo, deixando um vazio à
direita e bordas desalinhadas dentro da mesma seção.

## Decisão

Existe um único token, `--measure`, em `frontend-site/app/assets/css/tokens.css`, com valor
`none`. `.prose` e os blocos de texto que antes tinham largura própria usam `max-width:
var(--measure)`. Resultado: o texto ocupa a largura útil do `.container`, com as mesmas bordas
esquerda e direita da imagem/grid da seção — inclusive o conteúdo vindo do painel (`.prose`).

Página ou componente **não declara `max-width` em texto corrido**; se um dia for preciso limitar a
medida de leitura, muda-se o token, em um lugar só.

## Fora do escopo (de propósito)

- Formulários (`.form`, 34rem): são grupos de controles, não texto.
- Figura "placa" de Nossa história (22rem) e legenda da ampliação (60ch): imagem e overlay.
- Container do header (1200px, `docs/design/navegacao.md` §11): especificado à parte.

## Consequência

Linhas longas em monitores muito largos (≥ 1440px) são aceitas por escolha do cliente: o
container já limita a 72rem. Mobile e tablet não mudam (o container é mais estreito que qualquer
um dos limites antigos).
