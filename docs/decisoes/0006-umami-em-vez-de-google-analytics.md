# 0006 — Umami em vez de Google Analytics

## Contexto

O site precisa medir conversão (formulário enviado, clique em doação/PIX, clique no
WhatsApp, download de documento de transparência — `docs/estrutura-site.md`, Parte 3) sem
comprometer o compromisso maior do projeto: transparência como estratégia reputacional
(`docs/contexto.md`), não apenas conformidade formal. Um site que exibe banner de cookies por
causa da própria ferramenta de analytics manda o sinal errado para o público que ele mais
precisa reconquistar.

## Decisão

Umami Cloud (plano Hobby), documentado em `docs/arquitetura.md`. Cookieless por design — sem
cookie, sem `localStorage`, identidade por hash salgado rotativo no servidor — o que dispensa
banner de consentimento sob a leitura de que não há rastreamento entre sessões nem
identificação persistente do visitante.

Eventos personalizados a instrumentar: doação/PIX, envio de cada um dos seis formulários,
clique no WhatsApp, download de documento de transparência.

## Alternativas descartadas

- **Google Analytics (GA4).** Usa cookie e identificador persistente entre sites via a rede
  de publicidade do Google — exige banner de consentimento sob a LGPD, e o CLAUDE.md já veda
  essa combinação explicitamente ("Sem GA4, sem banner de cookies"). Também levaria dado de
  visitante de um site institucional infantil para fora do controle direto da instituição.
- **Plausible ou Fathom.** Também cookieless e compatíveis com o requisito, mas sem
  justificativa para trocar — Umami já estava adotado e configurado no scaffold do site
  público (commit `f712209`) sem custo de migração a justificar aqui.
- **Sem analytics algum.** Inviabilizaria medir se a estratégia de transparência está
  funcionando — o próprio objetivo do redesign (`docs/contexto.md`) depende de saber o que
  está sendo encontrado e clicado.

## Consequências

- Se algum dia entrar cookie não essencial no site (ex.: chat de terceiro), o banner de
  consentimento volta à pauta — e precisa ser tão visível quanto o aceite, com recusa igualmente
  fácil (`docs/arquitetura.md`).
- Retenção de dado do plano Hobby é 6 meses; se isso apertar, o plano de migração é
  auto-hospedar o Umami, não trocar de ferramenta.
- Google Search Console continua sendo a fonte de dado de busca orgânica — Umami não cobre
  esse eixo.
