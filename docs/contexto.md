# Contexto do Projeto

> **Fonte:** levantamento feito em agosto/2026 a partir de fontes públicas, do site atual da
> instituição, de matéria da CBN Londrina (10/08/2026) e de entrevista do vice-presidente à
> Rádio Paiquerê (30/07/2026).
>
> **Instrução ao Claude Code:** dados marcados `[CONFIRMAR]` vieram de fonte única ou podem
> estar desatualizados — não os trate como certos e nunca os publique no site sem validação
> da instituição. Seções marcadas `[LACUNA]` não têm informação; **pergunte antes de
> implementar** algo que dependa delas.

## A instituição

**Razão social: LAR ANÁLIA FRANCO DE LONDRINA** — associação civil beneficente, filantrópica
e de natureza espírita (estatuto consolidado em 11/06/2022, art. 1º), Londrina/PR. CNPJ
78.614.096/0001-75. Fundada em **12/07/1953** (art. 1º do estatuto) por um grupo espírita,
originalmente como orfanato — corrigido pelo cliente em 10/09/2026; este documento e o
repositório traziam **1963** até então. O atendimento hoje é laico.

**Dois locais distintos, confirmados pelo cliente em 10/09/2026** — não são a mesma entidade
de endereço, o que afeta seeder, `/contato` e qualquer marcação schema.org futura (JSON-LD
`NGO`/`Organization`, ver `docs/roadmap.md`):

- **Sede / CEI Anália Franco:** Av. Anália Franco, 33 — Jd. Aeroporto, Londrina/PR —
  (43) 3325-8060. Terreno de aproximadamente 33.000 m² `[CONFIRMAR]` — mesma pesquisa de
  fonte única dos demais números tratados como não confirmados neste documento.
- **Bazar:** Rua Rosa Siqueira, 152 — Jd. Aeroporto, Londrina/PR — (43) 3322-2373 e
  (43) 99950-0183 (WhatsApp).

Recebeu a **Medalha Ouro Verde** da Câmara Municipal de Londrina em 2016, a maior honraria do
município.

O nome homenageia Anália Franco (1853–1919), educadora, abolicionista, jornalista e
filantropa que criou mais de 70 escolas e 23 asilos para crianças órfãs no Brasil.

`[CONFIRMAR]` Total de funcionários: o repositório trazia "72 a 75 funcionários" — o cliente
desmentiu ou não confirmou esse número em 10/09/2026. Removido de todo o site; não publicar
sem confirmação.

**Diretoria confirmada pelo cliente em 10/09/2026** — gestão eleita em assembleia de
05/10/2025 (ata 05/2025), biênio 2026–2027, posse em 15/01/2026. Substitui o registro
anterior deste documento (presidente "Júlio Palmiro", `[CONFIRMAR]`).

Diretoria Executiva:

- Presidente: Valdomiro Ferreira dos Santos
- Vice-presidente: Sidnei Pereira do Nascimento
- Secretário: Marcos Aurélio Batyras
- Diretor de Patrimônio: Domingos Geraldo Stersa Junior
- 1º Tesoureiro: Marcos Adriano Dornelas Pinheiro
- 2º Tesoureiro: Ângelo Pamplona da Costa

Conselho Deliberativo:

- Presidente: André Luiz Gonçalves Salvador
- Vice-presidente: Jonatas Beranger

No site público, escrever sempre "gestão 2026–2027", nunca datas de início/fim de mandato.
A ata de origem contém RG, CPF, estado civil, profissão e endereço residencial de cada
membro — **nada disso entra no repositório, em seeder ou em qualquer arquivo versionado**,
em nenhuma hipótese. Publicar apenas nome e cargo.

## Os três pilares

O site e o sistema se organizam em torno de três operações distintas.

### 1. Centro de Educação Infantil Anália Franco (CEI Anália Franco)

> Nome corrigido pelo cliente em 10/09/2026. O repositório trazia "CEI Tio Pedro" até então —
> nome errado, substituído em todas as ocorrências.

Creche e pré-escola (C1 a P5), crianças de **1 a 5 anos**, período integral das 7h30 às
17h30. Criado em 2002. Convênio com a Secretaria Municipal de Educação de Londrina.

**Números confirmados pelo cliente em 10/09/2026, com fonte documental** — substituem os
"250 crianças hoje" e "meta de 300+ para 2027" que este documento trazia antes, nunca
validados (fonte única/entrevista):

- **213 crianças** atendidas no CEI em **abril de 2023**
- **15 turmas** no CEI (plano de trabalho do Termo de Colaboração 06/2022, exercício 2026)
- Previsão de **308 crianças** de 1 a 5 anos em **2027**

