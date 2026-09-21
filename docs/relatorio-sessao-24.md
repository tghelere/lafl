# Sessão 24 — Formulário sem e-mail: causa, Resend e runbook de DNS

Ponto de partida: relato de que uma mensagem enviada pelo formulário de contato foi salva e
apareceu no painel, mas nenhum e-mail de notificação chegou. Seis tarefas: investigar a causa,
configurar o envio de verdade via Resend, garantir que a notificação continua sem dado
pessoal, garantir o worker no deploy, subir Mailpit em dev e documentar o runbook de DNS.

O resumo curto: o *código* de notificação já existia inteiro e correto desde a sessão 6
(`App\Actions\Forms\NotifyFormSubmissionReceived` → `App\Mail\FormSubmissionReceived`,
enfileirado, sem dado pessoal, testado). A causa do sintoma é de **configuração de ambiente**,
não de código — e o mesmo vale para fila/worker e para Mailpit, que já estavam corretos. O que
faltava de fato: o transporte de e-mail de verdade (Resend), um bug de timezone no horário do
e-mail, e a documentação do runbook de DNS.

| Etapa | Commit |
|---|---|
| 1 — investigação da causa | (este relatório) |
| 2 — Resend via API HTTP | `0d5ac62` |
| 3 — timezone America/Sao_Paulo no e-mail | `0d5ac62` |
| 4 — worker no deploy e falhas em log | já estava correto (nenhuma mudança) |
| 5 — Mailpit no docker-compose | já estava correto (nenhuma mudança) |
| 6 — runbook de DNS no `docs/deploy.md` | `c390b67` |

## Etapa 1 — Investigação: por que nenhum e-mail chegou

**O envio existe no código, e está certo.** As seis Actions de criação de formulário
(`app/Actions/Forms/Create*.php`) chamam `NotifyFormSubmissionReceived::handle()` depois do
`save()`, exceto no ramo de honeypot — confirmado por
`FormSubmissionNotificationTest::'honeypot disparado não enfileira e-mail nenhum'`. A Action
monta `App\Mail\FormSubmissionReceived` e enfileira com `Mail::to($recipient)->queue(...)`; o
destinatário vem de `config('forms.notification_recipients.<tipo>')`, um por tipo de
formulário. Não há nada a corrigir nesse caminho.

**A causa mais provável, dado o estado documentado do ambiente:** `MAIL_MAILER=log`.
`docs/deploy.md` §0 já registrava essa pendência conhecida de homologação antes desta sessão —
"produção", no sentido de ambiente `production` do projeto, **ainda não existe**
(`docs/deploy.md` §0: "só é criada quando o lançamento for decidido"). O ambiente real que
existe hoje é homologação, e o cenário descrito (mensagem salva, sem e-mail) é exatamente o
efeito de `MAIL_MAILER=log`: o Laravel processa o envio normalmente, grava o conteúdo
renderizado em `storage/logs/laravel.log` e nunca sai da máquina — sem erro, sem `failed_jobs`,
sem sintoma nenhum além de "o e-mail não chegou". `infra/criar-ambiente.sh` deixa as variáveis
de e-mail para preenchimento manual depois do provisionamento, e nada aborta o boot da
aplicação se ficarem com o padrão.

**Fila e worker, conferidos e descartados como causa:**
- `QUEUE_CONNECTION=redis` em homologação/produção exige worker — existe:
  `infra/modelos/laf-queue@.service` (`queue:work --tries=3 --max-time=3600`), habilitado por
  `criar-ambiente.sh` (`systemctl enable laf-queue@<ambiente>`) e reiniciado a cada deploy por
  `infra/publicar.sh` (`queue:restart` + `systemctl restart laf-queue@<ambiente>`, passo 8).
