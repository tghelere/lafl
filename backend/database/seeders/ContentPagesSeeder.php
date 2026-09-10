<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PageStatus;
use App\Models\Page;
use Illuminate\Database\Seeder;

class ContentPagesSeeder extends Seeder
{
    /**
     * Conteúdo institucional para o esqueleto do site público — só roda em local/testing,
     * nunca dado real de assistido (ver CLAUDE.md, regra 10; aqui não há dado de assistido,
     * é conteúdo institucional). Usa apenas fatos confirmados em docs/contexto.md — os três
     * pilares, CEI Anália Franco, o bazar desde 1968, endereço, CNPJ. Nenhum dado marcado
     * `[CONFIRMAR]` ou `[LACUNA]` entra aqui — onde falta o dado, o texto diz explicitamente
     * que está pendente de confirmação com a instituição, em vez de inventar.
     *
     * Ano de fundação: o estatuto (art. 1º) registra 12/07/1953, divergindo do que este
     * repositório publicava antes (1963). O cliente confirmou 1953 em 10/09/2026, mas nenhuma
     * página publica ano de fundação até essa correção ser validada de forma definitiva — ver
     * docs/contexto.md.
     *
     * Cada página termina com o comentário HTML `rascunho: validar com a instituição` —
     * nenhum texto aqui foi aprovado pelo Lar Anália Franco.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        foreach ($this->pages() as $data) {
            Page::query()->updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'title' => $data['title'],
                    'content' => $data['content']."\n\n<!-- rascunho: validar com a instituição -->",
                    'meta_title' => "{$data['title']} — Lar Anália Franco",
                    'meta_description' => $data['meta_description'],
                    'status' => PageStatus::Published,
                    'published_at' => now(),
                ],
            );
        }
    }

    /**
     * @return list<array{slug: string, title: string, content: string, meta_description: string}>
     */
    private function pages(): array
    {
        return [
            ...$this->quemSomos(),
            ...$this->educacaoInfantil(),
            ...$this->contraturno(),
            ...$this->bazar(),
            ...$this->comoAjudar(),
            ...$this->transparencia(),
        ];
    }

