# Sessão 33 — Levar as fotos do ambiente local para a homologação

Pedido: a homologação não tem nenhuma imagem nas páginas, e o ambiente local tem. Levar só as
fotos (registros da biblioteca, originais, derivadas e vínculos com páginas), sem deploy, sem
tocar em texto, usuários, formulários nem outra tabela, com backup antes e restauração se a
verificação falhar.

| Item | Commit |
|---|---|
| `midia:importar`, `levar-fotos.sh` e deploy.md §10.2 | `0e7128a` |

## Situação: importação em homologação NÃO executada

A primeira tentativa de SSH (`deploy@homologacao-laf.softhing.com.br`) parou em "Host key
verification failed", antes de qualquer autenticação: o `known_hosts` conhece o servidor pelo
IP (`2.25.223.146`), não pelo nome. A tentativa seguinte, pelo IP, foi barrada pela camada de
permissões do Claude Code (execução remota sensível) e não chegou a sair da máquina. Nada foi
feito no servidor, nem leitura: o estado real da homologação (se as cinco páginas existem pelo
slug, se `midia:importar-fotos-iniciais` rodou lá) não foi conferido.

Tudo o que dependia só da máquina local ficou pronto e testado. Falta rodar um comando (§10.2
de `docs/deploy.md`):

```bash
SITE_AUTH='homologacao:SENHA' backend/scripts/midia/levar-fotos.sh --ambiente staging \
  --host 2.25.223.146 --api https://api.homologacao-laf.softhing.com.br \
  --site https://homologacao-laf.softhing.com.br
```

## O que o ambiente local tem

- **24 imagens** na biblioteca, todas com `origin_key` (o catálogo inicial de
  `midia:importar-fotos-iniciais`), **nenhuma marcada como foto de criança ou adolescente
  atendido**. Nenhuma fica de fora por esse motivo.
- **96 arquivos**: 24 originais e 72 derivadas `.webp`. A pasta local de mídia tem 281 MB porque
  guarda sobras de imagens já excluídas em testes manuais; o pacote leva só os 12 MB das 24.
- **19 vínculos** em 5 páginas: `bazar` (capa + 4 na galeria), `educacao-infantil` (capa + 4),
  `quem-somos` (capa + 1), `quem-somos/nossa-historia` (5 na galeria), `transparencia` (2 na
  galeria).
- **Nenhuma foto dentro do texto.** Nenhuma das 26 páginas locais cita `/midia/…` nem tem
  `<img>`. O pedido falava em fotos dentro do texto, mas no banco local elas não existem.

## O que foi feito

1. **`midia:importar {pacote}`** (`App\Actions\Media\ImportPageMediaPackage`). Lê o pacote de
   `conteudo:exportar`, com a mesma conferência de manifesto e SHA-256 de `conteudo:importar`.
   Para isso `readAndVerify` e `writeMedia` de `ImportContentPackage` passaram a ser públicos.
   Grava só `media`, os arquivos e `page_images`.
   - Página casa pelo **slug**. Página do pacote sem par é listada e não é criada.
   - A imagem mantém o **uuid** da origem. O texto cita foto por `/midia/{uuid}`, não por id
     sequencial, então o remapeamento de referências pedido é a identidade. O texto não é
     gravado, e o teste confere `content`, `uuid` e `updated_at` das páginas intactos.
   - Nunca sobrescreve: imagem com o mesmo uuid é mantida, e página que já tem capa ou galeria é
     mantida inteira.
   - Foto inicial que já existe com outro uuid (`midia:importar-fotos-iniciais` rodou no
     destino) recusa o pacote inteiro, sem escrever.
   - `--simular` não escreve. `--verificar` confere cada imagem no banco, cada arquivo no disco
     com o SHA-256 do pacote, e cada página com foto na origem com exatamente a mesma capa e
     galeria no destino.
   - A simulação lista também texto que cita imagem inexistente no destino.
2. **`backend/scripts/midia/levar-fotos.sh`**: exporta do local, faz rsync para `/var/tmp`,
   `pg_dump --data-only` de `media` e `page_images` mais a lista de pastas de mídia, simula,
   importa, confere a contagem esperada (antes + novas), roda `--verificar` e pede uma imagem
   pela API e pelo site. Se algo falhar depois da importação, restaura as duas tabelas numa
   transação e apaga só as pastas que a execução criou. Usa uma conexão SSH só
   (`ControlMaster` por linha de comando) e para na primeira falha de conexão.
3. **`docs/deploy.md` §10.2.**

## Decisões tomadas sem consulta

1. **Comando novo em vez de `conteudo:importar`.** Sem `--substituir` ele pularia as páginas
   (que já existem em homologação) junto com as fotos delas. Com `--substituir`, reescreveria o
   texto.
2. **Mesmo pacote, não um formato novo.** O pacote de `conteudo:exportar` já traz imagens e
   vínculos com checagem. Ele também leva os PDFs de transparência locais (sintéticos, do
   seeder). `midia:importar` os confere e não os grava, e o script apaga o pacote do servidor
   no fim.
3. **Página com foto na origem e sem par no destino reprova `--verificar`.** O pedido exige o
   vínculo de cada página que tinha foto.
4. **Backup em `/var/tmp/laf-midia-backup-<carimbo>/` (modo 700)**, e não em
   `/var/backups/laf/`, que é do root. O usuário `deploy` não escreve lá, e o pedido proíbe
   mudar a configuração da VPS.

## Testes

- `tests/Feature/Console/PageMediaPackageTest.php`, 8 testes: imagens e vínculos chegam pelo
  slug com texto e uuid das páginas intactos, o site público serve a imagem e a API devolve capa
  e galeria na ordem; a foto de assistido não viaja; página sem par é listada; rodar de novo
  não duplica; página com galeria é mantida; `--simular` não escreve; conflito de `origin_key`
  recusa tudo; o cache público é invalidado; `--verificar` falha antes, falha com página sem
  par, passa depois e pega um arquivo que sumiu do disco.
- Contra o banco local, com o pacote real: `--verificar` passa e `--simular` mostra 24 mantidas
  e 5 páginas mantidas.
- Suíte inteira: 612 testes passando. Pint limpo e PHPStan sem erros nos arquivos de `app/`.
- **O script `levar-fotos.sh` não foi executado.** Só passou em `bash -n`. A parte remota
  (pg_dump, psql e restauração) roda pela primeira vez quando for usada em homologação. Por
  isso ela faz backup antes de escrever e restaura se falhar.

## Pendências

- Rodar `levar-fotos.sh` em homologação (comando acima).
- Nada foi enviado ao remoto. Um push dispararia deploy.