- `failed_jobs` existe desde a migration base (`0001_01_01_000002_create_jobs_table.php`).
  Se o worker estivesse fora do ar ou o job estivesse falhando, o sintoma seria diferente do
  relatado (mensagem apareceria pendente ou em `failed_jobs`, não silenciosamente "enviada e
  esquecida" — e é isso que aponta de volta para `MAIL_MAILER=log`, não para a fila).

**Conclusão:** a causa é configuração de transporte de e-mail nunca preenchida com um
provedor real, um estado já sinalizado como pendência conhecida antes desta sessão. As etapas
2 e 3 fecham essa pendência; as etapas 4 e 5 confirmam que worker e Mailpit já estavam
corretos.

## Etapa 2 — Resend via API HTTP (`resend/resend-php`)

`config/mail.php` já trazia o mailer `resend` pronto (padrão do skeleton do Laravel) e
`config/services.php` já lia `RESEND_API_KEY` — só faltava a dependência instalada:

```bash
composer require resend/resend-php   # v1.15.0
```

Nenhuma outra mudança de código: `Mail::to(...)->queue(...)` não muda com o transporte. A
diferença fica só no `.env` (`MAIL_MAILER=resend`, `RESEND_API_KEY`, `MAIL_FROM_ADDRESS`) —
documentado em `backend/.env.example` e em `docs/deploy.md`, "E-mail (Resend)" (etapa 6).
API HTTP em vez de SMTP: uma porta de saída a menos para abrir (443, já liberada, em vez de
587/465) e uma chave de API em vez de host/porta/usuário/senha.

## Etapa 3 — Notificação sem dado pessoal, e o bug de timezone

O conteúdo do e-mail (tipo, data/hora, link para o painel — nunca nome, contato ou mensagem)
e os destinatários configuráveis por tipo **já existiam**, desde a sessão 6, cobertos por
`FormSubmissionNotificationTest`. Não havia o que ajustar aí.

O que a revisão desta etapa encontrou: `NotifyFormSubmissionReceived` montava o horário com
`now()->format('d/m/Y H:i')` — `now()` sem timezone explícito usa `config('app.timezone')`, que
é `UTC` (`config/app.php`). O e-mail chegaria com o horário errado em 3 horas para quem lê na
sede da instituição. Corrigido para `now('America/Sao_Paulo')`, com teste novo que congela o
relógio em UTC e confere a conversão (`'horário do e-mail de notificação é sempre
America/Sao_Paulo...'`, em `FormSubmissionNotificationTest`).

## Etapa 4 — Worker no deploy, com reinício e falhas em log

Conferido, não alterado — já satisfeito antes desta sessão:

- **Reinício após cada deploy:** `infra/publicar.sh`, passo 8, chama `artisan queue:restart`
  e depois `systemctl restart laf-queue@<ambiente>`, sempre que uma release é publicada — não
  depende só do `--max-time=3600` do worker (que reinicia sozinho de hora em hora, mas não no
  momento do deploy).
- **Falha registrada em log:** é o comportamento padrão do `Illuminate\Queue\Worker` — toda
  exceção de job não tratada passa por `$this->exceptions->report($e)`
  (`Worker::runJob`/`handleJobException`), que cai no canal de log padrão da aplicação, e o
  job esgotado (`--tries=3`) é gravado em `failed_jobs`. Nada customizado precisou ser
  adicionado. Documentado o comando de conferência (`artisan queue:failed`) em
  `docs/deploy.md` §8.

## Etapa 5 — Mailpit em dev

Já existia em `docker-compose.yml` (serviços `mailpit`, portas de UI e SMTP configuráveis por
env) e documentado em `README.md`/`docs/arquitetura.md` desde antes desta sessão. Nenhuma
mudança.

## Etapa 6 — Runbook de DNS no `docs/deploy.md`

Nova subseção "E-mail (Resend)" em `docs/deploy.md` §4: por que API HTTP em vez de SMTP,
subdomínio de envio dedicado (nunca o domínio raiz — isola reputação de entrega), remetente
sugerido (`nao-responda@`), passo a passo de cadastro de SPF/DKIM/DMARC no DNS e verificação
do domínio no painel do Resend, e o lembrete de uma chave de API por ambiente (mesma razão de
nunca reaproveitar `FIELD_ENCRYPTION_KEY` entre staging e produção). `docs/deploy.md` §0 e a
pendência do roadmap (`docs/roadmap.md`, "esqueci minha senha por e-mail") atualizadas para
apontar Resend em vez de SMTP.

## Decisões tomadas sem consulta

- **Registrar a escolha do Resend como ADR** (`docs/decisoes/0020-resend-via-api-http-em-vez-
  de-smtp.md`), mesmo a tarefa já direcionando o provedor: é escolha de stack (mailer de
  produção, igual em espírito a `docs/decisoes/0006`, Umami x GA4) e futuras sessões
  precisariam do porquê de API HTTP em vez de SMTP e de Resend em vez de SES/Postmark.
- **Não mexer no worker/deploy nem no docker-compose** (etapas 4 e 5): a investigação
  confirmou que já atendiam ao pedido; abrir uma mudança sem necessidade seria o tipo de
  "correção" que a regra do projeto contra funcionalidade além do necessário existe para
  evitar.

## O que não foi verificado

Nenhuma mudança de frontend nesta sessão — `npm run build`/`npm run generate` dos dois
frontends não foram rodados, e não havia nada para conferir visualmente no navegador. Não foi
enviado um e-mail de verdade contra o Resend (não há domínio verificado nem chave de API
disponíveis neste ambiente de trabalho) — a etapa 2 foi validada por `Mail::fake()` na suíte
Pest, não por envio real; confirmar contra o Resend real é o primeiro passo depois do runbook
de DNS ser executado em homologação.

## O que ainda falta para o e-mail funcionar de verdade

Nada de código. O que falta é operacional, e só pode ser feito por quem administra o domínio
da instituição: escolher o subdomínio de envio, cadastrar os três registros DNS que o Resend
gerar e gerar a chave de API de homologação — o passo a passo está em `docs/deploy.md`,
"E-mail (Resend)". Sem isso, homologação continua em `MAIL_MAILER=log` e o sintoma relatado
continua reproduzível ali, por desenho (é o que garante que teste em homologação nunca escreve
para caixa de e-mail real, ver `MAIL_ALWAYS_TO`).
