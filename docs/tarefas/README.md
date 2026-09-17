# Tarefas — revisão de 17/09/2026

Uma sessão do Claude Code por arquivo, **na ordem abaixo** (não é a ordem numérica: a
homologação vem antes de SEO, política e documentação, para a instituição começar a testar o
quanto antes). Cada arquivo diz o modelo recomendado na primeira linha. Para rodar: abrir o
Claude Code na raiz do repositório, selecionar o modelo e enviar `Execute docs/tarefas/NN-nome.md`.

| Ordem | Arquivo | Modelo |
|---|---|---|
| 1 | `01-conteudo-e-escopo-de-lancamento.md` (concluída — sessão 11) | Sonnet |
| 2 | `02-numeros-calculados.md` | Opus |
| 3 | `03-alinhamento-visual.md` | Sonnet |
| 4 | `04-marca-e-credito-softhing.md` | Sonnet |
| 5 | `05-correcoes-de-codigo.md` | Sonnet |
| 6 | `07-caminho-ate-a-producao.md` | Opus |
| 7 | `07b-servidor-homologacao-e-deploy-automatico.md` | Opus |
| — | *instituição testa em homologação; ajustes pedidos entram como tarefas novas* | — |
| 8 | `06-seo-e-pdfs-da-transparencia.md` | Opus |
| 9 | `08-politica-de-privacidade.md` | Opus |
| 10 | `09-documentacao.md` | Sonnet |

## Regras que valem para todas

- Escopo já está definido: execute do início ao fim, sem pedir aprovação de plano e sem parar no
  meio. Em dúvida real sobre dado pessoal ou regra inviolável do `CLAUDE.md`, escolha a opção
  mais restritiva, registre a dúvida no relatório e siga.
- Commit ao fim de **cada etapa** numerada, em português, Conventional Commits. Nunca deixar a
  etapa seguinte começar com árvore suja.
- Encerrar com o checklist de `.claude/skills/fechar-sessao/`.
- Reportar no fim: o que foi feito (com os commits), decisões tomadas sem consulta, o que ficou
  de fora e o que precisa de conferência humana no navegador.
- O arquivo da tarefa entra no primeiro commit da sessão (registro do escopo pedido).
