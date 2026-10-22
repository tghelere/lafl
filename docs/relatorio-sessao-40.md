# Relatório da sessão 40 — migração para São Paulo, Etapa 2: verificação do backup

Etapa 2 de 6. O objetivo era provar, **antes** da reinstalação do VPS de Boston, que o backup da
Etapa 1 (`docs/relatorio-sessao-39.md`, `~/backups/laf/laf-backup-etapa1-20261022-051124/`)
restaura por completo e que os dados cifrados abrem com as chaves que estão nele.

No servidor (`ssh sysadmin@2.25.223.146` + `sudo -n`) só houve leituras e a cópia da release do
passo 6, que veio por fluxo direto no SSH, sem gravar arquivo temporário lá. Ao final, os sete
serviços continuavam `active`, não havia nenhum `/var/tmp/laf-backup-*` e o site respondia
200. Os bancos e containers locais de desenvolvimento não foram tocados: o Postgres
descartável usou outro nome, outra porta (`127.0.0.1:55432`) e nenhum volume nomeado.

Este relatório traz só contagens e resultados, nenhum valor de campo.

## 1. Integridade dos arquivos

`sha256sum -c SHA256SUMS`: **19/19 OK** no início da sessão. Depois do passo 6: **20/20 OK**.

## 2. Restauração em Postgres 16 descartável

- Container `postgres:16-alpine` (PostgreSQL **16.15**, a mesma versão do servidor),
  publicado só em `127.0.0.1:55432`, com o backup montado somente leitura.
- `pg-globals.sql`: aplicado sem erro (o único aviso, "role postgres already exists", é
  esperado e foi filtrado).
- `pg_restore --exit-on-error` de `db-lar_analia_franco_staging.dump` em banco do dono
  `laf_staging`: **código de saída 0, nenhum erro**.
- A aplicação conectou como `laf_staging` com a **senha do `.env` do backup**. Isso prova que o
  papel e o hash de senha vieram certos no `pg-globals.sql`.

## 3. Contagem de registros, banco restaurado × banco vivo

Consulta somente leitura no servidor às 05:19 UTC (8 minutos depois do backup).

| Tabela | Restaurado | Vivo | |
|---|---:|---:|---|
| activity_log | 196 | 196 | = |
| cache | 0 | 0 | = |
| cache_locks | 0 | 0 | = |
| contact_messages | 3 | 3 | = |
| failed_jobs | 0 | 0 | = |
| job_batches | 0 | 0 | = |
| jobs | 0 | 0 | = |
| media | 32 | 32 | = |
| migrations | 29 | 29 | = |
| model_has_permissions | 0 | 0 | = |
| model_has_roles | 12 | 12 | = |
| page_images | 26 | 26 | = |
| page_slug_history | 0 | 0 | = |
| pages | 26 | 26 | = |
| partners | 1 | 1 | = |
| partnership_inquiries | 0 | 0 | = |
| password_reset_tokens | 0 | 0 | = |
| permissions | 0 | 0 | = |
| personal_access_tokens | 0 | 0 | = |
| pickup_requests | 0 | 0 | = |
| program_applications | 0 | 0 | = |
| role_has_permissions | 0 | 0 | = |
| roles | 7 | 7 | = |
| sessions | 0 | 0 | = |
| transparency_documents | 18 | 18 | = |
| users | 7 | 7 | = |
| volunteer_applications | 0 | 0 | = |

**27/27 tabelas iguais, nenhuma diferença a explicar.** `sessions` está vazia nos dois porque a
sessão vive no Redis; nada se perde com isso além de obrigar quem estiver logado a entrar de
novo.

## 4. Dados cifrados e blind indexes

### Como foi montado

- O código veio de `git archive 3e2eb33 backend`, num diretório temporário fora do repositório.
- O `vendor/` veio da release copiada no passo 6. `composer.lock`, `app/` e `config/` do
  checkout são idênticos aos da release, então o código conferido é o que está no ar.
- O `.env` usado foi uma cópia de `env/backend.env` com estas chaves alteradas:
  - `DB_PORT` (para o container; `DB_HOST` já era `127.0.0.1`);
  - `QUEUE_CONNECTION=sync`;
  - `CACHE_STORE=array` e `SESSION_DRIVER=array`;
  - `REDIS_PORT=1`, uma porta morta, para nada alcançar o Redis local;
  - `LOG_CHANNEL=null`.
