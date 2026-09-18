# 0018 — Nenhuma rota indexável é prerenderizada

## Contexto

Desde a sessão 21, toda página do site emite três tags **absolutas**, montadas por
`app/composables/usePageSeo.ts` a partir de `NUXT_PUBLIC_SITE_URL`:

```html
<link rel="canonical" href="https://dominio/pagina">
<meta property="og:url"   content="https://dominio/pagina">
<meta property="og:image" content="https://dominio/og/og-padrao.png">
```

Elas precisam ser absolutas porque quem as lê está fora do contexto do site: o rastreador do
buscador e o gerador de pré-visualização de link do WhatsApp, do Facebook e afins não resolvem
caminho relativo.

Ao mesmo tempo, a ADR 0014 e o `deploy.yml` estabelecem que **o pacote de deploy é um só**
para homologação e produção — o mesmo artefato validado em homologação é promovido, sem
rebuild. O job "Gerar o pacote" não define nenhuma `NUXT_PUBLIC_*` no ambiente, de propósito.
`docs/deploy.md` resume: *"nenhum dos dois frontends precisa ser rebuildado por ambiente"*.

Essa afirmação é verdadeira para SSR e **falsa para rota prerenderizada**. Prerender roda no
build: o HTML é gravado no pacote com o valor que a variável tinha naquele momento. Medido
nesta sessão, contra o build de produção:

| Build | O que fica gravado no HTML estático |
|---|---|
| com `.env` local | `https://localhost:3000/o-que-fazemos` — o endereço da máquina de quem buildou |
| sem a variável (o caso do CI) | `/o-que-fazemos` — relativo; `og:image` relativo nenhum rastreador de link resolve |

Nos dois casos, o mesmo pacote serviria homologação e produção com a mesma resposta gravada —
e uma delas estaria errada.

## Decisão

**Nenhuma rota que possa ser indexada entra em `nitro.prerender.routes`.**

Saíram nesta decisão `/o-que-fazemos` e `/politica-de-privacidade`, as duas últimas páginas
indexáveis que ainda eram prerenderizadas. Continuam prerenderizadas apenas as cinco
`/obrigado/*`: sendo `noindex`, elas não emitem canônico nem Open Graph (ver
`usePageSeo`), então não há nenhuma tag dependente do ambiente para o build congelar.

A regra completa da lista, somando os motivos acumulados desde a sessão 16:

| Sai do prerender porque | Exemplo |
|---|---|
| o conteúdo vem do CMS e o painel o edita | `/quem-somos`, `/bazar` |
| depende de número calculado pela API | `/` (idade da instituição) |
| depende de `route.query` | os cinco formulários, `/transparencia/documentos` |
| a decisão é do ambiente, não do build | `robots.txt`, `sitemap.xml` |
| **emite canônico ou Open Graph absoluto** | `/o-que-fazemos`, `/politica-de-privacidade` |

## Alternativas descartadas

- **Buildar um pacote por ambiente.** Desfaz a ADR 0014: "promover para produção" voltaria a
  ser um build novo com outro nome, e o artefato que sobe em produção deixaria de ser o
  mesmo que passou pela homologação.
- **Injetar a URL no HTML prerenderizado em tempo de publicação** (`sed` no `publicar.sh`, ou
  um gancho do Nitro reescrevendo a resposta). Transforma HTML gerado em artefato editado por
  script de deploy, e passa a exigir que o `sed` conheça o formato exato das tags.
- **Emitir as três tags relativas.** `<link rel="canonical" href="/pagina">` até resolve
  corretamente no navegador; `og:url` e `og:image` não — e é justamente o cartão de
  compartilhamento que a instituição usa para divulgar.
- **Deixar `usePageSeo` ler a URL do cabeçalho `Host` da requisição.** Funciona em SSR, mas em
  rota prerenderizada não há requisição no build — e confiar em `Host` para montar canônico
  abre o caminho para alguém envenenar o canônico com um `Host` forjado.

## Consequências

- `npm run generate` emite cada vez menos: hoje, só as cinco páginas de confirmação. O site já
  não era hospedável como estático (formulários, proxy de PDF, `/transparencia/documentos`,
  `robots.txt`, `sitemap.xml` e redirect de slug antigo exigem o Nitro), e esta decisão fecha
  a porta de vez. Se algum dia a hospedagem estática voltar à pauta, ela exige um pacote por
  ambiente — as duas coisas são incompatíveis.
- Custo em produção: SSR de duas páginas de conteúdo fixo a cada requisição. É o tipo de
  página que o cache de HTML da §7 de `docs/deploy.md` cobre, se um dia pesar.
- Toda página nova indexável nasce em SSR. Acrescentar rota à lista de prerender passa a
  exigir responder antes: *esta página pode ser indexada ou compartilhada?* Se sim, não entra.
- A bateria de ponta a ponta cobre a consequência que importa: `tests/seo/metadados.spec.ts`
  confere que as três tags saem absolutas e com o domínio da instância sob teste.
