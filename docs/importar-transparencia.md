# Importar documentos de transparência em lote

`php artisan transparencia:importar` cria documentos de transparência a partir de uma pasta
com PDFs e um `manifesto.json`, sem passar pelo painel um a um. Os próximos lotes usam o mesmo
comando.

**O comando só ADICIONA.** Não atualiza, não apaga e não sobrescreve documento nenhum, e não
abre, converte, comprime nem altera o conteúdo de nenhum PDF: o arquivo é publicado como a
instituição enviou. Dois PDFs de conteúdo idêntico com títulos diferentes são ambos criados.

## O que o comando faz

- Grava pelo mesmo caminho do painel (`SaveTransparencyDocument` + as regras de
  `StoreTransparencyDocumentRequest`): mesmo disco (`local`, `storage/app/private`), mesmo
  nome interno (`transparency-documents/<hash>.pdf`), mesmo slug de URL.
- **Sem `--executar` é simulação**: mostra a tabela e não grava nada.
- Antes de gravar o primeiro item, confere tudo: manifesto legível, `total` igual ao número de
  itens, todo arquivo presente, tipo conhecido e as regras do Request (PDF, até 20 MB, ano
  válido). Qualquer problema lista tudo e **aborta o lote inteiro sem gravar**.
- **Não duplica**: pula o item cujo **título + ano** já exista (inclusive na lixeira). Rodar duas
  vezes não cria nada novo.
- Grava numa transação; se um item falhar, desfaz o lote e apaga só os PDFs copiados naquela
  execução.

### Campos do manifesto

`{ lote, total, documentos: [ { arquivo, titulo, tipo, ano, data_documento, descricao, publicar } ] }`

O model `TransparencyDocument` tem **ano** (`year`), não data, e não tem descrição. Por isso:

| Campo do manifesto | Destino |
|---|---|
| `titulo` | `title` |
| `ano` | `year` |
| `tipo` | `type` (mapeamento abaixo) |
| `publicar` | publicado agora (`published_at`) ou rascunho |
| `data_documento` | só aparece na tabela do comando; **não é gravada** |
| `descricao` | **não é gravada** (o model não tem o campo) |

### Mapeamento de `tipo`

Nenhum valor novo de enum foi criado.

| Manifesto | Enum | Observação |
|---|---|---|
| `ata` | `minutes` (Ata) | exato |
| `edital` | `notice` (Edital) | exato |
| `termo_de_colaboracao` | `agreement_accounting` (Prestação de contas do convênio) | aproximado |
| `termo_aditivo` | `agreement_accounting` | aproximado |
| `prestacao_de_contas` | `agreement_accounting` | aproximado |
| `quadro_de_pessoal` | `agreement_accounting` | aproximado: o quadro é vinculado ao Termo de Colaboração |

Se a instituição quiser tipos próprios (por exemplo "Termo de colaboração"), isso exige
migration e valor novo de enum — fica para uma decisão à parte.

## Como rodar no servidor

O servidor tem `deploy` como usuário de publicação; o PHP-FPM do ambiente também roda como
`deploy`, então um PDF gravado pelo comando tem o mesmo dono e as mesmas permissões de um
enviado pelo painel. **Rode sempre como `deploy`**, nunca como root (o arquivo ficaria
ilegível para o site).

Nos exemplos, `<ambiente>` é `production` (ou `staging`). Troque `IP` pelo IP da VPS
(ver `docs/deploy.md` §0) e `~/.ssh/laf-deploy` pela chave de `deploy` que você usa.

### 1. Copiar a pasta do seu Windows/WSL para a VPS

A pasta de destino precisa ficar em `storage/app/importacao/<lote>/` do ambiente. Como
`backend/storage` de toda release é ligado ao `shared/storage` (que sobrevive aos deploys),
o caminho estável é:

```bash
# No WSL. Se a pasta está no Windows (ex.: C:\Users\voce\Downloads\transparencia-lote-01):
scp -i ~/.ssh/laf-deploy -r \
  /mnt/c/Users/SEU_USUARIO/Downloads/transparencia-lote-01 \
  deploy@IP:/var/www/laf/<ambiente>/shared/storage/app/importacao/
```

Se `importacao/` ainda não existir no servidor, crie antes (uma vez só):

```bash
ssh -i ~/.ssh/laf-deploy deploy@IP 'mkdir -p /var/www/laf/<ambiente>/shared/storage/app/importacao'
```

Confira que chegaram os PDFs (o número impresso deve ser 18) e o `manifesto.json` na mesma pasta:

```bash
ssh -i ~/.ssh/laf-deploy deploy@IP 'ls /var/www/laf/<ambiente>/shared/storage/app/importacao/transparencia-lote-01/*.pdf | wc -l'
```

### 2. Simulação (não grava nada)

```bash
ssh -i ~/.ssh/laf-deploy deploy@IP
cd /var/www/laf/<ambiente>/current/backend
php8.5 artisan transparencia:importar storage/app/importacao/transparencia-lote-01
```

Leia a tabela: título, tipo mapeado, data e status (`seria criado` / `já existia`). Se algo
estiver errado, o comando lista os problemas e diz "Nada foi gravado".

### 3. Execução

```bash
php8.5 artisan transparencia:importar storage/app/importacao/transparencia-lote-01 --executar
```

A tabela final mostra `criado` ou `já existia` para cada item. Rodar de novo é seguro: tudo
aparece como `já existia`. Confira em `/transparencia/documentos` no site e na tela de
documentos do painel.

### 4. Apagar a pasta depois

Só depois de conferir no site e no painel. Os PDFs importados já foram copiados para
`transparency-documents/`; a pasta de importação é só a origem.

```bash
rm -rf /var/www/laf/<ambiente>/shared/storage/app/importacao/transparencia-lote-01
```

(Se quiser limpar tudo do lote, apague também a cópia local no Windows, quando não precisar mais.)

## O deploy preserva os dados enviados

Conferido em `infra/publicar.sh`, `infra/criar-ambiente.sh` e `.github/workflows/deploy.yml`:

- `publicar.sh` troca `backend/storage` da release por um link para `shared/storage`: PDFs,
  imagens da biblioteca (`private/media/`), logs e a pasta `importacao/` sobrevivem a qualquer
  deploy. A release nova nunca recebe `storage/` do pacote.
- O `rsync --delete` do workflow atua só em `incoming/pacote/` (cópia do pacote), nunca em
  `shared/`. O expurgo de releases apaga só `releases/*`.
- O deploy roda `migrate --force` (aditivo) e **nunca** `db:seed` nem `migrate:fresh`.
- `storage/app/importacao/` não entra no pacote: está no `.gitignore` (e `empacotar.sh` sai de
  `git archive`).
- `TransparencyDocumentsSeeder` (PDFs de exemplo em branco) retorna sem fazer nada fora de
  `local`, `testing` e `e2e`; `ContentPagesSeeder` e `DevSuperAdminSeeder` têm guarda
  parecida. Nenhum script de deploy chama `db:seed` além do `RoleSeeder` do primeiro deploy.
