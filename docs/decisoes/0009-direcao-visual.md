# 0009 — Direção visual do site público

> **Substituída por [0013](0013-paleta-derivada-da-logo-substitui-0009.md).** A paleta
> verde/ocre e a tipografia Bitter/IBM Plex Sans abaixo foram trocadas pela paleta
> amarelo/laranja extraída da logo e por Poppins/Lora no commit `6b55ccb` — sem ADR na hora.
> Este documento fica como registro histórico da decisão original; não editado além desta
> nota.

## Contexto

O Lar Anália Franco existe desde 1963 e foi condenado em primeira instância em 2022 por
maus-tratos no serviço de acolhimento, hoje encerrado. Uma nova diretoria assumiu e a creche
cresceu de 120 para 250 crianças desde então (`docs/contexto.md`). O site precisa deslocar a
percepção pública de "instituição que teve um problema" para "instituição que se reergueu" —
isso é trabalho de design tanto quanto de conteúdo. Três públicos têm peso visual igual: mãe
procurando vaga na creche, doador decidindo se confia, pessoa querendo desapegar de um móvel
para o bazar.

## Decisão

### Cor

| Token | Hex | Uso |
|---|---|---|
| `--color-ink` | `#1E2321` | Texto, rodapé escuro |
| `--color-paper` | `#F2F4F1` | Fundo padrão |
| `--color-surface` | `#FFFFFF` | Cards |
| `--color-quadra` | `#1B6E4C` | Acento primário — verde de tinta de quadra poliesportiva |
| `--color-ocre` | `#B8862B` | Acento secundário — ocre de horta, só em elemento não-textual |
| `--color-linha` | `#8B968E` | Hairlines, texto terciário |

Contraste verificado (WCAG): ink/paper ~19:1; quadra/paper (texto) ~5,6:1; branco/quadra
(botão) ~6,2:1. **Ocre sobre paper fica em ~2,9:1 — abaixo do mínimo AA mesmo para texto
grande.** Por isso `--color-ocre` nunca é cor de texto sobre fundo claro no sistema; entra só
como fundo tintado (com `--color-ink` por cima) ou como texto sobre fundo escuro
(`--color-ink`), onde o contraste sobe para ~5,4:1.

### Tipografia

- **Display — Bitter** (600/700), usada com restrição: H1–H3 e o numeral da linha de
  registro.
- **Texto — IBM Plex Sans** (400/500/600 + itálico 400).
- Ambas auto-hospedadas a partir do subset `latin` do Google Fonts (cobre Latin-1
  Supplement — inclui á, é, í, ó, ú, ã, õ, ç, â, ê, ô), baixadas uma única vez para
  `frontend-site/public/fonts/` e servidas com `font-display: swap`. Nenhuma requisição a
  CDN de terceiro em runtime.

### Layout e assinatura

Conceito: um site organizado como livro-caixa público, não como brochura. Todo número
institucional aparece como item de linha auditável — a **linha de registro**
(`.ledger`/`LedgerLine.vue`): numeral em Bitter, rótulo em IBM Plex Sans, hairline, e uma
data à direita. **A linha nunca cita fonte externa** — a instituição é a fonte dos próprios
números; citar imprensa para falar do próprio orçamento comunica insegurança, não
transparência (correção feita nesta sessão em relação à proposta original, que citava
"entrevista, Paiquerê").

Os valores que preenchem a linha de registro hoje (250 crianças, 63 anos, 40% do orçamento)
vêm de `docs/contexto.md` marcados `[CONFIRMAR]`/`[VALIDAR]` — **não são publicáveis antes de
validação da instituição**. O componente (`LedgerLine.vue`) tem uma prop `example` que marca
visualmente a linha como exemplo pendente de validação; ver dependência registrada em
`docs/roadmap.md`.

## Alternativas descartadas

- **Fundo creme + serifada de alto contraste + acento terracota.** O reflexo padrão de
  qualquer ONG. Fundo foi para cinza-esverdeado frio, serifada para slab de baixo contraste,
  acento para verde de quadra em vez de laranja-terracota.
- **Terra roxa / café / vermelho do norte do Paraná.** Material local legítimo, mas
  perigosamente próximo do terracota descartado acima. Optou-se pelo verde de quadra
  poliesportiva e pelo ocre de horta — elementos concretos do terreno citados nas avaliações
  das famílias — em vez da família terracota/terra-roxa.
- **Paleta pastel, ilustração infantil, tipografia arredondada.** Infantilizaria a
  instituição onde ela precisa parecer séria; quem decide é adulto (mãe, doador, órgão
  fiscalizador).
- **Zilla Slab para display.** Escolha original da Etapa 1. Reconsiderada a pedido: Zilla
  Slab é reconhecível como a fonte de identidade da Mozilla/Firefox, e IBM Plex Sans é a
  identidade tipográfica da IBM — a dupla arriscava ler como "startup de tecnologia" em vez
  de "instituição de 63 anos". Trocado por **Bitter**, slab serif sem associação a marca de
  empresa de tecnologia (nasceu como fonte editorial para leitura em tela), mantendo a mesma
  robustez de baixo contraste que motivou a escolha original. IBM Plex Sans foi mantida no
  corpo de texto: a associação de marca é bem menos reconhecível em corpo pequeno do que em
  título de destaque, e a face já estava testada quanto a peso, itálico e cobertura latina.

## Consequências

- O cabeçalho (`AppHeader.vue`) já linka os sete itens do menu principal de
  `docs/estrutura-site.md` §1.1, mas só "Quem somos" existe como página. Isso obrigou a
  desligar `crawlLinks` em `nuxt.config.ts` — do contrário `nuxt generate` tentaria
  prerenderizar as seis rotas ainda não implementadas e falharia. Toda rota nova precisa
  entrar manualmente em `nitro.prerender.routes`, mesma disciplina que "Quem somos" já usa.
- Números na linha de registro exigem validação da instituição antes de ir ao ar — ver
  `docs/roadmap.md`.
- `--color-ocre` como texto sobre `--color-paper` é proibido pelo próprio contraste; qualquer
  uso futuro do token precisa respeitar essa restrição.
