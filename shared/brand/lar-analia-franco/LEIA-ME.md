# Marca — Lar Anália Franco

Fonte única dos arquivos de marca do site público e do painel. Os dois frontends copiam daqui;
nenhum arquivo de logo é editado dentro de `frontend-site/` ou `frontend-admin/`.

O logo usado dentro de componente Vue (header, rodapé, login, sidebar) é `import`ado direto
daqui — sem cópia, mesmo padrão de `shared/design-tokens/tokens.css` (ver `nuxt.config.ts` e
`frontend-admin/vite.config.ts`, ambos com `vite.server.fs.allow` liberando acesso a este
diretório). Favicon e a logo do e-mail são cópia manual e documentada, não import: são
consumidos fora do grafo de módulos JS (requisição direta de `/favicon.ico`, `<img>` de
e-mail que precisa de URL absoluta) e por isso vivem em `frontend-site/public/` e
`frontend-admin/public/`. Se o arquivo de origem mudar, recopiar manualmente — não há script
de sincronização.

| Arquivo | Uso |
|---|---|
| `lar-analia-franco-horizontal.svg` | Header e rodapé do site, topo da sidebar do painel |
| `lar-analia-franco-vertical.svg` | Tela de login do painel e espaços mais altos que largos |
| `lar-analia-franco-simbolo.svg` | Só o símbolo (casa com "LAR"): espaços compactos, favicon SVG |
| `lar-analia-franco-horizontal-600.png` | Onde SVG não é aceito (e-mail, JSON-LD `logo`) |
| `favicon/` | `favicon.ico` (16/32/48), `favicon.svg`, `apple-touch-icon.png` (180), `icon-192.png`, `icon-512.png` |
| `og/og-padrao.png` | Imagem Open Graph padrão (1200×630) para páginas sem imagem própria |
| `originais/` | Arquivos como recebidos — não usar direto nas páginas |

## Cores oficiais

Conferidas no arquivo de identidade `originais/lar-analia-franco-logo-2019.ai` (página "Web" e
página de cores):

| Cor | Web (RGB) | Impressão (CMYK) |
|---|---|---|
| Amarelo | `#FABB29` | C0 M30 Y100 K0 |
| Laranja | `#F26E34` | C0 M70 Y90 K0 |
| Turquesa (cor de apoio, não usada no site hoje) | `#52C3CA` | C60 M0 Y20 K0 |

`originais/laf-logo-vertical-cores-incorretas.svg` tem o desenho certo (confere com o `.ai`),
mas com `#F2592A` e `#F8B618`, que não são as cores oficiais. A versão em uso,
`lar-analia-franco-vertical.svg`, é o mesmo desenho com as cores oficiais.

O `.ai` também traz variações em uma cor (logo branca sobre fundo laranja ou amarelo). Não
foram exportadas porque o site ainda não tem onde usá-las; se surgir o uso, exportar do `.ai`.

## Tratamento dos SVGs derivados

Diferem dos originais só no que é técnico: cores como atributo `fill` em vez de classes
`.cls-*` (classes genéricas de Illustrator colidem entre dois SVGs na mesma página), `viewBox`
justo ao desenho, `<title>` para acessibilidade, sem metadados de editor. Nenhum traço alterado.

Contraste medido: laranja da logo sobre `--color-neutral-900` (#221F1B, fundo do rodapé e da
sidebar) = 5,51:1; sobre branco = 2,98:1 (aceitável para logotipo, isento do critério de
contraste de texto).