    /**
     * @return list<array{slug: string, title: string, content: string, meta_description: string}>
     */
    private function quemSomos(): array
    {
        return [
            [
                'slug' => 'quem-somos',
                'title' => 'Quem Somos',
                'meta_description' => 'O Lar Anália Franco é uma associação civil beneficente, filantrópica e de natureza espírita de Londrina, com três frentes: creche, contraturno e bazar beneficente.',
                'content' => <<<'HTML'
                    <p>O Lar Anália Franco de Londrina é uma associação civil beneficente, filantrópica e de
                    natureza espírita. A sede, onde também funciona o CEI Anália Franco, fica na Av. Anália
                    Franco, 33, Jd. Aeroporto, Londrina/PR — telefone (43) 3325-8060. A área exata do
                    terreno está em confirmação com a instituição.</p>
                    <p>Hoje a instituição opera três frentes distintas: o Centro de Educação Infantil Anália
                    Franco (CEI Anália Franco), creche e pré-escola conveniada com a Prefeitura de Londrina;
                    a Escola de Contraturno, com aulas de informática e inteligência artificial para
                    adolescentes; e o Bazar Beneficente, loja de doações que sustenta boa parte do orçamento
                    da casa.</p>
                    <p>As páginas desta seção contam a história da instituição, sua estrutura de governança e
                    o que ela é hoje — inclusive os pontos em que precisou se reconstruir.</p>
                    HTML,
            ],
            [
                'slug' => 'quem-somos/nossa-historia',
                'title' => 'Nossa História',
                'meta_description' => 'De orfanato a instituição com creche, contraturno e bazar: a linha do tempo do Lar Anália Franco de Londrina.',
                'content' => <<<'HTML'
                    <p>O Lar Anália Franco foi criado por um grupo espírita de Londrina, originalmente como
                    orfanato. O atendimento que a instituição presta hoje é laico.</p>
                    <p>O nome é uma homenagem a Anália Franco (1853–1919), educadora, jornalista,
                    abolicionista e filantropa que fundou mais de 70 escolas e 23 asilos para crianças
                    órfãs no Brasil.</p>
                    <ul>
                    <li>1968 — início do Bazar Beneficente, em funcionamento ininterrupto desde então.</li>
                    <li>2002 — criação do Centro de Educação Infantil Anália Franco, com convênio junto à
                    Secretaria Municipal de Educação de Londrina.</li>
                    <li>2016 — recebe a Medalha Ouro Verde, maior honraria da Câmara Municipal de
                    Londrina.</li>
                    <li>2022 — encerramento do antigo serviço de acolhimento institucional e troca de
                    diretoria.</li>
                    <li>2026 — inauguração da sala de informática da Escola de Contraturno.</li>
                    </ul>
                    <p>A página <a href="/quem-somos/o-lar-hoje">O Lar hoje</a> detalha o que mudou nos
                    últimos anos.</p>
                    HTML,
            ],
            [
                'slug' => 'quem-somos/missao-visao-valores',
                'title' => 'Missão, Visão e Valores',
                'meta_description' => 'Rascunho de trabalho da missão, visão e valores do Lar Anália Franco, ainda pendente de validação pela direção da instituição.',
                'content' => <<<'HTML'
                    <p><strong>Este texto é um rascunho de trabalho</strong>, escrito a partir da operação
                    atual da instituição. A missão anterior descrevia o antigo serviço de acolhimento,
                    encerrado em 2022, e por isso não é mais usada — a redação final ainda depende de
                    validação da direção.</p>
                    <h2>Missão</h2>
                    <p>Sustentar, em Londrina, uma educação infantil de qualidade e oportunidades de
                    formação para adolescentes em situação de vulnerabilidade social, financiadas em parte
                    pelo próprio trabalho da instituição.</p>
                    <h2>Visão</h2>
                    <p>Ser reconhecida em Londrina como uma instituição que presta contas do que arrecada e
                    do que faz.</p>
                    <h2>Valores</h2>
                    <ul>
                    <li>Transparência: os documentos de prestação de contas são públicos, não apenas
                    entregues ao órgão fiscalizador.</li>
                    <li>Continuidade: a creche funciona desde 2002 sem interrupção, inclusive durante a
                    troca de diretoria e o fim do acolhimento institucional em 2022.</li>
                    <li>Autossustentação: o Bazar Beneficente existe desde 1968 para custear o que o
                    convênio público não cobre.</li>
                    </ul>
                    HTML,
            ],
            [
                'slug' => 'quem-somos/governanca',
                'title' => 'Governança',
                'meta_description' => 'Como o Lar Anália Franco é administrado: associação civil beneficente, filantrópica e de natureza espírita, diretoria eleita e prestação de contas pública.',
                'content' => <<<'HTML'
                    <p>O Lar Anália Franco é uma associação civil beneficente, filantrópica e de natureza
                    espírita, CNPJ 78.614.096/0001-75, administrada por uma diretoria eleita pelos
                    associados.</p>
                    <p>Em 2022, após uma decisão de primeira instância que reconheceu irregularidades no
                    antigo serviço de acolhimento institucional (ver <a href="/quem-somos/o-lar-hoje">O Lar
                    hoje</a>), a diretoria anterior foi afastada por decisão judicial e uma nova diretoria
                    assumiu a gestão da instituição.</p>
                    <h2>Diretoria — gestão 2026–2027</h2>
                    <h3>Diretoria Executiva</h3>
                    <ul>
                    <li>Presidente: Valdomiro Ferreira dos Santos</li>
                    <li>Vice-presidente: Sidnei Pereira do Nascimento</li>
                    <li>Secretário: Marcos Aurélio Batyras</li>
                    <li>Diretor de Patrimônio: Domingos Geraldo Stersa Junior</li>
                    <li>1º Tesoureiro: Marcos Adriano Dornelas Pinheiro</li>
                    <li>2º Tesoureiro: Ângelo Pamplona da Costa</li>
                    </ul>
                    <h3>Conselho Deliberativo</h3>
                    <ul>
                    <li>Presidente: André Luiz Gonçalves Salvador</li>
                    <li>Vice-presidente: Jonatas Beranger</li>
                    </ul>
                    <p>A prestação de contas da instituição — balanços, atas e editais — está reunida na
                    seção <a href="/transparencia">Transparência</a>.</p>
                    HTML,
            ],
            [
                'slug' => 'quem-somos/o-lar-hoje',
                'title' => 'O Lar Hoje',
                'meta_description' => 'O que aconteceu em 2022 no Lar Anália Franco, o que mudou desde então e por que a transparência é a resposta da instituição.',
                'content' => <<<'HTML'
                    <!-- BLOQUEADO PARA PUBLICAÇÃO: exige revisão jurídica antes de ir ao ar -->
                    <p>O Lar Anália Franco passou por uma reconstrução profunda desde 2022. Esta página
                    explica o que aconteceu e o que mudou desde então.</p>
                    <h2>O que aconteceu</h2>
                    <p>Em janeiro de 2022, uma ação movida pelo Ministério Público do Paraná resultou numa
                    decisão de primeira instância que reconheceu irregularidades no antigo serviço de
                    acolhimento institucional — o abrigo que a instituição mantinha até então. A decisão
                    determinou o afastamento de nove ex-dirigentes e a dissolução do Lar como entidade de
                    acolhimento.</p>
                    <p>Decisão de primeira instância não é definitiva. O status processual atual — se houve
                    recurso e qual o resultado — está em confirmação junto à instituição e será atualizado
                    aqui assim que validado.</p>
                    <h2>O que mudou</h2>
                    <p>Uma nova diretoria assumiu a gestão logo em seguida. O serviço de acolhimento foi
                    encerrado — a instituição não recebe mais crianças e adolescentes em regime de abrigo, e
                    não há mais medida protetiva, guarda ou vínculo com vara da infância.</p>
                    <p>A creche seguiu funcionando durante todo o processo e cresceu desde então. Em 2026, a
                    instituição também inaugurou a Escola de Contraturno, sua operação mais nova.</p>
                    <h2>O que a instituição está fazendo diferente</h2>
                    <p>Não é possível apagar o que aconteceu. O compromisso da direção atual é manter a
                    prestação de contas pública e verificável, documento por documento — ver
                    <a href="/transparencia">Transparência</a>.</p>
                    HTML,
            ],
        ];
    }

