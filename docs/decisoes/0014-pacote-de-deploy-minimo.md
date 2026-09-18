# 0014 — O servidor recebe um pacote mínimo, não o repositório

## Contexto

O deploy da 07b envia para uma VPS o que o CI empacotar. Precisava ficar decidido **o que
exatamente** vai para lá, porque o modo mais comum de publicar uma aplicação — clonar o
repositório no servidor e rodar o build ali — não serve a este projeto por dois motivos
independentes, e nenhum deles é performance.

**O servidor é da instituição.** O Lar Anália Franco contrata e paga a VPS, e tem (com razão)
acesso administrativo a ela. O que é propriedade da Softhing — o código-fonte Vue e Nuxt dos
dois frontends, a documentação de arquitetura e de produto, os testes, as decisões registradas
em `docs/decisoes/`, o `CLAUDE.md` — não precisa estar naquela máquina para o site funcionar.
Copiar tudo para lá entrega o trabalho inteiro junto com o produto, sem que ninguém tenha
decidido isso; não copiar não tira nada da instituição, que continua com o sistema rodando e
com o repositório disponível pelo contrato, quando e como o contrato disser.

**Superfície.** Todo arquivo que não é executado em produção é superfície de ataque ou de
vazamento sem contrapartida: `docs/` descreve a arquitetura e onde ficam as chaves;
`backend/scripts/concorrencia/` roda `migrate:fresh`; `backend/tests/` e `e2e/` descrevem o
comportamento esperado de cada rota de escrita; um `.env` copiado por acidente entrega tudo de
uma vez. Este é um sistema que guarda dado de criança e adolescente (ver
`docs/protecao-de-dados.md`) — aqui o "não custa nada deixar" custa.

## Decisão

**O que vai para o servidor é um pacote gerado por `scripts/deploy/empacotar.sh` a partir de
uma árvore limpa, contendo apenas artefato executável.** O repositório nunca é clonado no
servidor e nenhum build acontece lá.

O pacote tem três diretórios, um por processo (ver `docs/deploy.md`):

| No pacote | O que é | Quem serve |
|---|---|---|
| `backend/` | Laravel com `composer install --no-dev --optimize-autoloader` | PHP-FPM, worker e scheduler |
| `site/` | `frontend-site/.output` (build do Nuxt) | `node site/server/index.mjs` (Nitro) |
| `painel/` | `frontend-admin/dist` (build do Vite) | Nginx, como arquivos estáticos |
| `RELEASE` | commit, data e versões de PHP/Node do build | ninguém — é procedência |

Ficam **sempre** de fora: `docs/`, `.claude/`, `.github/`, `e2e/`, `docker/`, `infra/`,
`shared/` (o que precisa ser servido o build já copiou), `.git/`, todo `README.md` e qualquer
outro `.md`, o `CLAUDE.md`, o código-fonte `.vue`/`.ts` dos frontends, os source maps, e do
backend: `tests/`, `scripts/`, `stubs/`, `phpunit.xml`, `phpstan.neon`, `pint.json`, qualquer
`.env*` e o conteúdo de `storage/logs`.

Três detalhes que a implementação obrigou a decidir:

**O pacote sai de `git archive HEAD`, não do diretório de trabalho.** É o que garante que nada
não versionado entre por acidente — um `.env` local, um dump de banco, um arquivo de teste
esquecido. Em troca, o script recusa rodar com a árvore suja: um pacote que não corresponde a
commit nenhum não é reproduzível.

**A poda vem antes de `composer dump-autoload`.** O classmap otimizado é gerado a partir da
árvore que existia na hora; podar depois dele deixa entradas apontando para arquivos que não
existem mais, e o efeito disso é erro fatal em produção — só em produção.

**A regra vale para o código deste repositório, não para dentro de `vendor/`.** O Laravel
carrega os `stubs/` do próprio framework nos comandos `make:*`, os polyfills do Symfony
resolvem classe a partir de `Resources/stubs/`, e vários pacotes trazem o próprio `pint.json`.
Podar isso quebraria a dependência para economizar bytes. O que é varrido do pacote inteiro,
`vendor/` incluído, é o que não tem execução possível: `.md`, `.env*`, `.vue`, `.ts`, `.map`.

### A verificação faz parte do script, e ela falha o build

A lista acima está escrita em dois lugares — aqui e na seção de verificação do script — e é o
script que manda. Ele varre o pacote pronto atrás de cada item proibido e **sai com erro sem
gerar nada** se encontrar qualquer um, além de conferir a lista do que precisa estar presente
(`artisan`, `public/index.php`, `site/server/index.mjs`, `painel/index.html`, os arquivos de
marca efetivamente servidos). Regra que depende de alguém lembrar de segui-la não é regra; a
verificação é o que transforma esta ADR em algo que continua valendo depois desta sessão.

## Consequências

**O pacote não é o mesmo para homologação e produção — por causa do painel.** O Vite grava o
valor de `VITE_API_URL`, `VITE_SITE_URL` e `VITE_SESSION_IDLE_TIMEOUT_MINUTES` **dentro** do
bundle, em tempo de build. O site (Nuxt) não tem esse problema: as `NUXT_PUBLIC_*` são lidas
em tempo de execução, e é por isso que o mesmo `.output` responde como produção ou como
homologação só trocando variável de ambiente (ver `server/plugins/staging-noindex.ts`). Em
consequência, "promover para produção o mesmo pacote já validado em homologação" (previsto na
07b) vale para o backend e para o site, mas **o painel precisa ser rebuildado com as variáveis
de produção**. Ou isso, ou o painel passa a ler a configuração em tempo de execução — decisão
para a 07b, não para esta.

> **Decidido na 07b pela segunda saída** — ver
> `docs/decisoes/0015-painel-configurado-em-tempo-de-execucao.md`. O painel lê
> `window.__LAF_CONFIG__` de um `/config.js` servido fora do bundle e reescrito no servidor,
> e com isso o pacote passou a ser promovível inteiro, painel incluído. O parágrafo acima
> descreve a consequência que existia; ela não vale mais.

**Nada de `artisan make:*` no servidor útil** — os `stubs/` do projeto não vão junto. É o
esperado: ninguém desenvolve no servidor.

**Depurar em produção olhando o código é mais difícil**, porque não há source map e não há
teste para ler. A contrapartida é log estruturado e a possibilidade de reproduzir localmente a
partir do commit registrado em `RELEASE`.

**Quem rodar o script precisa de PHP, Composer e Node na máquina** (ou no runner do CI), nas
mesmas versões do `docker-compose.yml` e do CI. É o que já acontece no GitHub Actions.
