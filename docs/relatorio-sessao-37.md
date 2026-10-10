# Relatório da sessão 37

Cobre todo o trabalho desde o relatório da sessão 36 (`63aa504`). Quatro migrações de dados
novas (WhatsApp/itens aceitos do bazar, fotos do bazar, e duas em /doar) mais a dos valores
voláteis; todas idempotentes, com `down`, e todas **só tocam o texto se ele for exatamente o
esperado** — bloco editado pelo Lar não é alterado e vai para o log com `warning`.

**Atenção ao implantar:** as migrações de dados só valem depois de `php artisan migrate`, e o
conteúdo público é cacheado por 10 minutos (`cache:clear` para ver na hora).

## Valores voláteis removidos (`a1b04ea`, `d94fe18`)

- **Mudou:** repasse, nº do termo/plano de trabalho e nº de turmas saem do texto de Home,
  Educação Infantil e Transparência (ficam só nos anexos da transparência, a pedido do
  financeiro); some o registro fixo da home.
- **Migração:** `2026_10_08_090000_remove_volatile_values_from_site_pages` — troca cada trecho só
  se o conteúdo atual for o original.
- **Testes:** Pest (`RemoveVolatileValuesMigrationTest`), e2e de números calculados e de home sem API ajustados.
- **Decisão:** texto genérico no lugar, nenhum número novo digitado.

## WhatsApp do bazar e lista de itens aceitos (`4212fed`)

- **Mudou:** WhatsApp de (43) 99950-0183 para (43) 99995-0183 (texto e link `wa.me`) em
  `InitialPages`, `institution.ts`, testes e docs; a página "O Que Aceitamos" troca o aviso de
  "lista em confirmação" pela lista de itens.
- **Migração:** `2026_10_10_090000_fix_bazar_whatsapp_and_accepted_items`.
- **Testes:** `FixBazarWhatsappMigrationTest`; e2e ajustados.

## ImageProcessor (`03ed118`)

- **Mudou:** foto girada (EXIF 5–8) era processada com original e girada vivas juntas — 20 MP
  chegavam a +172 MB, 30 MP a +234 MB, acima do `memory_limit` de 256M de produção. Agora a
  original é cortada em faixas, girada por faixa; derivadas saem da maior para a menor. Pico: 20 MP
  172→112 MB, 30 MP 234→136 MB (o piso é a decodificação, ~91 MB para 20 MP).
- **Teste:** cobre as 8 orientações e o pico relativo. `memory_limit` de 512M só para o processo
  de testes (`phpunit.xml`): o estouro de 128M vinha do acúmulo da suíte, não de uma foto.
- **Migração de dados:** nenhuma.

## Galerias 4:3 (`298b329`)

- **Mudou:** miniaturas com `aspect-ratio` 4:3, `object-fit: cover`, centradas, em `AppGaleria`
  (Educação Infantil, Bazar, Transparência, páginas genéricas) e no registro de época de Nossa
  História. A ampliação continua mostrando a foto inteira, na proporção real.
- **Teste:** e2e `galeria-miniaturas.spec.ts`. **Migração:** nenhuma.

## Fotos do bazar (`83529ee`)

- **Mudou:** 7 fotos novas em `InitialPhotos::additions()` (fora de `all()`, que alimenta os
  redirecionamentos de endereços antigos), com texto alternativo.
- **Migração:** `2026_10_10_120000_add_bazar_photos_batch_two` — idempotente por `origin_key`, só
  acrescenta ao fim da galeria, não traz de volta foto excluída. Reversível.
- **Testes:** `BazarPhotosBatchMigrationTest`, `InitialPhotosTest`; nenhuma das 7 duplica foto
  existente (hash perceptual).

## Auditoria npm (`992d7a4`)

- **Mudou:** dependências atualizadas dentro do range nos dois frontends; `nuxt` sobe para
  `^4.6.1`. `@vue/server-renderer` e `source-map-js` (presentes no `.output`) corrigidos.
- **Decisão:** `simple-git`/`argv-parser` e `braces`, ausentes do `.output`, entram como isenção
  com motivo em `scripts/ci/npm-audit.mjs`. **Migração:** nenhuma.

## Pix e transferência (`4a5cfc3` até `d06e36a`)

- **`4a5cfc3`:** seção "Doe por Pix" em /doar, QR em SVG gerado no servidor a partir do payload de
  `institution.ts`, botões de copiar. Única dependência nova: `uqr` 0.1.3 (declarada no commit).
  Teste do CRC16 do payload e e2e do fluxo.
