> **Modelo recomendado: Sonnet**

# 04 — Logos da instituição e crédito da Softhing

Leia `docs/tarefas/README.md` e os dois `LEIA-ME.md` em `shared/brand/`. Rode depois da 03.

## Parte A — Logo do Lar Anália Franco

Arquivos prontos em `shared/brand/lar-analia-franco/` (fonte única; os frontends copiam de lá,
de preferência por script de build ou cópia documentada — não duplicar à mão sem registro).

1. **Site público**
   - Header: logo horizontal no lugar do texto "Lar Anália Franco", como `<img>` com `width` e
     `height` declarados (sem salto de layout), link para `/`, nome acessível "Lar Anália
     Franco — página inicial". Altura em torno de 44 px no desktop e 36 px no mobile; conferir
     o alinhamento com o menu e o botão Doar (a tarefa 03 criou os testes — continuar verdes).
   - Rodapé (fundo escuro): logo horizontal colorida — contraste já medido e aceitável.
   - Favicons: substituir o `favicon.ico` padrão do Nuxt pelo conjunto de `favicon/`, com os
     `<link>` correspondentes em `app.head` (ico, svg, apple-touch-icon).
2. **Painel**
   - Tela de login: logo **vertical** acima do formulário; manter um título acessível
     ("Painel administrativo").
   - Topo da sidebar (fundo escuro): logo horizontal em tamanho reduzido (ou símbolo +
     "Painel", se a largura não comportar — decidir no navegador e registrar).
   - Favicons: substituir o `favicon.svg` padrão do Vite.
3. **Tokens de cor:** `shared/design-tokens/tokens.css` marca `#f9b62a` e `#f26f2e` como "valor
   de marca", mas as cores oficiais (conferidas no `.ai` de identidade, ver `LEIA-ME.md`) são
   `#FABB29` e `#F26E34`. Alinhar os dois valores de marca à
   logo, recalcular as razões de contraste documentadas no próprio arquivo e em
   `docs/decisoes/0009-direcao-visual.md`, e registrar a mudança no ADR.
4. **E-mail de notificação:** logo PNG (`lar-analia-franco-horizontal-600.png`) no cabeçalho do
   template, servida por URL absoluta do site.

## Parte B — Crédito "desenvolvido por Softhing"

O projeto é pro bono; o crédito é a contrapartida e precisa estar em **todas as páginas** do
site e no painel.

- **Site:** faixa discreta no fim do rodapé, depois do copyright: "Desenvolvido por" + logo da
  Softhing (`softhing-fundo-escuro.svg`).
- **Painel:** tela de login (rodapé da tela, `softhing-fundo-claro.svg`) e base da sidebar (`softhing-fundo-escuro.svg` — a sidebar tem fundo escuro).
- Link: `https://softhing.com.br/?utm_source=lar-analia-franco&utm_medium=referral&utm_campaign=credito-site`
  (no painel, `utm_campaign=credito-painel`). `target="_blank"` com `rel="noopener"` **apenas**
  — sem `noreferrer` (esconderia a origem da visita no analytics da Softhing) e sem `nofollow`.
- Nome acessível do link: "Softhing — abre o site da desenvolvedora em nova aba".
- Evento Umami no clique do crédito (o plugin já existe), para medir visitas geradas.

## Etapas

1. Logo e favicons do site. 2. Logo e favicons do painel. 3. Tokens de cor e ADR. 4. Crédito
Softhing no site e no painel. 5. Logo no e-mail. 6. E2e: logo presente com nome acessível no
header do site e no login do painel; link da Softhing presente, com `href` e `rel` corretos, na
home, numa página do CMS e no login do painel.

## Verificação

Builds, lint, e2e completo, Pest (template de e-mail). Conferência no Firefox em desktop e
375 px: header, rodapé, login e sidebar.