- Outras chaves já estavam com valor inofensivo: `MAIL_MAILER` já era `log` e
  `TELESCOPE_ENABLED` já era `false`.
- O script não aciona scheduler, fila nem nenhum serviço externo, e a sessão de banco rodou
  em modo `read_only`.
- Conferido por hash de cada linha: `APP_KEY`, `FIELD_ENCRYPTION_KEY`,
  `FIELD_ENCRYPTION_PREVIOUS_KEYS`, `BLIND_INDEX_KEY`, `BLIND_INDEX_PREVIOUS_KEYS`,
  `DB_DATABASE`, `DB_USERNAME` e `DB_PASSWORD` ficaram **inalteradas**.

### Qual chave cifra o quê

O dado pessoal dos formulários **não** é cifrado com o `APP_KEY`. Ele usa o cast
`App\Casts\FieldEncrypted` (AES-256-GCM) com a `FIELD_ENCRYPTION_KEY`, que é separada de
propósito (`docs/protecao-de-dados.md`).

O `APP_KEY` só cifra `users.two_factor_secret` e `two_factor_recovery_codes` (cast `encrypted`),
e **os dois estão vazios nos 7 usuários**. Além disso, ele assina cookies e sessões.

Logo, o que precisa abrir os dados do banco é a `FIELD_ENCRYPTION_KEY`, e foi ela que este teste
exercitou. O `APP_KEY` só precisa ser o mesmo para as sessões continuarem válidas.

As três chaves têm 32 bytes. Não há chaves anteriores configuradas.

### Decifragem, todos os registros, todos os campos

Os modelos e campos cifrados foram descobertos por introspecção dos casts de cada modelo em
`app/Models`, não por lista escrita à mão.

| Modelo | Registros | Campos cifrados | Preenchidos | Decifrados | Resultado |
|---|---:|---|---:|---:|---|
| ContactMessage | 3 | name, email, message, internal_note | 9 (internal_note vazio) | 9, todos com a chave ativa | ok |
| PartnershipInquiry | 0 | tax_id, contact_name, phone, email, internal_note | 0 | 0 | ok (vazio) |
| PickupRequest | 0 | donor_name, phone, address, internal_note | 0 | 0 | ok (vazio) |
| ProgramApplication | 0 | guardian_name, phone, internal_note | 0 | 0 | ok (vazio) |
| VolunteerApplication | 0 | name, phone, email, message, internal_note | 0 | 0 | ok (vazio) |
| User | 7 | two_factor_secret, two_factor_recovery_codes (APP_KEY) | 0 | 0 | ok (vazio) |

**Controle negativo:** o mesmo código, com uma `FIELD_ENCRYPTION_KEY` aleatória, recusou 9 de 9
campos. Isso mostra que o "ok" acima não é um teste que passa com qualquer chave.

### Blind indexes

- **`partnership_inquiries.tax_id_hash`**, o único blind index declarado
  (`$blindIndexes`): **0 registros, não aplicável**. Não há amostra para recalcular nem buscar.
- **`ip_hash`** (5 tabelas de formulário, HMAC com a `BLIND_INDEX_KEY`): **não verificável por
  construção**, porque o IP de origem não é guardado em lugar nenhum. As únicas linhas são as 3
  de `contact_messages`, de 18/09. Os logs do Nginx no servidor só vão até 08/10, então não há
  fonte para recalcular. Não é preciso desfazer isso: esse índice só serve para limitar
  envios repetidos num período curto.
- O que dá para afirmar da `BLIND_INDEX_KEY`: ela tem 32 bytes e é **byte a byte a mesma** do
  servidor vivo (o `.env` do backup tem o mesmo sha256 do original, conferido na Etapa 1). Nos
  dados de hoje, nenhum registro depende dela para ser *lido*.

## 5. Storage

### Backup extraído × servidor vivo

| Pasta | Arquivos (backup / vivo) | Bytes (backup / vivo) | |
|---|---|---|---|
| storage/app/private/media | 134 / 134 | 17 361 173 / 17 361 173 | = |
| storage/app/private/transparency-documents | 18 / 18 | 16 147 046 / 16 147 046 | = |
| storage/app/public | 0 / 0 | 0 / 0 | = |
| storage/framework/cache | 0 / 0 | 0 / 0 | = |
| storage/framework/sessions | 0 / 0 | 0 / 0 | = |
| storage/framework/views | 24 / 24 | 56 090 / 56 090 | = |
| storage/logs | 1 / 1 | 15 296 / 15 296 | = |

