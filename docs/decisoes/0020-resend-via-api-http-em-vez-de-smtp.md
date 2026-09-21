# 0020 — Resend via API HTTP em vez de SMTP

## Contexto

O envio de e-mail transacional (notificação de formulário recebido, e futuramente "esqueci
minha senha" — ver `docs/roadmap.md`) precisava de um transporte real em homologação e
produção. Até a sessão 24, `MAIL_MAILER=log` era a pendência conhecida documentada em
`docs/deploy.md`: nenhum e-mail saía da máquina, só o conteúdo renderizado ia para
`storage/logs/laravel.log`.

## Decisão

Resend como provedor, via o transporte **nativo do Laravel para API HTTP**
(`resend/resend-php`) — nunca SMTP. `MAIL_MAILER=resend` e `RESEND_API_KEY` são as duas
variáveis que ligam o envio; o restante (`config('services.resend.key')`,
`config('mail.mailers.resend')`) já vinha pronto no skeleton do Laravel, sem código de
integração próprio do projeto. Passo a passo de verificação de domínio (SPF, DKIM, DMARC) em
`docs/deploy.md`, "E-mail (Resend)".

## Alternativas descartadas

- **SMTP genérico** (o que `.env.example` documentava antes desta sessão). Mais superfície de
  configuração (host, porta, usuário, senha, criptografia) e uma porta de saída a mais para
  abrir no `ufw`/provedor (587 ou 465, contra a 443 que já está liberada para HTTPS). Nenhuma
  vantagem concreta em troca, já que o volume de e-mail deste projeto é só notificação
  administrativa de formulário — não há caso de uso que precise de SMTP especificamente.
- **SES ou Postmark**, também suportados nativamente pelo mesmo `config/mail.php`. Não há
  motivo de projeto para preferi-los a Resend — a escolha aqui é a que a tarefa que originou
  esta sessão já direcionava, e o custo de trocar depois é baixo: é o mesmo padrão de mailer
  do Laravel, só o driver muda.

## Consequências

- Verificação de domínio (SPF/DKIM/DMARC) é um passo manual, único por ambiente, que só quem
  administra o DNS da instituição pode fazer — não há como automatizar isso em
  `criar-ambiente.sh`. Enquanto não for feito, homologação e produção continuam em
  `MAIL_MAILER=log`.
- Uma chave de API por ambiente (nunca reaproveitada entre staging e produção), mesma
  disciplina de `FIELD_ENCRYPTION_KEY`/`BLIND_INDEX_KEY` — revogar uma não deve afetar o
  outro.
- Subdomínio de envio dedicado (`envio.SEUDOMINIO`, nunca o domínio raiz) isola a reputação de
  entrega do domínio de envio da do domínio principal.
