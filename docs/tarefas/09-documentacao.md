> **Modelo recomendado: Sonnet**

# 09 — Documentação em dia

Leia `docs/tarefas/README.md`. Última tarefa: rode depois de todas as outras.

## Desatualizações apontadas na revisão

- `CLAUDE.md`, "Estado do projeto": "Projeto em fase inicial — nada foi implementado ainda" e
  "domínio ainda não registrado" como único contexto.
- `README.md`: "Estado atual" descreve só a fatia de autenticação; "suíte Pest inteira (30/30)";
  "CLAUDE.md fixa Nuxt 3" (hoje fixa Nuxt 4); "Pendências conhecidas" desatualizadas.
- `docs/roadmap.md`: "painel administrativo não existe"; "seis formulários" onde são cinco;
  "escolha de hospedagem ainda não está fechada" (Hostinger contratada pelo cliente — plano a
  confirmar); "28 páginas"; o que as tarefas 01 a 08 resolveram ainda como pendente.
- Comentários de código que as tarefas anteriores não tenham alcançado.

## Etapas

1. `CLAUDE.md`: estado real em poucas linhas; acrescentar às regras de conteúdo que o site não
   menciona o processo de 2022 e que idade e contagem são sempre calculadas (marcadores da
   tarefa 02), com o porquê.
2. `README.md`: estado atual, contagens atuais de testes (rodar e copiar o número real),
   remover afirmações falsas, apontar para `docs/deploy.md`.
3. `docs/roadmap.md`: reorganizar por estado real — concluído, pendente de lançamento,
   pós-lançamento, Fase 2 —, sem histórico de sessão dentro dos itens (isso mora nos relatórios).
4. Conferir `docs/estrutura-site.md` e `docs/design/navegacao.md` contra o menu e as páginas
   reais depois das tarefas 01 e 04.
5. Apagar `docs/tarefas/` num commit próprio (os relatórios de sessão preservam o registro).

## Verificação

Toda afirmação numérica ou de estado conferida contra o repositório (rodar testes, contar
arquivos, abrir telas). Listar no relatório o que foi conferido e como.
