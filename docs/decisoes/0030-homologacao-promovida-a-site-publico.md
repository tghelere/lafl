# 0030 — A homologação foi promovida a site público, em vez de criar `production`

Sessão 38, 20/10/2026.

## Contexto

`docs/deploy.md` §10 previa o lançamento como a criação de um ambiente `production` novo, no
mesmo VPS, com banco vazio, chaves próprias e o conteúdo levado da homologação por
`conteudo:exportar`/`conteudo:importar`. Quando o domínio `laranaliafrancolondrina.org.br` foi
comprado, o Lar já usava o painel da homologação de verdade: contas criadas, páginas editadas,
fotos e documentos enviados. O pedido foi publicar **esse** sistema no domínio definitivo, sem
deploy do zero, sem recriar banco e sem perder nada.

## Decisão

O ambiente `staging` passou a servir o domínio definitivo, aberto e indexável. Não foi
renomeado: diretórios, banco, Redis, systemd e workflow continuam dizendo `staging`.

- Homologação nunca dependeu do nome do ambiente. O noindex sai de `NUXT_PUBLIC_ENVIRONMENT`,
  e a senha sai da autenticação básica do nginx. As duas foram desligadas.
- `/etc/laf/staging.conf` ganhou `PUBLICO=1`, e `infra/criar-ambiente.sh` se recusa a rodar
  sobre um ambiente com essa marca, porque o reconfiguraria como homologação.
- O nginx deste ambiente passou a ser escrito à mão (raiz, `www` → raiz, API, painel e o 301
  do domínio antigo), e não sai mais de `infra/modelos/`.
- Push na `main` continua publicando em `staging`, que agora é o site da instituição.

## Alternativas descartadas

- **Criar `production` e migrar.** O pacote de conteúdo leva páginas, documentos e imagens, mas
  não as contas nem as mensagens recebidas, e as chaves de cifra seriam outras. Seria um deploy
  do zero, com a instituição recriando acesso, exatamente o que foi pedido para evitar.
- **Renomear `staging` para `production` no servidor.** Exigiria mexer em banco, usuário de
  banco, prefixo de Redis, pool do PHP-FPM, unidades systemd, caminhos e variável do workflow
  ao mesmo tempo, no mesmo dia da troca de domínio, sem nenhum ganho funcional.

## Consequências

- Não existe mais homologação entre a `main` e o público. O CI completo, inclusive o e2e, é a
  única rede antes da publicação.
- `APP_ENV=staging` e a retenção de backup de 7 dias são herança da homologação e precisam de
  decisão própria (ver `docs/relatorio-sessao-38.md`, pendências).
- Se um dia voltar a existir uma homologação separada, ela precisa nascer em outro nome de
  ambiente ou exigir planejar a troca. O domínio definitivo já está tomado pelo `staging`.
