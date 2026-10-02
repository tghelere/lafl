# 0029 — Parceiros com logo da biblioteca; ampliação nunca menor que a página

## Parceiros

- Tabela `partners` (migration aditiva): `uuid`, `name`, `media_id` (logo, FK para `media`,
  índice explícito), `url` opcional, `position`, `is_active`, timestamps e soft delete.
- A logo é uma imagem da **biblioteca**, e não um arquivo próprio: o upload já remove EXIF e gera
  as derivadas webp. PNG, JPG e WebP; **SVG não**, porque o projeto não sanitiza SVG no upload.
  Texto alternativo = nome do parceiro; declaração de assistido gravada como "não" (logotipo de
  empresa).
- Trocar a logo substitui o arquivo da mesma imagem (`ReplaceMediaFile`). Excluir a imagem da
  biblioteca é recusado enquanto um parceiro ativo a usa (`DeleteMedia`).
- Permissões: as de quem edita páginas (`direcao`, `comunicacao`; `super_admin` por bypass), com
  criar e excluir para os dois — é conteúdo do dia a dia.
- Ordem: campo numérico. Nenhum outro cadastro reordena arrastando (a galeria usa botões de
  subir/descer por foto, sem relação com esta lista).
- API pública `GET /api/v1/public/partners`: só ativos, ordenados, paginada (padrão 50, máx. 100),
  com nome, link e logo. Logo marcada depois como foto de assistido tira o parceiro da lista.
- Site: a grade aparece só em `/como-ajudar/parceiros`, abaixo do texto do CMS, e nada é
  desenhado sem parceiros.

## Ampliação

Causa: `.ampliacao__imagem` tinha `width:auto`, então a imagem era desenhada no tamanho
**natural** da derivada escolhida (400/640/960 px), mesmo com o palco maior; a derivada era a
maior que cabia (nunca a que cobria o tamanho), e o palco era limitado por moldura (até 80rem ×
60rem, com padding) e pela altura (foto em pé). Medido antes da correção: aberta 400 px contra 532
na página; 640 contra 700.

Correção em `AppAmpliacao.vue` (um componente para o site todo): a largura com que a imagem
aparece na página é medida ao abrir e vira o piso; o teto é o palco (a tela inteira menos
controles). A largura é aplicada explicitamente, a derivada é a menor que cobre esse tamanho (ou
a maior que existir) e, quando o piso não cabe na altura, o palco rola em vez de encolher a
imagem. O diálogo ocupa a tela toda em qualquer largura. Nenhuma dependência foi removida: o
componente já era `<dialog>` nativo.
