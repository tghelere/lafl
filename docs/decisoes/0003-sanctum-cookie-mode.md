# 0003 — Sanctum em cookie mode (SPA) em vez de token Bearer

## Contexto

O painel administrativo trafega dado de formulário recebido e, na Fase 2, dado de assistido
— titular hipervulnerável (`CLAUDE.md`). O mecanismo de autenticação da API precisa minimizar
a superfície de roubo de sessão nesse cenário, especificamente XSS no SPA Vue.

## Decisão

Laravel Sanctum em **SPA mode**: cookie de sessão `httpOnly`, `SameSite=Lax`, com proteção
CSRF via `GET /sanctum/csrf-cookie` + header `X-XSRF-TOKEN`. Fluxo completo documentado em
`docs/arquitetura.md`.

Um token roubado por XSS em modo Bearer dá ao atacante acesso à API por todo o prazo de
validade do token, de qualquer origem, sem precisar continuar explorando o navegador da
vítima. Com cookie `httpOnly`, o JavaScript malicioso nunca lê o valor da sessão — o risco
residual que sobra é CSRF, mitigado pelo par cookie+header que só funciona vindo da mesma
origem.

## Alternativas descartadas

- **Token Bearer (Sanctum API token ou JWT) em `localStorage`.** Legível por qualquer script
  injetado via XSS — inclusive por uma extensão de navegador maliciosa, cenário já registrado
  como incidente real neste projeto (ver commit `7ebd559`, onde uma extensão bloqueando
  `localStorage` deixou o painel em branco). Guardar a credencial de sessão ali soma um risco
  de exfiltração ao risco de indisponibilidade já observado.
- **Token Bearer em `sessionStorage`.** Mesma exposição a XSS que `localStorage`; a única
  diferença é o escopo de aba, que não muda o vetor de ataque.

## Consequências

- Front e API precisam compartilhar domínio raiz (`dominio.org.br` e `api.dominio.org.br`),
  porque cookie não atravessa domínios distintos — consequência de infraestrutura já
  registrada em `docs/arquitetura.md` e pendente em `docs/contexto.md` (domínio ainda não
  registrado).
- Chamada SSR do Nuxt para endpoint autenticado precisaria repropagar o cookie da requisição
  original; o site público evita o problema consumindo só endpoints públicos (Parte 3 de
  `docs/estrutura-site.md`), que não exigem sessão.
- CORS precisa `supports_credentials: true` restrito aos domínios do front — qualquer origem
  adicional (preview de PR, por exemplo) exige entrada explícita, não wildcard.