    /**
     * @return list<array{slug: string, title: string, content: string, meta_description: string}>
     */
    private function educacaoInfantil(): array
    {
        return [
            [
                'slug' => 'educacao-infantil',
                'title' => 'Educação Infantil',
                'meta_description' => 'CEI Anália Franco: creche e pré-escola do Lar Anália Franco para crianças de 1 a 5 anos, período integral, conveniada com a Prefeitura de Londrina.',
                'content' => <<<'HTML'
                    <p>O Centro de Educação Infantil Anália Franco é a creche e pré-escola do Lar Anália
                    Franco, para crianças de 1 a 5 anos (turmas de C1 a P5). Funciona em período integral,
                    das 7h30 às 17h30, com convênio junto à Secretaria Municipal de Educação de
                    Londrina.</p>
                    <p>O CEI Anália Franco foi criado em 2002 e tem autorização de funcionamento renovada até
                    janeiro de 2028. A rotina inclui aulas de inglês, educação física, horta e uso da quadra
                    poliesportiva do terreno.</p>
                    <p>As páginas desta seção detalham a proposta pedagógica, a alimentação e a estrutura
                    física do CEI.</p>
                    HTML,
            ],
            [
                'slug' => 'educacao-infantil/dia-da-crianca',
                'title' => 'Dia da Criança',
                'meta_description' => 'Programação especial de Dia da Criança do CEI Anália Franco — detalhes da edição deste ano ainda em confirmação com a coordenação.',
                'content' => <<<'HTML'
                    <p>O Centro de Educação Infantil Anália Franco reserva uma programação especial para o Dia
                    da Criança, em outubro, para as crianças atendidas pela creche.</p>
                    <p>Detalhes da edição deste ano — atividades, parcerias e como a comunidade pode
                    contribuir — ainda serão confirmados com a coordenação do CEI e publicados nesta
                    página.</p>
                    HTML,
            ],
            [
                'slug' => 'educacao-infantil/proposta-pedagogica',
                'title' => 'Proposta Pedagógica',
                'meta_description' => 'Rotina do CEI Anália Franco: aulas de inglês, educação física, horta e quadra poliesportiva, em período integral para crianças de 1 a 5 anos.',
                'content' => <<<'HTML'
                    <p>O CEI Anália Franco atende crianças de 1 a 5 anos (turmas de C1 a P5) em período
                    integral, das 7h30 às 17h30, sob convênio com a Secretaria Municipal de Educação de
                    Londrina.</p>
                    <p>Fazem parte da rotina aulas de inglês, educação física, atividades na horta da
                    instituição e uso da quadra poliesportiva do terreno.</p>
                    <p>O currículo pedagógico detalhado (eixos de aprendizagem, avaliação, calendário
                    letivo) ainda não está disponível para publicação e será adicionado após validação da
                    coordenação pedagógica.</p>
                    HTML,
            ],
            [
                'slug' => 'educacao-infantil/alimentacao-e-saude',
                'title' => 'Alimentação e Saúde',
                'meta_description' => 'O CEI Anália Franco serve cinco refeições diárias com cardápio de nutricionista e registra alergias e restrições alimentares informadas na matrícula.',
                'content' => <<<'HTML'
                    <p>O CEI Anália Franco serve cinco refeições diárias, com cardápio elaborado por
                    nutricionista, para todas as crianças em período integral.</p>
                    <p>Restrições alimentares e alergias informadas pela família na matrícula são
                    registradas e levadas em conta no preparo das refeições da criança.</p>
                    <p>Informações sobre protocolo de saúde, vacinação exigida na matrícula e acompanhamento
                    médico ainda serão detalhadas nesta página, após validação da coordenação do CEI.</p>
                    HTML,
            ],
            [
                'slug' => 'educacao-infantil/estrutura',
                'title' => 'Estrutura',
                'meta_description' => 'O CEI Anália Franco funciona no terreno do Lar Anália Franco, com horta e quadra poliesportiva entre os espaços mais usados.',
                'content' => <<<'HTML'
                    <p>O CEI Anália Franco funciona no terreno da instituição, na Av. Anália Franco, 33,
                    Jd. Aeroporto, Londrina/PR — a área exata do terreno está em confirmação com a
                    instituição.</p>
                    <p>O terreno tem horta, usada nas atividades pedagógicas, e quadra poliesportiva, também
                    usada pela Escola de Contraturno e, fora do horário de aula, alugada como fonte de
                    receita da instituição.</p>
                    <p>Uma galeria de fotos da estrutura será publicada aqui assim que o cadastro de mídia
                    do site estiver pronto.</p>
                    HTML,
            ],
            [
                'slug' => 'educacao-infantil/depoimentos',
                'title' => 'Depoimentos',
                'meta_description' => 'O CEI Anália Franco tem avaliação pública de 4,5 estrelas no Google. Depoimentos individuais só entram aqui com autorização de quem os deu.',
                'content' => <<<'HTML'
                    <p>O CEI Anália Franco tem avaliação pública de 4,5 estrelas, com mais de duas centenas de
                    avaliações, no perfil do Google da instituição.</p>
                    <p>Depoimentos de pais e responsáveis só entram nesta página com autorização expressa de
                    quem os deu — nenhuma avaliação pública é reproduzida aqui sem esse consentimento. Esta
                    seção será preenchida à medida que a instituição colher essas autorizações.</p>
                    HTML,
            ],
        ];
    }

