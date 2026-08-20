# 0005 — Nuxt 4 em vez de Nuxt 3

## Contexto

`CLAUDE.md` fixa Nuxt 3 como stack do site público. Ao fazer o scaffold do `frontend-site/`
(commit `f712209`, Sessão 1), o `nuxi init` com template minimal instalou Nuxt 4.5.2 —
divergência sinalizada e confirmada com o solicitante antes de prosseguir, não decidida
unilateralmente. Este ADR registra formalmente essa decisão já tomada.

Nuxt 3 está sem patch de segurança há mais de um ano no momento do scaffold; fixar essa major
manteria o site público — a superfície com maior exposição do projeto, por ser pública e sem
autenticação — numa linha sem correção de vulnerabilidade futura.

## Decisão

Usar Nuxt 4 (versão estável atual), não Nuxt 3. Mesmo critério já aplicado ao Laravel no
projeto: versão estável atual mantida, não uma major específica fixada por documento que
antecede o scaffold real.

`docs/arquitetura.md` é atualizado por este ADR para refletir Nuxt 4 onde antes citava Nuxt 3.

## Alternativas descartadas

- **Fixar Nuxt 3 conforme `CLAUDE.md`.** Implicaria rodar em produção, desde o primeiro dia,
  uma major sem patch de segurança ativo — inaceitável para um site cuja API pública é a
  única porta de entrada de seis formulários com dado pessoal (`docs/estrutura-site.md`,
  Parte 2).
- **Fixar a versão exata usada no scaffold (`4.5.2`) sem trilhar atualizações de patch.**
  Contradiria o próprio motivo desta decisão — o problema original era ficar parado numa
  versão sem correção; congelar um patch específico reproduziria o mesmo risco em escala
  menor.

## Consequências

- `docs/arquitetura.md` passa a citar Nuxt 4 em vez de Nuxt 3.
- Diferenças de estrutura de pastas entre Nuxt 3 e 4 (`app/` como raiz de código-fonte) já
  estão refletidas no scaffold existente (`frontend-site/app/`); qualquer exemplo ou tutorial
  externo consultado durante o projeto que assuma layout de Nuxt 3 precisa ser adaptado.
- Este projeto não fixa major de frontend por documento estático; a versão real em uso é a
  fonte da verdade, e divergências como esta devem continuar sendo sinalizadas e confirmadas
  antes de prosseguir, não decididas silenciosamente.