- **`9ff57e2`:** card sobe para logo abaixo do título; chave Pix e frase "não processa doações"
  saem do texto do CMS. Migração `2026_10_10_150000_move_pix_out_of_doar_page_content`.
- **`90928da`:** `bankTransfer` em `institution.ts` (fonte única, ao lado de `pix`).
- **`4d6120f`:** marcador `{{formas_de_doacao}}` e componente `AppFormasDeDoacao.vue`, no lugar do
  `AppDoacaoPix`. Dois cartões lado a lado (mesma altura, títulos e rodapé alinhados), empilhados
  no celular com o Pix primeiro e o QR recolhido; botões de copiar com rótulo temporário, aria-live
  e fallback de seleção. **Decisão de arquitetura:** o servidor deixa esse marcador cru
  (`ContentMarker::isRenderedBySite`) e o Nuxt o troca pelo componente, porque os dados vivem em
  `institution.ts` — o inverso dos demais marcadores, que o backend resolve.
- **`b7acc91`:** corpo de /doar vira frase de abertura → marcador → frase do bazar.
  Migração `2026_10_10_180000_use_donation_methods_marker_in_doar_page`: troca se o texto atual for
  o original **ou** o da migração 150000. `InitialPages` atualizado.
- **`4f3ebbe`:** e2e reescrito — ordem, mesma altura, empilhamento, cada botão copiando o valor
  certo, chave Pix uma única vez. O CNPJ aparece duas vezes na página (chave Pix e linha CNPJ da
  transferência) por serem o mesmo número; o teste exige exatamente isso.

## Esta sessão

- **/doar local sem cards.** Causa: as duas migrações (`…150000` e `…180000`) estavam
  **pendentes** no banco de desenvolvimento — não foi caso de "pular". O texto no banco era
  exatamente o original (lista de PIX e transferência), então nenhuma diferença a explicar e o
  critério de comparação **não foi alterado**. `php artisan migrate` + `cache:clear`: /doar passou a
  mostrar os cards e o corpo ficou como o esperado.
- **Cards nunca somem** (`d06e36a`). Se o conteúdo não tiver o marcador (migração pulada, ou
  apagado no painel), os cartões vêm logo após o texto do CMS. Teste e2e novo edita a página pela
  API, confere "sem marcador → cartões após o texto", restaura e confere "com marcador → cartões no
  lugar dele, frase do bazar depois".
- **Conferência no navegador (Playwright MCP, 375 e 1280 px).** Ordem frase → cartões → frase do
  bazar ok; no desktop os cartões têm a mesma altura e títulos e rodapés alinhados; no celular
  empilhados, Pix primeiro, QR recolhido. Galerias do bazar (11 fotos) e da Educação Infantil
  (4): todas as miniaturas 347×260 (4:3) mesmo com fotos retrato e 16:9; a ampliação abre na
  proporção real (retrato 0,56 e 4:3 conferidos). Nada precisou de correção. Os botões de cópia
  são cobertos pelo e2e (o clipboard real do navegador não é lido).

## Ajuste visual dos cartões de /doar (`19f1ec6`)

- **Mudou (`AppFormasDeDoacao.vue`):** títulos dos cartões passam a `h3` (26 px) com ícone de 20 px
  centralizado na linha; botões "Copiar" das linhas (chave Pix, agência, conta, CNPJ) ficam
  compactos (36 px, ícone de copiar + texto, mesma largura mínima), alinhados à direita;
  "Copiar código Pix" segue primário em largura total. Todas as linhas (inclusive "Banco", sem
  botão) têm 56 px de altura fixa, valor e botão alinhados no centro.
- **Conferido no navegador (375 e 1280 px):** cartões de mesma altura (408 px no desktop), títulos
  e rodapés na mesma linha, linhas de 56 px, botões de 36 px com a mesma borda direita, sem
  estouro horizontal no celular, Pix primeiro.
- **Testes:** o e2e do desktop agora confere altura igual das linhas e dos botões e o alinhamento à
  direita; o seletor do título passou de `h2` para `h3`. **Migração de dados:** nenhuma.
- **Decisão:** o título ficou `h3` direto sob o `h1` (pulando o nível 2), como pedido.

## Conferência humana

- Rodar `php artisan migrate` em produção e `cache:clear`; se a migração pular /doar (texto
  editado), os cartões aparecem mesmo assim, após o texto — e o texto antigo com a lista de
  PIX continuará lá até alguém editar no painel.
- Conferir `/doar` num celular de verdade e pagar um Pix de teste pelo QR.
