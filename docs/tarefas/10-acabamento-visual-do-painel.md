> **Modelo recomendado: Opus**

# 10 — Painel: acabamento visual e responsividade

Requisito transversal: **nada desalinhado**. Inputs, selects e botões lado a lado com a mesma
altura; itens de menu e breadcrumb alinhados na mesma linha de base.

## Itens

1. **Favicon.** Os arquivos já estão na pasta `public` do painel (`favicon.svg`, `favicon.ico`,
   `apple-touch-icon.png`, `icon-192.png`, `icon-512.png`). Configurar no `index.html` e no
   manifest (nome "Painel · Lar Anália Franco"). Título das abas no formato
   `<Tela> · Painel LAF`.
2. **Menu ativo.** O item do menu deve continuar marcado nas rotas filhas (detalhe de registro,
   edição de página). Usar uma meta de seção na rota, não comparação exata de URL.
3. **Breadcrumb.** Hoje aparece "PÁGINAS / / EDITAR PÁGINA", com segmento vazio. Formato
   correto: "Páginas / Governança / Editar". Durante o carregamento, usar skeleton, nunca
   segmento vazio. Revisar todos os breadcrumbs do painel.
4. **Cabeçalho das telas.** Criar um componente `PageHeader` com o título e o badge
   (Publicada/Rascunho) centralizados verticalmente em relação ao título, e usá-lo em todas as
   telas.
5. **Fonte.** Source Sans 3, auto-hospedada (woff2, latin + latin-ext), em todo o painel.
   Títulos continuam em Poppins. Nenhuma fonte com serifa no painel.
6. **Ícones.** `lucide-vue-next`, importando só os ícones usados. Aplicar em itens do menu,
   botões (Filtrar, Limpar, Sair, Salvar, Abrir no site etc.), cards da tela Início e status.
   Tamanho entre 18 e 20px, alinhados ao texto, com `aria-hidden`.
7. **Responsividade completa.**
   - Abaixo de 1024px, o menu lateral vira drawer com botão hambúrguer, e a barra superior
     mantém usuário e Sair.
   - Abaixo de 768px, as tabelas viram lista de cards (nome, data/hora, status, indicador de
     não lido).
   - Filtros empilhados em largura total.
   - Barra do editor com rolagem horizontal e botão Salvar fixo no rodapé.
   - Áreas de toque de no mínimo 44px e nenhuma rolagem horizontal na página.
   - Validar com screenshots Playwright em 360, 390, 768, 1024 e 1440px, nas telas Início,
     listagem, detalhe e edição de página.

## Entrega

Commit ao final de cada item. Relatório final com os screenshots.