Nenhum arquivo em `storage/app` foi criado ou alterado no servidor depois das 05:11:24 UTC.

### Banco restaurado × storage extraído

O caminho de cada mídia foi derivado como faz `App\Support\Media\MediaPaths`:
`media/{uuid}/{version}/original.{ext}` mais um `{largura}.webp` por item de `widths`. Os
documentos usam `transparency_documents.file_path`.

| Conferência | Resultado |
|---|---|
| Registros | 32 `media`, 18 `transparency_documents` (nenhum com exclusão lógica) |
| Arquivos esperados pelo banco / presentes no storage | 152 / 152 |
| Registro → arquivo ausente | **0** |
| Arquivo → sem registro (órfão) | **0** |
| sha256 da original ≠ `media.sha256` | **0** |
| Tamanho ≠ o gravado no banco (`media.size`, `transparency_documents.file_size`) | **0** |

## 6. Plano B da Etapa 4: release atual

- **Arquivo novo na pasta do backup:** `release-20261020-201933-3e2eb33.tar.gz`, com
  14 069 137 bytes (96 MB descompactado, 9 054 entradas).
- **sha256:** `d09cb99024f35c2dbd17843c46a03a426a983609d3b2e323c965f3877baf66f2`.
- **Como veio:** `tar -czf -` no servidor, por fluxo direto no SSH. O sha256 foi calculado do
  lado do servidor sobre o mesmo fluxo (`tee >(sha256sum)`) e bateu com o calculado no arquivo
  local.
- **SHA256SUMS:** a linha foi acrescentada e o arquivo agora confere 20/20.
- **Metadados:** o `RELEASE` interno registra o commit
  `3e2eb33deb5bfc7cc4738909211577b6f5db05b9`, gerado em 2026-10-20T20:19:12Z com PHP 8.5.11
  e Node v24.21.0.
- **Links simbólicos:** os três (`backend/.env`, `backend/storage`, `painel/config.js`) vieram
  como links, apontando para `shared/`. O tar não carrega segredo nenhum além do que já está em
  `env/`.
- **Não copiadas:** as outras quatro releases.
- **MANIFESTO.md** não foi alterado; ele continua descrevendo o conteúdo da Etapa 1, e a release
  está registrada aqui e no `SHA256SUMS`.

## 7. Limpeza

- Container `laf-etapa2-pg` removido com o volume anônimo (`docker rm -fv`); a porta 55432
  está livre.
- Removidos do scratchpad: o checkout, a cópia do `.env`, o storage extraído, os trechos da
  release, as listas exportadas do banco e os scripts. O diretório está vazio.
- Pasta do backup: todos os diretórios em `700`, todos os arquivos em `600`, inclusive
  `~/backups` e `~/backups/laf`. Total de 82 MB.
- Os 6 containers do ambiente de desenvolvimento local continuaram rodando, intocados.

## Observações para as próximas etapas

- **O que abre os dados é a `FIELD_ENCRYPTION_KEY`.** No servidor novo, as três chaves do `.env`
  precisam ir exatamente como estão. Uma `FIELD_ENCRYPTION_KEY` diferente torna as 3 mensagens
  de contato ilegíveis, sem erro no deploy.
- Quem estiver logado no painel na hora da troca terá de entrar de novo (sessões no Redis não
  migram).
- Os blind indexes não puderam ser exercitados com dado real (seção 4). Não há risco hoje,
  porque nenhum registro depende deles, mas vale um envio de teste do formulário de parceria
  depois da Etapa 4 para exercitar `tax_id_hash` de ponta a ponta no servidor novo.

## Veredito

**ETAPA 2 APROVADA — PODE REINSTALAR.**

O banco restaura sem erro, com as 27 contagens iguais às do servidor vivo. Todo campo cifrado
preenchido decifra com a chave do backup, e o controle negativo confirma que o teste
discrimina. O storage é idêntico ao do servidor, sem nenhum órfão nas duas direções, com
sha256 e tamanhos batendo com o banco. A release atual está guardada e conferida.

A única ressalva é a dos blind indexes: não houve dado real para recalcular (0 registros com
`tax_id_hash`, e o IP de origem do `ip_hash` não é guardado em lugar nenhum). Ela não reprova a
etapa, porque nenhum dado deixa de ser legível por causa disso.
