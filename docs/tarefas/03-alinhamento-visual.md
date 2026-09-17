> **Modelo recomendado: Sonnet**

# 03 — Alinhamento visual (site e painel)

Leia `docs/tarefas/README.md`. Problemas já apontados antes e não resolvidos; o incômodo é
grande — parece trabalho malfeito. A tarefa só termina com prova em navegador real e teste
automatizado que impeça a volta do defeito.

## Defeitos

1. **Header do site:** o primeiro item do menu fica desalinhado verticalmente dos demais.
2. **Breadcrumb:** itens em alturas diferentes (uns acima, outros abaixo).
3. **Painel:** inputs e selects com uma altura e botões ao lado com outra (barra de filtro das
   listagens, formulários).

## Causas já identificadas na revisão (confirmar e corrigir na raiz)

- `frontend-site/app/assets/css/base.css` tem a regra global `li + li { margin-top:
  var(--space-2); }`. Em qualquer lista em linha com `align-items: center` (menu do header,
  breadcrumb e provavelmente navegação de seção, painel do menu e paginação), do segundo item
  em diante ganha margem no topo e desce — o primeiro fica "mais alto". Corrigir escopando o
  ritmo vertical de listas ao conteúdo (`.prose`, `.page-content`) em vez de zerar item por
  item, e auditar toda lista com `display: flex`.
- `frontend-admin/src/assets/css/components.css`: `.btn` tem `line-height: 1` e padding
  próprio; inputs e selects herdam `font: inherit` (line-height do body) com outro padding, e o
  select nativo acrescenta altura própria. Criar tokens de altura de controle (ex.: pequeno para
  barra de filtro, padrão para formulário) aplicados igualmente a `.btn`, `input`, `select` e
  equivalentes, com `box-sizing` e `line-height` explícitos. Conferir se os formulários do site
  público têm o mesmo problema e aplicar a mesma solução.

Procurar outros casos da mesma família: ícone lucide em linha de texto (rodapé, `/contato`),
botão de CTA do header ao lado do menu, seta do gatilho do menu, cartões lado a lado.

## Etapas

1. Site: corrigir as causas, conferir no Firefox (desktop e 375 px) header, gaveta mobile,
   breadcrumb de página de primeiro e segundo nível, navegação de seção e rodapé.
2. Painel: tokens de altura, aplicar em todas as telas (login, Início, listagens de formulário,
   transparência, páginas, usuários, conta, definir senha). Conferir no Firefox.
3. Testes e2e de alinhamento, com tolerância de 1 px:
   - itens do menu do header com o mesmo centro vertical;
   - itens do breadcrumb com o mesmo centro vertical;
   - em cada barra de filtro e formulário do painel, inputs, selects e botões da mesma linha
     com a mesma altura.
   Provar que os testes pegam o defeito: reintroduzir a regra `li + li` global e o
   `line-height: 1` localmente, ver ficar vermelho, reverter (mesmo método da sessão 10).

## Verificação

Builds e lint dos dois frontends, bateria e2e completa. Anexar ao relatório as capturas do
Playwright MCP antes/depois do header, do breadcrumb e de uma barra de filtro.