    /**
     * @return list<array{slug: string, title: string, content: string, meta_description: string}>
     */
    private function contraturno(): array
    {
        return [
            [
                'slug' => 'contraturno',
                'title' => 'Escola de Contraturno',
                'meta_description' => 'Aulas de informática básica e inteligência artificial para adolescentes, na sala inaugurada em julho de 2026 e viabilizada pelo Bazar Beneficente.',
                'content' => <<<'HTML'
                    <p>A Escola de Contraturno é a operação mais nova do Lar Anália Franco: aulas de
                    informática básica e inteligência artificial para adolescentes em situação de
                    vulnerabilidade social, na sala de informática inaugurada em 28 de julho de 2026.</p>
                    <p>Os equipamentos foram doados pelo Centro de Recondicionamento de Computadores,
                    programa do governo federal, e o projeto foi viabilizado pela receita do Bazar
                    Beneficente — sem o bazar, segundo a própria direção da instituição, essa e outras
                    operações não se sustentariam.</p>
                    <p>A previsão inicial é de duas turmas de cerca de 20 alunos. As páginas desta seção
                    explicam para quem é o programa, como funciona e o que vem a seguir.</p>
                    HTML,
            ],
            [
                'slug' => 'contraturno/o-projeto',
                'title' => 'O Projeto',
                'meta_description' => 'Como o Bazar Beneficente viabilizou a sala de informática da Escola de Contraturno, inaugurada em julho de 2026 com equipamentos doados.',
                'content' => <<<'HTML'
                    <p>O projeto nasceu da combinação de duas coisas que o Lar Anália Franco já tinha: uma
                    sala disponível no terreno da instituição e a receita do Bazar Beneficente, que
                    viabilizou o investimento inicial.</p>
                    <p>Os computadores usados nas aulas foram doados pelo Centro de Recondicionamento de
                    Computadores, programa federal de reaproveitamento de equipamentos. A sala de
                    informática foi inaugurada em 28 de julho de 2026.</p>
                    <p>O programa oferece aulas de informática básica e de inteligência artificial — um
                    conteúdo pouco comum em iniciativas sociais desse porte em Londrina.</p>
                    HTML,
            ],
            [
                'slug' => 'contraturno/para-quem-e',
                'title' => 'Para Quem É',
                'meta_description' => 'A Escola de Contraturno é voltada a adolescentes em situação de vulnerabilidade social — previsão inicial de duas turmas de cerca de 20 alunos.',
                'content' => <<<'HTML'
                    <p>O programa é voltado a adolescentes em situação de vulnerabilidade social. A previsão
                    inicial é de duas turmas de cerca de 20 alunos cada.</p>
                    <p>A faixa etária exata e os critérios de seleção ainda estão em definição junto à
                    coordenação do programa e serão publicados aqui assim que confirmados.</p>
                    HTML,
            ],
            [
                'slug' => 'contraturno/como-funciona',
                'title' => 'Como Funciona',
                'meta_description' => 'As aulas da Escola de Contraturno acontecem na sala de informática inaugurada em julho de 2026. Frequência e inscrição ainda em definição.',
                'content' => <<<'HTML'
                    <p>As aulas acontecem na sala de informática da instituição, inaugurada em 28 de julho
                    de 2026, com equipamentos doados pelo Centro de Recondicionamento de Computadores.</p>
                    <p>Frequência das aulas, forma de inscrição e se há vínculo com a escola regular do
                    adolescente são pontos que a instituição ainda está definindo, por se tratar de um
                    programa recém-criado. Esta página será atualizada assim que esses detalhes forem
                    confirmados.</p>
                    HTML,
            ],
            [
                'slug' => 'contraturno/parceiros',
                'title' => 'Parceiros',
                'meta_description' => 'O Centro de Recondicionamento de Computadores, programa federal, doou os equipamentos da sala de informática da Escola de Contraturno.',
                'content' => <<<'HTML'
                    <p>O Centro de Recondicionamento de Computadores, programa do governo federal de
                    reaproveitamento de equipamentos, doou os computadores usados na sala de informática da
                    Escola de Contraturno, inaugurada em 28 de julho de 2026.</p>
                    <p>A lista de parceiros da instituição — para o contraturno e para os demais pilares —
                    ainda está sendo consolidada e será publicada nesta página.</p>
                    HTML,
            ],
            [
                'slug' => 'contraturno/o-que-vem-por-ai',
                'title' => 'O Que Vem por Aí',
                'meta_description' => 'A direção do Lar Anália Franco já declarou intenção de estender cursos de acessibilidade digital a pessoas acima de 60 anos no futuro.',
                'content' => <<<'HTML'
                    <p>A direção do Lar Anália Franco já declarou a intenção de, no futuro, estender cursos
                    de acessibilidade digital a pessoas acima de 60 anos, além dos adolescentes atualmente
                    atendidos pela Escola de Contraturno.</p>
                    <p>Essa expansão ainda não tem data nem formato definidos. Esta página será atualizada
                    quando a instituição confirmar os próximos passos do programa.</p>
                    HTML,
            ],
        ];
    }

