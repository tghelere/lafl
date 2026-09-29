# 0023 — A partir do lançamento, o banco é a fonte de verdade do conteúdo; `InitialPages.php` é só semente

## Contexto

O texto das páginas institucionais nasceu em `App\Support\Content\InitialPages`, no repositório,
e era carregado por seeder (desenvolvimento) e por `conteudo:importar-inicial` (ambiente novo).
Desde que o painel ganhou o editor de páginas, quem escreve é a instituição, e o resultado vive
em `pages.content` no banco de homologação — junto dos documentos de transparência e dos PDFs
em `shared/storage`.

Isso deixou uma armadilha na troca de ambiente. O que `infra/criar-ambiente.sh` e
`infra/publicar.sh` fazem, conferido nos scripts:

- `createdb` cria o banco de produção **vazio**; `publicar.sh` roda só `migrate --force`, nunca
  `db:seed`;
- `install -d` cria `shared/storage/` **vazio**; `publicar.sh` só o liga à release.

Um ambiente novo nasce, portanto, sem nada do que foi editado. E o caminho óbvio para
preenchê-lo, `conteudo:importar-inicial`, entrega o texto do repositório — que é **anterior** às
edições feitas pelo painel. Produção subiria com o site na versão que a instituição já corrigiu.

## Decisão

**A partir do lançamento, o banco de produção é a fonte de verdade do conteúdo.**
`InitialPages.php` deixa de descrever o que está no ar e passa a ser apenas a **semente de um
ambiente vazio**: o que aparece num ambiente de desenvolvimento novo, num `migrate:fresh --seed`,
na bateria de e2e, ou num ambiente criado do zero sem pacote para importar.

Consequências, todas de propósito:

1. **`InitialPages.php` vai divergir do que está no ar, e isso é esperado.** Ninguém deve
   "atualizar o `InitialPages.php` para refletir o site" nem abrir PR para isso. A divergência não
   é dívida: é o sinal de que a instituição está usando o painel. Ele só muda quando a
   *semente* precisa mudar (uma página nova que todo ambiente vazio deve ter, um marcador de
   conteúdo novo).
2. **`ImportInitialPages` continua só criando o que não existe e nunca sobrescrevendo.** Isso
   não muda. Rodá-lo depois de o conteúdo estar no ar é inofensivo, mas em produção ele **não é
   usado** no lançamento: o conteúdo vem do pacote.
3. **O conteúdo atravessa ambientes por pacote**, com `conteudo:exportar` e `conteudo:importar`
   (procedimento em `docs/deploy.md` §10.1). O pacote leva páginas de todos os status, histórico
   de slugs, documentos de transparência e PDFs — e **não** leva usuários, formulários recebidos,
   auditoria, lixeira nem contador de download.
4. **A importação nunca apaga nem sobrescreve sem `--substituir`**, que pede confirmação
   interativa. Mesmo com o flag, o que o pacote não traz é preservado.
5. **Homologação deixa de ser espelho de produção** depois do lançamento. Para testar com texto
   real, o caminho é o inverso: exportar de produção e importar em homologação com `--substituir`.

## Alternativas descartadas

- **Restaurar o dump de homologação em produção.** É o caminho mais curto e o pior: traria
  usuários de teste, formulários preenchidos e log de auditoria, e as chaves de cifra do outro
  ambiente (o dado cifrado viraria lixo). O pacote existe para tornar isso desnecessário.
- **Manter `InitialPages.php` sincronizado com o que está no ar** (exportar do banco para o
  arquivo). Faria o repositório receber commit a cada edição da instituição e reintroduziria o
  texto de produção no controle de versão, sem ganho: o banco já tem backup diário.
- **Fazer o seeder de produção `updateOrCreate`.** Apagaria a edição da instituição a cada
  deploy; é exatamente o que `ImportInitialPages` foi escrito para não fazer.
- **Zip em vez de pasta.** O pacote é uma pasta comum, legível e conferível por `sha256sum`; zip
  acrescentaria uma extensão do PHP e um passo, sem ganho para um pacote de dezenas de arquivos.

## Notas

- O formato é versionado (`format_version` no manifesto). Um importador recusa versão que não
  conhece — mudar o formato exige subir o número.
- O pacote contém rascunhos, ou seja, conteúdo ainda não publicado. Não tem dado pessoal, mas
  não deve ficar largado no servidor depois do lançamento (ver `docs/deploy.md` §10.1).
- Contador de download não é exportado: mede acesso ao arquivo *naquele ambiente*, e produção
  começa a contar do zero.
