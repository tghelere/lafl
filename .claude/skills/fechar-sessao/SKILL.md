---
name: fechar-sessao
description: "Checklist obrigatório antes de encerrar qualquer sessão de trabalho neste projeto. Use sempre que terminar uma etapa, antes de dizer que o trabalho está concluído, ou quando o usuário pedir para fechar/finalizar. Garante que nada fica solto na árvore, sem debug esquecido e com a documentação em dia."
---

# Fechamento de sessão

Trabalho não commitado é trabalho perdido. Execute este checklist **na ordem**, sem pular
etapa, antes de afirmar que qualquer trabalho está concluído.

## 1. Remover código de depuração

```bash
grep -rn "console\.log\|console\.error.*DEBUG\|dd(\|dump(\|var_dump\|ray(" \
  backend/app backend/tests frontend-site/app frontend-admin/src 2>/dev/null
```

Qualquer resultado que você tenha introduzido nesta sessão precisa sair. `console.error` de
tratamento legítimo de erro pode ficar — depuração temporária, não.

Apague também scripts temporários criados em `/tmp` ou no scratchpad que não fazem parte da
entrega.

## 2. Verificação automatizada

```bash
cd backend
./vendor/bin/pint
./vendor/bin/phpstan analyse --memory-limit=512M
php artisan test
```

```bash
cd frontend-site && npm run build && npm run generate
cd ../frontend-admin && npm run build
```

Tudo verde. Se algo falhar, corrija antes de commitar — nunca commite com suíte vermelha.

## 3. Conferência visual

Se houve mudança de interface e o MCP de navegador estiver disponível, abra as páginas
afetadas, confira que renderizam e que os fluxos interativos funcionam. Registre no relatório
o que você conseguiu verificar e o que não conseguiu.

Se não houver navegador disponível, diga isso explicitamente — nunca afirme que algo está
visualmente correto sem ter visto.

## 4. Documentação

- `docs/roadmap.md` atualizado: o que foi feito, o que ficou pendente, o que você anotou pelo
  caminho
- ADR em `docs/decisoes/` para qualquer decisão de arquitetura tomada nesta sessão
- `docs/contexto.md` atualizado se algum dado institucional foi confirmado ou corrigido

## 5. Commit

Commits separados por escopo — documentação, backend e frontend não vão no mesmo commit.
Português, Conventional Commits, com corpo explicando o porquê quando não for óbvio.

```bash
git status --short
```

**A árvore precisa terminar limpa.** Se `git status` retornar qualquer coisa, o trabalho não
está fechado.

## 6. Relatório

Se a sessão foi longa ou autônoma, escreva `docs/relatorio-sessao-N.md` com:

- O que foi entregue, por etapa
- Decisões tomadas sem consulta, e o motivo de cada uma
- O que ficou pendente
- O que precisa de conferência humana no navegador

## Regra final

Nunca diga que o trabalho está concluído sem ter rodado `git status --short` e visto a saída
vazia. Se você não rodou, não está concluído.