    /**
     * @return list<array{slug: string, title: string, content: string, meta_description: string}>
     */
    private function bazar(): array
    {
        return [
            [
                'slug' => 'bazar',
                'title' => 'Bazar Beneficente',
                'meta_description' => 'O Bazar Beneficente do Lar Anália Franco existe desde 1968 e financia parte relevante do orçamento da instituição.',
                'content' => <<<'HTML'
                    <p>O Bazar Beneficente existe desde 1968, sem interrupção. É uma loja física que recebe
                    doações de itens e revende ao público, e financia parte relevante do orçamento do Lar
                    Anália Franco — o que o convênio da creche com a Prefeitura de Londrina não cobre.</p>
                    <p>Foi a receita do bazar que viabilizou a Escola de Contraturno, criada em 2026. A loja
                    está em obra de duplicação.</p>
                    <p>As páginas desta seção explicam onde fica a loja, o que a instituição aceita em
                    doação e para onde vai o resultado das vendas.</p>
                    HTML,
            ],
            [
                'slug' => 'bazar/visite-a-loja',
                'title' => 'Visite a Loja',
                'meta_description' => 'O Bazar Beneficente funciona em endereço próprio, na Rua Rosa Siqueira, 152, Jd. Aeroporto, em Londrina/PR — separado da sede do Lar Anália Franco.',
                'content' => <<<'HTML'
                    <p>O Bazar Beneficente funciona em endereço próprio, separado da sede do Lar Anália
                    Franco: Rua Rosa Siqueira, 152, Jd. Aeroporto, Londrina/PR. Telefone (43) 3322-2373 ou
                    WhatsApp (43) 99950-0183.</p>
                    <p>A loja está atualmente em obra de duplicação. Horário de funcionamento e um mapa de
                    acesso serão publicados aqui assim que confirmados com a administração do bazar.</p>
                    HTML,
            ],
            [
                'slug' => 'bazar/o-que-aceitamos',
                'title' => 'O Que Aceitamos',
                'meta_description' => 'Lista do que o Bazar Beneficente do Lar Anália Franco aceita em doação — em confirmação com a equipe do bazar.',
                'content' => <<<'HTML'
                    <p>O Bazar Beneficente recebe doações de itens para revenda na loja física da
                    instituição.</p>
                    <p>A lista detalhada do que é aceito — e do que não é — está em confirmação com a
                    equipe do bazar e será publicada nesta página. Para agendar uma coleta, use o
                    formulário de agendamento (em breve nesta seção).</p>
                    HTML,
            ],
            [
                'slug' => 'bazar/para-onde-vai',
                'title' => 'Para Onde Vai',
                'meta_description' => 'A receita do Bazar Beneficente sustenta o que o convênio com a Prefeitura de Londrina não cobre, incluindo a nova Escola de Contraturno.',
                'content' => <<<'HTML'
                    <p>A receita do Bazar Beneficente sustenta parte relevante do orçamento do Lar Anália
                    Franco — o convênio com a Prefeitura de Londrina cobre a creche, e é o bazar que banca o
                    restante da operação da instituição.</p>
                    <p>Foi a receita do bazar, por exemplo, que viabilizou a Escola de Contraturno, criada
                    em 2026 com equipamentos doados pelo Centro de Recondicionamento de Computadores.</p>
                    <p>Os números exatos de arrecadação e destinação estão nos documentos reunidos na seção
                    <a href="/transparencia">Transparência</a>.</p>
                    HTML,
            ],
            [
                'slug' => 'bazar/sua-compra-vira-educacao',
                'title' => 'Sua Compra Vira Educação',
                'meta_description' => 'Cada peça comprada no Bazar Beneficente ajuda a sustentar o CEI Anália Franco e a Escola de Contraturno do Lar Anália Franco.',
                'content' => <<<'HTML'
                    <p>Cada peça comprada no Bazar Beneficente ajuda a sustentar o Centro de Educação
                    Infantil Anália Franco e a Escola de Contraturno — as duas operações educacionais do Lar
                    Anália Franco.</p>
                    <p>O convênio da instituição com a Prefeitura de Londrina cobre a creche, mas não cobre
                    tudo. É a receita do bazar que completa o que falta, e que bancou a criação da Escola de
                    Contraturno em 2026.</p>
                    HTML,
            ],
        ];
    }