Cinco refeições diárias com cardápio de nutricionista. Autorização de funcionamento renovada
até 01/01/2028.

Pais destacam nas avaliações públicas: aulas de inglês, educação física, horta e quadra
poliesportiva.

**Matrícula — confirmado pelo cliente em 13/09/2026, decisão de arquitetura, não só de
conteúdo:** a matrícula é feita **exclusivamente pela Central de Vagas da Prefeitura de
Londrina**, na Rua Benjamin Constant, 800, Centro. O Lar Anália Franco não recebe pedido de
vaga diretamente. Por isso o formulário de manifestação de interesse que o site tinha (ADR
0007, `enrollment_interests`) foi **removido por completo** — coletar contato que a
instituição não pode atender geraria dado pessoal sem finalidade, o que a LGPD não permite.
`/educacao-infantil/matricula` virou uma página só informativa, que explica o caminho pela
Central de Vagas.

`[LACUNA]` Telefone da Central de Vagas — a página publica o endereço e direciona ao site
oficial da Prefeitura de Londrina para telefone e demais canais, sem citar número, até a
instituição confirmar o telefone (antes a página dizia "em confirmação com a instituição",
uma anotação de trabalho visível ao público — corrigido).

### 2. Escola de Contraturno

**Programa em preparação, ainda sem turma nem aluno matriculado.** Nenhuma página do site
pode afirmar que o programa está em funcionamento — ver `ContentPagesSeeder`.

**Confirmado pelo cliente em 13/09/2026**, substitui a faixa etária `[CONFIRMAR]` de 12 a 17
anos e a "previsão inicial de duas turmas de cerca de 20 alunos" que este documento trazia
antes:

- **Público:** crianças e adolescentes de **6 a 15 anos**, de famílias com renda de até **3
  salários mínimos**.
- **Meta:** atender **100** crianças e adolescentes.
- **Estrutura já existente:** laboratório de informática com **20 computadores** doados pelo
  Centro de Recondicionamento de Computadores (programa do governo federal); parceria com o
  **SENAI**; ginásio de esportes; auditório. Tudo no terreno da instituição.
- **Oficinas previstas:** produção audiovisual, produção de podcast, grafite, patrimônio
  histórico-cultural, capoeira, música, dança, literatura, tecnologias criativas, informática
  básica e um time de futebol.
- **Início das turmas e abertura das inscrições:** previsto para **2027**. Sem data exata —
  o texto do site usa "início previsto para 2027", nunca "em breve".
- **Nome oficial do programa ainda não definido.** O slug `/contraturno` é provisório; a
  troca de slug quando o nome for definido já é coberta pelo mecanismo existente de histórico
  de slug (`App\Models\PageSlugHistory`), sem exigir mudança de código.
- **Financiamento:** viabilizado pela receita do Bazar Beneficente, como já registrado abaixo.

`[LACUNA]` Como funciona a inscrição efetiva quando abrir, critérios de seleção além de
faixa etária e renda, frequência das oficinas, se há vínculo com a escola regular da criança
ou adolescente.

`[DECIDIDO]` Enquanto o programa não abre inscrições, o site coleta apenas nome e telefone do
responsável, para avisar quando abrirem — nunca dado da criança ou adolescente (ver ADR 0007
e `App\Http\Requests\Forms\StoreProgramApplicationRequest`).

`[PENDENTE]` Quando o site implementar marcação schema.org, a página `/contraturno` deve
trazer só descrição institucional — nunca marcada como serviço em operação nem como oferta
ativa, porque o programa ainda não abriu. Registrado aqui para não se perder até a
implementação existir.

Há intenção declarada de estender cursos de acessibilidade digital para pessoas acima de 60
anos no futuro — fora do escopo atual, mas o sistema não deveria assumir que todo atendido é
menor de idade para sempre.

### 3. Bazar Beneficente

Existe desde **1968**, sem interrupção. Loja física com coleta de doações de itens. Financia
cerca de **40% do orçamento** da instituição e banca tudo que não é a creche. Em obra de
duplicação.

Frase do vice-presidente que resume o peso do bazar: sem ele, a operação não se sustentaria
um dia.

**Agendamento de coleta — confirmado pelo cliente em 13/09/2026:** hoje já é feito por
telefone e WhatsApp. O WhatsApp (43) 99950-0183 é o canal principal da página
`/bazar/agendar-coleta`; o formulário do site é caminho secundário, para fora do horário de
atendimento, e notifica automaticamente o setor do bazar.

## Sustentação financeira

