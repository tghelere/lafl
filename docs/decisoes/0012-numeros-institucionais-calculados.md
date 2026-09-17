# 0012 — Números institucionais calculados na API, com marcador no conteúdo do CMS

## Contexto

O site publicava três classes de número escritas à mão:

- **contagem** — "o acervo de prestação de contas, hoje com cerca de 70 documentos"
  (`transparencia`), num acervo que muda a cada upload e a cada exclusão pelo painel;
- **idade** — `date="58 anos"` na linha de registro da home, ao lado de "Bazar beneficente em
  funcionamento desde 1968";
- **ano de acontecimento** — "o bazar existe desde 1968", "o CEI foi criado em 2002".

As duas primeiras envelhecem sozinhas. Não com estardalhaço: em 1º de janeiro de 2027 a home
passa a mentir por um ano inteiro, e ninguém recebe aviso nenhum. É o tipo de erro que corrói
exatamente a coisa que o site existe para sustentar — a instituição que presta contas
públicas não pode publicar número errado sobre si mesma.

A terceira classe não envelhece: 1968 é 1968 para sempre.

O agravante é que o conteúdo institucional é editado pelo painel, por pessoas da instituição.
Não adianta a home calcular a idade se a página de transparência continua com o número
digitado dentro de um `<p>` que só um editor de texto rico alcança.

## Decisão

**Toda contagem e toda idade é calculada; ano de acontecimento e valor de documento
continuam literais.**

### Fonte única

`config/institution.php` declara os marcos. O FORMATO DO VALOR declara a precisão:

| Valor | Precisão | Como a idade é calculada |
|---|---|---|
| `'1953-07-12'` | dia | aniversário respeitado — em 11/07 ainda não completou |
| `'1968'` | ano | diferença de ano |

`App\Services\InstitutionalFacts` faz as contas no fuso `America/Sao_Paulo`, não no UTC da
aplicação: das 21h à meia-noite de Londrina o UTC já está no dia seguinte, e sem isso o site
anunciaria o aniversário três horas antes da hora.

A saída vem **já formatada em português**, com substantivo e plural ("1 ano"/"73 anos",
"1 documento"/"71 documentos", milhar com ponto). Quem consome nunca monta a frase — é o que
impede "71 documento" e mantém a regra do plural no backend, onde as outras regras estão.

### Dois caminhos até o texto, um só cálculo

- **Conteúdo do CMS** — marcadores em texto puro (`App\Enums\ContentMarker`):
  `{{idade_associacao}}`, `{{idade_sede}}`, `{{idade_bazar}}`, `{{idade_cei}}`,
  `{{documentos_transparencia}}`. `{` e `}` não são sintaxe de HTML, então o marcador
  atravessa o editor Tiptap e o `ContentSanitizer` sem alteração alguma — há teste para isso.
- **Página fixa em `.vue`** (hoje só a home) — `GET /api/v1/public/institution-facts`, com
  cada marco cru e formatado.

### Três regras que o desenho depende

1. **Resolução só na leitura pública.** O endpoint administrativo devolve o marcador cru. Se o
   painel recebesse "73 anos", o primeiro salvamento gravaria esse texto no banco e o cálculo
   morreria em silêncio — a página diria 73 para sempre, e ninguém saberia por quê. Coberto
   por teste Pest e pela bateria de ponta a ponta.
2. **Resolução depois do cache.** `ResolvePublicPageBySlug` guarda o conteúdo CRU por dez
   minutos; `ResolveContentMarkers` roda sobre o que sai do cache. Publicar, despublicar ou
   excluir um documento muda a contagem já na requisição seguinte. Como a resposta agora muda
   sem a página ter sido editada, página com marcador passa a `max-age=0, must-revalidate`, e
   o ETag inclui o conteúdo já resolvido — quem revalida leva 304 enquanto o número não mudar.
3. **Marcador desconhecido é recusado ao salvar** (422, mensagem em português listando os
   válidos), não descoberto na leitura. Na escrita existe alguém a quem avisar: quem digitou
   `{{idade_da_casa}}` ainda está na tela. Na leitura só haveria a escolha entre publicar o
   marcador cru e apagar o trecho em silêncio.

A home sai de `nitro.prerender.routes` por consequência direta: prerenderizada, congelaria a
idade no dia do build — a mesma falha silenciosa do número digitado, com outra fachada.

## Alternativas descartadas

- **Calcular no frontend.** Proibido por `CLAUDE.md` (regra 1) e, aqui, pior que o normal: o
  plural do português e o fuso viveriam duplicados em Nuxt e em Vue, e o conteúdo do CMS —
  que é `v-html` — continuaria sem solução nenhuma.
- **Gravar o valor resolvido no banco a cada recálculo (comando agendado).** Troca um número
  errado silencioso por outro: se o agendamento parar, ninguém percebe. E o conteúdo do banco
  deixaria de ser o que a pessoa escreveu.
- **Resolver antes do cache de `ResolvePublicPageBySlug`.** Simples de escrever e errado no
  que importa: publicar um documento levaria até dez minutos para aparecer na contagem, sem
  explicação visível para quem acabou de publicar.
- **Sintaxe de template de verdade (Blade, Twig, Mustache) no conteúdo.** Poder demais dentro
  de um campo que pessoas editam: condicional, laço e acesso a objeto viram superfície de
  ataque e de erro. Cinco nomes fechados num enum, validados na escrita, dão tudo o que o
  caso de uso pede.
- **Entidade `institution_stats` editável pelo painel** (já prevista em
  `docs/estrutura-site.md`). Resolve outro problema — número que a instituição informa e
  atualiza à mão (crianças atendidas, turmas). Não resolve este: idade e contagem de acervo
  não são informadas, são derivadas. As duas coisas podem coexistir.
- **Marcador com espaço obrigatório ou delimitador exótico** (`[[x]]`, `%x%`). `{{x}}` é a
  convenção que mais gente reconhece, e o teste mostra que ela atravessa o editor intacta.

## Consequências

- Confirmar com a instituição o **dia** do início do bazar ou da criação do CEI passa a ser
  uma troca de uma linha em `config/institution.php` (`'1968'` → `'1968-05-03'`); nada mais
  muda. Até lá, a idade desses dois fica um ano adiantada entre 1º de janeiro e o aniversário
  real — imprecisão deliberada, registrada no docblock de `InstitutionalFacts::computeAges`.
- A home deixa de ser prerenderizável enquanto ler fatos calculados. Isso não muda a exigência
  de deploy: o servidor Nitro já era necessário para os cinco formulários e para
  `/transparencia/documentos`.
- Toda leitura pública de página com marcador faz uma consulta a mais (a contagem). Página sem
  marcador não paga nada: `ResolveContentMarkers` sai cedo quando o conteúdo não tem `{{`.
- `InstitutionalFacts` memoriza o que calcula, mas **não** é singleton do container, de
  propósito: a instância do grafo de dependências já dura uma requisição, que é o tempo em que
  os valores são estáveis. Um singleton sobreviveria à requisição (em teste, e sob Octane
  também em produção) e serviria a contagem de antes do documento ter sido publicado.
- O `ContentPagesSeeder` grava `content` direto pelo model, sem passar por `SavePage` — então
  a recusa de marcador desconhecido não o alcança. Um teste Pest varre o conteúdo do seeder
  para fechar esse buraco.
- Acrescentar um marcador exige três passos casados: o caso no enum, a origem do valor em
  `InstitutionalFacts` e — se for idade — o marco em `config/institution.php`. A lista do
  painel e a mensagem de erro se atualizam sozinhas a partir do enum.
