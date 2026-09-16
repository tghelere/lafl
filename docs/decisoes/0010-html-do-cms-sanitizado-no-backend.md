# 0010 — HTML do CMS sanitizado no backend, editado com Tiptap

## Contexto

O painel passa a editar o conteúdo das páginas institucionais (`pages.content`). Esse campo é
renderizado no site público com `v-html` (`frontend-site/app/pages/**`), ou seja, o HTML
gravado no banco é executado no navegador de quem visita o site. Sem filtro, um usuário do
painel com papel `comunicacao` poderia — por descuido ao colar de um editor externo, ou de
propósito — gravar `<script>` numa página pública. Isso é XSS armazenado, e o site é a
superfície mais exposta do projeto.

Duas dependências novas entram nesta fatia: uma para filtrar o HTML no backend, outra para
produzir esse HTML no painel. `CLAUDE.md` exige justificar dependência nova — "a superfície de
ataque importa mais aqui que conveniência".

Um levantamento do conteúdo atual (27 páginas do `ContentPagesSeeder`) mediu exatamente o que
precisa sobreviver ao filtro:

| Estrutura | Ocorrências | Situação |
|---|---|---|
| `p`, `li`, `a`, `h2`, `ul`, `strong`, `h3` | 108 | dentro do editor básico |
| `br` | 1 (`educacao-infantil/matricula`) | fora — preservado na allowlist |
| `a[class="btn btn--primary"]` | 2 (`contraturno`, `contraturno/o-que-vem-por-ai`) | fora — preservado com allowlist de token |
| `a[target]` + `a[rel]` | 2 (`bazar/visite-a-loja`, `bazar/o-que-aceitamos`) | fora — preservado |
| `em`, `ol` | 0 | no editor, apenas não usados hoje |

Nenhuma página usa `div`, `style`, classe de layout ou bloco especial. Nenhuma precisou ser
marcada como não editável.

## Decisão

**Sanitizar no backend, com `symfony/html-sanitizer`, dentro de `SavePage`.**

O filtro vive no Action, não no `FormRequest`, para que todo caminho de escrita passe por ele
— inclusive seeder, comando de console ou chamada futura que não venha de uma requisição
HTTP. A allowlist (`App\Support\Html\ContentSanitizer`) é explícita: `p`, `h2`, `h3`,
`strong`, `em`, `ul`, `ol`, `li`, `br` e `a[href,target,rel,class]`; esquemas de link `http`,
`https`, `mailto`, `tel` e caminho relativo; `class` em `<a>` restrita aos tokens de botão do
site (`btn`, `btn--primary`, `btn--secondary`).

Link externo recebe `rel="noopener noreferrer"`; link interno não. O
`HtmlSanitizerConfig::forceAttribute()` do componente é incondicional e sujaria todo link
interno do conteúdo, então essa parte é um passo próprio, aplicado sobre a saída já
sanitizada — onde `<` e `>` dentro de valor de atributo já estão escapados, o que torna o
casamento da tag previsível.

**Editar com Tiptap no painel**, com a barra de ferramentas limitada ao que a allowlist
aceita.

### Duas propriedades verificadas antes de fechar a escolha

1. **Idempotência.** A saída do sanitizador é entrada estável dele mesmo (`sanitize(sanitize(x))
   === sanitize(x)`), medido nas 27 páginas e em casos isolados (`&`, aspas, acentos, query
   string). Sem isso, cada salvamento degradaria o texto um pouco mais — o caso clássico é
   `&` virar `&amp;` a cada passagem. Há teste cobrindo isso.

2. **Tag fora da allowlist bloqueia, não derruba.** O padrão do componente para tag
   desconhecida é remover o elemento **junto com os filhos**: `<div><p>texto</p></div>`
   viraria string vazia, e quem colasse do Word perderia o texto inteiro sem aviso. Por isso
   `div`, `span`, `b`, `i`, `table` e afins entram como `blockElement` (tag some, texto fica).
   `script`, `style` e `iframe` ficam de fora dessa lista de propósito — para eles derrubar
   com o conteúdo é o certo, senão o corpo do script viraria texto visível na página.

### Normalização do conteúdo do seeder

O teste exigido compara a saída do sanitizador com o conteúdo do seeder byte a byte. Três
páginas divergiam, em dois pontos, ambos de serialização e nenhum de allowlist:

- `<br>` → `<br />` (o componente sempre fecha elemento vazio);
- `?text=` → `?text&#61;` na URL do WhatsApp (o componente codifica `=`, `+`, `@` e `` ` ``
  dentro de valor de atributo — `StringSanitizer::REPLACEMENTS` — como defesa contra quirks de
  interpretação de atributo em navegador).

Ambos são idênticos depois que o navegador decodifica o HTML: mesma quebra de linha, mesmo
destino de link. O conteúdo do seeder foi normalizado para essa forma canônica — é
normalização de serialização, não simplificação: nenhuma tag, atributo, palavra ou destino de
link mudou. Como o sanitizador é idempotente, essa forma é estável, e é exatamente a que
qualquer salvamento pelo painel vai produzir daqui em diante.

## Alternativas descartadas

- **`v-html` sem sanitização, confiando no papel do usuário.** Contraria `CLAUDE.md` ("a API
  é a única fonte de verdade"), e o papel `comunicacao` é justamente o de menor privilégio
  entre os que escrevem conteúdo. Confiança de papel não substitui filtro de conteúdo.
- **Sanitizar só no editor (Tiptap), sem filtro no backend.** Regra de negócio no frontend,
  proibida por `CLAUDE.md`. Além disso, a API aceita `PUT` direto — o editor não é o único
  caminho até o campo.
- **Escrever a allowlist à mão com `DOMDocument`/regex.** Foi o que motivou procurar
  biblioteca: parsing de HTML hostil é onde implementações caseiras falham (mutation XSS,
  `<svg>` com namespace, atributo sem aspas, entidade dupla). O componente do Symfony segue o
  padrão W3C Sanitizer API, tem histórico de CVE tratado e é mantido pelo mesmo ecossistema
  de que o Laravel já depende — `symfony/*` já está no `composer.lock` como dependência
  transitiva do framework, então a superfície nova é um pacote pequeno e sem dependência
  externa própria.
- **`mews/purifier` (HTMLPurifier).** Resolve o mesmo problema e é maduro, mas é bem maior,
  carrega cache em disco de definições e a configuração é por string de diretiva, menos
  legível que a allowlist tipada do componente do Symfony. Sem ganho que justifique o
  tamanho, dado que a allowlist aqui é pequena e fechada.
- **Guardar Markdown em vez de HTML.** Tecnicamente mais seguro, mas exigiria migrar as 27
  páginas existentes e mudar a renderização do site — muito além do recorte desta fatia, que
  é "editar o conteúdo das páginas existentes". Fica registrado como caminho possível se o
  conteúdo crescer em complexidade.
- **Editor `contenteditable` próprio, sem Tiptap.** Produzir HTML previsível a partir de
  `contenteditable` puro (colagem, desfazer, seleção entre blocos) é trabalho considerável e
  já resolvido. Tiptap é o editor sobre ProseMirror com schema declarativo — o que importa
  aqui é justamente poder declarar um schema que só conhece as marcas e nós da allowlist, em
  vez de filtrar depois.
- **Quill ou CKEditor.** Quill usa formato próprio (Delta) e converter para HTML de volta
  acrescenta uma tradução no meio. CKEditor 5 tem licença GPL/comercial — inadequado para o
  contexto da instituição.

## Consequências

- `symfony/html-sanitizer` e `@tiptap/*` entram como dependências mantidas; a allowlist do
  backend e a barra do editor precisam ser alteradas juntas, senão o editor oferece formatação
  que o backend remove ao salvar.
- Todo HTML gravado em `pages.content` passa a estar na forma canônica do sanitizador. Quem
  ler o campo direto no banco verá `<br />` e `&#61;` onde antes via `<br>` e `=`.
- O teste que compara o conteúdo do seeder byte a byte com a saída do sanitizador funciona
  como alarme: acrescentar estrutura nova ao conteúdo institucional sem estendê-la na
  allowlist quebra a suíte antes de a página ir ao ar mutilada.
- Adicionar uma tag ao editor no futuro (tabela, imagem, citação) exige três passos casados:
  allowlist do `ContentSanitizer`, schema/barra do Tiptap e — se a tag tiver estilo próprio —
  CSS do site.