`[CONFIRMAR]` Divisão percentual do orçamento entre convênio e bazar: este documento trazia
"~60% convênio / ~40% bazar" e o `LedgerLine` da home publicava "40% do orçamento vindo do
Bazar Beneficente". O cliente desmentiu ou não confirmou nenhuma divisão percentual
(50/50, 40%, 74/26) em 10/09/2026 — removida de todo o site. O convênio com a Prefeitura de
Londrina cobre a creche; o Bazar Beneficente sustenta o que o convênio não cobre (ver
"Os três pilares" acima) — sem número de proporção até confirmação.

**Repasse municipal 2026 — confirmado, dado de transparência:** R$ 2.819.892,84 (Termo de
Colaboração 06/2022 com a Secretaria Municipal de Educação de Londrina).

Outras fontes já existentes, hoje mal exploradas no site: urna de doações em
estabelecimentos parceiros, aluguel da quadra poliesportiva, doações diretas e voluntariado.

**Não há gateway de pagamento no escopo.** O site precisa explicar bem os caminhos que já
existem.

**PIX e transferência bancária — confirmados pelo cliente em 10/09/2026:**

- PIX (chave CNPJ): 78.614.096/0001-75 — nome exibido: LAR ANALIA FRANCO DE LONDRINA
- Transferência: Banco do Brasil (001), agência 2755-3, conta corrente 4963-8

**Removido em 10/09/2026, por instrução do cliente:** menção a Nota Paraná e a qualquer
destinação de Imposto de Renda (fundo da criança e do adolescente, FMDCA, lei de incentivo).
Nenhuma dessas opções está disponível hoje — as páginas `/como-ajudar/nota-parana` e
`/como-ajudar/empresas-ir` foram removidas do `ContentPagesSeeder`, sem deixar rascunho.
Voltam ao escopo quando a instituição avisar.

## Histórico recente — informação sensível

Em janeiro de 2022 a instituição foi **condenada em primeira instância** em ação do Ministério
Público do Paraná por maus-tratos no **serviço de acolhimento institucional** (o antigo
abrigo). A decisão determinou o afastamento de nove ex-dirigentes e a dissolução do Lar
enquanto entidade de acolhimento. Uma nova diretoria assumiu na sequência.

`[LACUNA]` **Status processual atual.** Decisão de primeira instância não é definitiva, e
estamos em 2026 — quatro anos depois. Não sabemos se houve recurso, em que instância o
processo está hoje, nem se a decisão de 2022 transitou em julgado. Nenhum texto do site deve
tratar a condenação de primeira instância como fato encerrado sem essa confirmação; ver a
nota de bloqueio em `/quem-somos/o-lar-hoje` no `ContentPagesSeeder`.

**A creche seguiu funcionando normalmente durante todo o processo** — documentos de abril de
2023 registram 213 crianças atendidas (ver "Os três pilares" acima). Não há, por ora, número
confirmado de matrículas imediatamente antes ou depois de 2022 para sustentar uma alegação de
crescimento específica.

### Controles adotados após 2022 — lacuna de maior valor do projeto

`[LACUNA]` O que falta para `/quem-somos/o-lar-hoje` dizer algo concreto além de "uma nova
diretoria assumiu, o acolhimento foi encerrado, a creche cresceu". Quem tranquiliza um
visitante desconfiado é o controle concreto, não a alegação genérica — e nada disto foi
levantado ainda em fonte pública. Perguntas a fazer à instituição:

- Que controles internos foram criados desde 2022 (financeiros, de conduta, de atendimento)?
- Quem fiscaliza a instituição hoje, além da prestação de contas pública — órgão externo,
  auditoria independente, conselho fiscal?
- Que protocolos de proteção à criança e ao adolescente foram adotados ou reforçados desde
  então?
- Como a equipe que atua com crianças e adolescentes é hoje selecionada, formada e
  supervisionada?
- Há acompanhamento por algum órgão do sistema de garantia de direitos (Conselho Tutelar,
  CMDCA, Ministério Público) desde a mudança de diretoria?

Esta é a lacuna de maior valor pendente do projeto: sem essas respostas, a página mais
sensível do site só pode descrever o que mudou estruturalmente, nunca o que foi construído
para evitar repetição — que é exatamente o que tranquilizaria quem chega desconfiado.

### Por que isso importa para este projeto

**Para o site:** o objetivo central do redesign é deslocar a percepção pública de
"instituição que teve um problema" para "instituição que se reergueu". Transparência real é a
estratégia, não enfeite. Ainda hoje as matérias sobre o caso dominam a busca pelo nome da
instituição.

**Para o sistema:** o padrão de rigor com dados de menores não é teórico aqui. Uma
instituição com esse histórico não pode ter um segundo incidente, de nenhuma natureza. As
regras de `docs/protecao-de-dados.md` existem por isso.

## Consequência arquitetural: não há mais acolhimento

**Isto simplifica o modelo de dados de forma significativa e revisa premissas anteriores.**