    /**
     * @return list<array{slug: string, title: string, content: string, meta_description: string}>
     */
    private function comoAjudar(): array
    {
        return [
            [
                'slug' => 'como-ajudar',
                'title' => 'Como Ajudar',
                'meta_description' => 'Formas de apoiar o Lar Anália Franco: PIX, transferência bancária, doação de itens, voluntariado e parceria empresarial.',
                'content' => <<<'HTML'
                    <p>O Lar Anália Franco não opera gateway de pagamento no site — as formas de contribuir
                    hoje passam por canais já existentes, explicados nas páginas desta seção.</p>
                    <p>É possível apoiar a instituição via PIX ou transferência bancária, doando itens para
                    o Bazar Beneficente, ou se tornando voluntário. Empresas também podem apoiar diretamente
                    os programas da instituição.</p>
                    HTML,
            ],
            [
                'slug' => 'como-ajudar/doar',
                'title' => 'Doar',
                'meta_description' => 'Doe para o Lar Anália Franco via PIX ou transferência bancária.',
                'content' => <<<'HTML'
                    <p>O Lar Anália Franco ainda não processa doações em dinheiro diretamente pelo site.
                    Doe por PIX ou transferência bancária:</p>
                    <ul>
                    <li><strong>PIX</strong> (chave CNPJ): 78.614.096/0001-75 — nome exibido: LAR ANALIA
                    FRANCO DE LONDRINA</li>
                    <li><strong>Transferência:</strong> Banco do Brasil (001), agência 2755-3, conta
                    corrente 4963-8</li>
                    </ul>
                    <p>Doações de itens para o Bazar Beneficente também sustentam diretamente a
                    instituição.</p>
                    HTML,
            ],
            [
                'slug' => 'como-ajudar/parceiros',
                'title' => 'Parceiros',
                'meta_description' => 'Parcerias que sustentam os três pilares do Lar Anália Franco, da doação de equipamentos ao convênio com a Secretaria de Educação.',
                'content' => <<<'HTML'
                    <p>O Lar Anália Franco mantém parcerias que sustentam seus três pilares — da doação de
                    equipamentos de informática pelo Centro de Recondicionamento de Computadores, programa
                    do governo federal, ao convênio com a Secretaria Municipal de Educação de Londrina para
                    o Centro de Educação Infantil Anália Franco.</p>
                    <p>A lista completa de parceiros e apoiadores da instituição está sendo consolidada e
                    será publicada nesta página.</p>
                    HTML,
            ],
        ];
    }

    /**
     * @return list<array{slug: string, title: string, content: string, meta_description: string}>
     */
    private function transparencia(): array
    {
        return [
            [
                'slug' => 'transparencia',
                'title' => 'Transparência',
                'meta_description' => 'O acervo de prestação de contas do Lar Anália Franco — balanços, atas e editais — organizado e público.',
                'content' => <<<'HTML'
                    <p>O Lar Anália Franco mantém um acervo de prestação de contas — balanços, atas,
                    editais e relatórios — hoje com cerca de 70 documentos.</p>
                    <p>Reunimos esses documentos nesta página para que qualquer pessoa consiga localizar o
                    que precisa, incluindo quem busca informação sobre o processo de 2022 (ver
                    <a href="/quem-somos/o-lar-hoje">O Lar hoje</a>).</p>
                    <p>O convênio com a Secretaria Municipal de Educação de Londrina, que sustenta o Centro
                    de Educação Infantil Anália Franco, também exige prestação de contas periódica.</p>
                    <p>Acesse o <a href="/transparencia/documentos">acervo de documentos</a>, com filtro por
                    ano e por tipo.</p>
                    HTML,
            ],
        ];
    }
}
