# 0017 — URL pública dos documentos de transparência: slug persistido, servida pelo site

## Contexto

A instituição mantém cerca de 70 documentos de prestação de contas. O diagnóstico de
`docs/contexto.md` resume o problema: *"o endereço do site contém a palavra transparência e a
página de transparência não é encontrável"*. O site novo existe, em boa parte, para inverter
isso — o balanço precisa ser encontrado por quem procura no Google, não só por quem já está
no site.

Até esta decisão, o PDF era servido por
`GET {api}/api/v1/public/transparency-documents/{uuid}/download`, com
`Content-Disposition: attachment`. Três problemas somados, cada um suficiente para o arquivo
não ser indexado:

1. **Sem nome.** O uuid não diz nada a um buscador nem a quem salva o arquivo no computador.
2. **Em outro host.** Um PDF servido por `api.dominio` conta, na busca, como conteúdo de
   outro site — não reforça o domínio que precisa ser encontrado.
3. **`attachment`.** Buscador indexa PDF que abre no navegador; entregue como anexo, na
   prática, é ignorado.

Havia ainda um quarto problema, de outra natureza: cada passagem de robô somava em
`download_count`, o número que a instituição usa para saber quantas pessoas leram cada
documento.

## Decisão

**A URL canônica do PDF é `/transparencia/documentos/{ano}/{slug}.pdf`, no domínio do site,
servida `inline`.**

Quatro escolhas dentro dela:

### O slug é gerado na criação e persistido

Coluna `slug`, `UNIQUE`, `NOT NULL`, preenchida por
`App\Support\Transparency\DocumentSlug` a partir do título, com sufixo numérico em caso de
colisão (inclusive contra documento excluído por soft delete, que continua ocupando o índice).
`App\Actions\Transparency\SaveTransparencyDocument` a preenche **só quando o registro não
existe**.

Derivar o slug do título a cada requisição seria mais simples e estaria errado: corrigir uma
palavra do título — o que o painel permite e a instituição vai fazer — trocaria o endereço de
um arquivo que o Google já indexou e que pode estar citado num ofício.

### O ano é segmento da URL, não parte do slug

O ano já existe como metadado e é o recorte pelo qual as pessoas procuram. Mantê-lo fora do
slug tem uma consequência desenhada: corrigir o ano de um documento move o endereço canônico,
e o endereço antigo passa a responder **301** para o novo. Nenhum link publicado quebra,
nem quando o metadado é corrigido.

A unicidade é do slug sozinho, não do par ano+slug: a URL é resolvida só pelo slug e o ano é
conferido depois — é o que torna esse 301 possível sem busca em duas chaves.

### Quem serve é o Nitro; quem decide é a API

`frontend-site/server/routes/transparencia/documentos/[year]/[slug].ts` é **proxy puro** para
uma rota da API que espelha o mesmo caminho segmento a segmento. 404, 301 de ano trocado,
cabeçalhos e contagem de download são decisão da API — regra 1 do `CLAUDE.md`. O site não
monta o caminho do arquivo em lugar nenhum: ele chega pronto no campo `path` do resource
público.

O proxy repassa o `User-Agent` do visitante. Sem isso, toda requisição chegaria à API com a
assinatura do Node e a contagem viraria ficção.

### A contagem ignora robô conhecido, e isso é aproximação declarada

`App\Support\Http\KnownBots` é a lista única de assinaturas, documentada no próprio arquivo.
O docblock de `RegisterTransparencyDocumentDownload` registra os limites: User-Agent é texto
que o cliente escolhe, e um acesso não é uma pessoa (recarga soma a mais; cache do navegador
soma a menos).

## Alternativas descartadas

- **Manter a URL por uuid e só trocar para `inline`.** Resolve um dos três problemas. O
  endereço continuaria sem nome e em outro host.
- **Servir o arquivo estaticamente, de `public/`.** Seria o mais rápido e tiraria a contagem
  de download junto — e a contagem é o que permite à instituição dizer quais documentos as
  pessoas procuram. Também exigiria arquivo de documento dentro do webroot, o que
  `docs/protecao-de-dados.md` evita por princípio, mesmo para documento público.
- **Redirect (302) da URL do site para a URL da API.** O arquivo continuaria hospedado no
  outro host; o buscador segue o redirect e indexa o destino.
- **Slug calculado do título a cada requisição.** Descartado pelo motivo central da decisão:
  link indexado que se move sozinho.
- **Decidir o 301 de ano trocado no Nitro.** É regra de negócio (qual é o endereço canônico de
  um documento), e regra de negócio não mora no frontend.
- **Verificar robô por DNS reverso do IP**, como o Google documenta. É a forma correta de
  confirmar um rastreador, e custa uma resolução de DNS por download. Desproporcional para uma
  contagem que já é aproximada por outros motivos.

## Consequências

- A rota do proxy é `[slug].ts`, **sem sufixo de método**: com `[slug].get.ts` o Nitro casa só
  `GET`, e um `HEAD` — o que `curl -I` e vários rastreadores mandam antes de baixar — cai na
  404 do site. Medido contra o build de produção.
- `SITE_BASE_URL` (`config('forms.site_base_url')`) passa a ser exigida pelo backend para
  montar o `Location` do 301 do endereço antigo, que sai de um host e aponta para outro. Era
  usada só pelo cabeçalho do e-mail.
- O botão "Baixar PDF" e o link de leitura são **a mesma URL**: o atributo `download` do link
  é o que faz o navegador salvar. Duas URLs para o mesmo arquivo dividiriam o sinal de busca.
- Um documento novo criado por qualquer caminho que não passe por `SaveTransparencyDocument`
  precisa preencher o slug (o seeder e a factory o fazem). A coluna é `NOT NULL` de propósito:
  o banco recusa o registro em vez de publicar um documento sem endereço.
- Dois cadastros simultâneos com o mesmo título podem colidir no índice `UNIQUE`, e aí o
  segundo falha em vez de gravar duplicata. É o comportamento desejado; o gerador escolhe um
  nome bonito, o índice é a garantia.
- Os metadados internos do PDF (título, autor) **não são alterados** — é documento oficial.