A instituição **não** faz mais acolhimento institucional. Portanto, **não existem** no
sistema: medida protetiva, prontuário de acolhimento, dado de guarda ou disputa de guarda,
pernoite, casa-lar, histórico de vara da infância.

O que existe:

| Operação | Dados envolvidos |
|---|---|
| Creche | Matrícula, responsáveis legais, frequência, alergia e restrição alimentar, autorização de saída, consentimento de imagem |
| Contraturno | Inscrição, responsáveis, frequência, turma, eventual acompanhamento pedagógico |
| Bazar | Doações de itens, voluntários — dados de adulto, regime comum |

Isso deve ser refletido em `docs/dominio.md` e `docs/protecao-de-dados.md`: a tabela
`referrals` (encaminhamento e medida protetiva) e boa parte de `health_records` provavelmente
não se aplicam. **Confirmar antes de remover** — a creche ainda registra alergia e restrição
alimentar, que são dado de saúde.

O que **não** muda: todos os atendidos continuam sendo crianças e adolescentes, então o art.
14 da LGPD, o consentimento de responsável e o cuidado com imagem continuam integralmente
válidos.

## Prestação de contas

Convênio com a **Secretaria Municipal de Educação de Londrina** para a creche. A instituição
já mantém um acervo de cerca de 70 documentos de transparência (balanços, prestações de
contas, editais) hospedado em pasta pública — organizado e atualizado, mas invisível para
buscadores, porque o site atual o carrega por JavaScript.

`[LACUNA]` Quais campos e relatórios o convênio exige, e em que formato e periodicidade. É a
informação com maior risco de forçar mudança de schema depois.

`[LACUNA]` Se há vínculo com CMDCA ou Conselho Municipal de Assistência Social, e o que isso
exige.

## Situação digital atual

| Ativo | Situação |
|---|---|
| Site | `laftransparencia.wixsite.com/mysite` — Wix plano gratuito, com banner de anúncio |
| Domínio | `laftransparencia.com` registrado, não resolvendo |
| Blog | Parado desde junho de 2020 |
| Transparência | ~70 documentos existem, mas a página não é indexável |
| Doação online | Inexistente |
| Contraturno | Não mencionado no site |
| Bazar | Sem página própria |
| Instagram | `@ceilaranaliafrancolondrina`, ativo `[CONFIRMAR]` |
| Facebook | Duas páginas: instituição e bazar |
| Google Places | 4,5★ com 221 avaliações |

O detalhe que resume o diagnóstico: o endereço do site contém a palavra "transparência" e a
página de transparência não é encontrável.

## Públicos do site

| Público | O que vem buscar |
|---|---|
| Mães e pais do entorno | Vaga na creche, como matricular, o que a instituição oferece |
| Adolescentes e responsáveis | Inscrição no contraturno |
| Doador pessoa física | Como doar item, dinheiro, ou destinar IR |
| Doador pessoa jurídica | Credibilidade, prestação de contas, parceria |
| Voluntário | Como ajudar |
| Órgão fiscalizador, imprensa | Documentos, dados oficiais, contato |

## Domínio

`[LACUNA]` Domínio novo ainda não definido. O Sanctum em cookie mode exige site e API sob o
mesmo domínio raiz (ver `docs/arquitetura.md`). Considerar o que fazer com
`laftransparencia.com`, já registrado.

## Projeto

Desenvolvido pela **Softhing**, também como case de portfólio. A instituição tem pouco tempo
e pouco material a fornecer, então boa parte do conteúdo inicial é construída pela equipe e
depois validada.

`[LACUNA]` Quantas pessoas na equipe do projeto e qual o papel de cada uma.

`[LACUNA]` Perfil técnico de quem vai operar o painel administrativo na instituição. **É a
lacuna mais relevante que resta** — define o quanto a interface precisa ser conservadora.

`[LACUNA]` Prazo, evento ou compromisso que pressione a entrega. Vale considerar novembro de
2026, quando a instituição completa 63 anos.

`[LACUNA]` Quem mantém o sistema depois de entregue.

`[LACUNA]` Identidade visual: logo, paleta, tipografia, manual de marca.

## Regras de conteúdo

- **Nunca publicar número, data ou fato marcado `[CONFIRMAR]` sem validação da instituição.**
  Dado errado em site de ONG vira problema de imagem e, eventualmente, jurídico.
- Depoimento de pai ou mãe extraído de avaliação pública só entra no site **com autorização**.
- Foto de criança ou adolescente só com consentimento de imagem vigente registrado, e **nunca
  com nome completo**.
- A missão institucional atual foi escrita na época do abrigo e descreve uma operação que não
  existe mais. Reescrevê-la é entregável em aberto, a validar com a instituição.
