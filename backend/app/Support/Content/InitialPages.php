<?php

declare(strict_types=1);

namespace App\Support\Content;

use App\Enums\PageStatus;

/**
 * Conteúdo institucional inicial do site público — **fonte única**, lida por dois caminhos
 * diferentes:
 *
 * - `Database\Seeders\ContentPagesSeeder`, que roda só em local/testing/e2e e reescreve tudo
 *   a cada `migrate:fresh --seed`;
 * - `App\Actions\Content\ImportInitialPages` (comando `conteudo:importar-inicial`), que roda
 *   em qualquer ambiente e **só cria o que ainda não existe**, nunca sobrescreve.
 *
 * Por isso este array mora em `app/` e não junto do seeder: fora de desenvolvimento o
 * conteúdo é editado pelo painel, e o texto de partida precisa continuar disponível num
 * pacote de deploy que não carrega ferramenta de desenvolvimento nenhuma.
 *
 * **Só semente.** Depois do lançamento o banco de produção é a fonte de verdade do conteúdo, e
 * este arquivo vai divergir do que está no ar — de propósito, não é dívida a quitar (ver
 * docs/decisoes/0023-banco-e-a-fonte-de-verdade-do-conteudo.md). O conteúdo chega a um
 * ambiente novo por `conteudo:exportar` / `conteudo:importar`, não por aqui.
 *
 * Só fatos confirmados em docs/contexto.md entram aqui — os três pilares, CEI Anália Franco,
 * o bazar desde 1968, endereço, CNPJ. Nenhum dado marcado `[CONFIRMAR]` ou `[LACUNA]`: onde
 * falta o dado, o texto diz explicitamente que está pendente de confirmação com a
 * instituição, em vez de inventar. Nenhum dado de assistido, em hipótese alguma (CLAUDE.md,
 * regra 10) — é conteúdo institucional público.
 *
 * Datas: o estatuto (art. 1º) registra fundação em 12/07/1953, divergindo do que este
 * repositório publicava antes (1963, que na verdade é o ano de inauguração da sede, não de
 * fundação da associação). O cliente confirmou as três datas em 17/09/2026 — fundação
 * (1953), início da obra da sede (1957) e inauguração da sede (1963) — e elas já aparecem
 * em quem-somos/nossa-historia — ver docs/contexto.md.
 *
 * O site não menciona o processo judicial de 2022 em nenhuma página, nem de forma indireta —
 * decisão do cliente confirmada em 17/09/2026, ver docs/contexto.md, seção "Histórico
 * recente". `quem-somos/o-lar-hoje`, a página que tratava do assunto, não existe mais aqui
 * (a remoção de um banco de desenvolvimento que já a tinha continua no `ContentPagesSeeder`,
 * onde apagar é aceitável).
 *
 * Contagem e idade NUNCA são digitadas aqui: entram como marcador de App\Enums\ContentMarker
 * (`{{documentos_transparencia}}`, `{{idade_bazar}}` e os demais), resolvido só na leitura
 * pública. "Cerca de 70 documentos" envelhecia a cada upload; o marcador não. Ano de
 * acontecimento ("o bazar existe desde 1968") e valor de documento (o repasse de
 * R$ 2.819.892,84, as 15 turmas do plano de trabalho) continuam literais — não são cálculo,
 * são o que o documento diz.
 *
 * Nenhum texto aqui foi aprovado pelo Lar Anália Franco — esse status é de controle interno
 * (ver docs/roadmap.md), nunca publicado no conteúdo da página.
 *
 * O `content` de cada página está em FORMA CANÔNICA DO EDITOR: é literalmente
 * sanitize(editor.getHTML(html)) — o ponto fixo estável do editor Tiptap do painel mais o
 * ContentSanitizer, o par que qualquer salvamento pelo painel de fato executa. Não é HTML
 * "bonito" escrito à mão; entre outras coisas, todo item de lista vem como
 * `<li><p>texto</p></li>`, nunca `<li>texto</li>` (é assim que o schema de lista do editor
 * sempre serializa — ver o CSS de `.page-content li > p:first-child:last-child` no site e o
 * equivalente em `.rich-text__surface` no painel). Escrever conteúdo novo fora dessa forma
 * não quebra nada sozinho, mas o primeiro salvamento pelo painel reescreve para a forma
 * canônica de qualquer jeito — manter os dois já alinhados evita esse ruído no diff. O teste
 * "conteúdo de todas as páginas do seeder atravessa o sanitizador sem nenhuma alteração"
 * (ContentSanitizerTest) é o que garante essa propriedade.
 */
final class InitialPages
{
    /**
     * `status` é opcional por página — padrão `PageStatus::Published` para quem consome
     * (ver `ContentPagesSeeder::run()` e `ImportInitialPages`). Único bloqueio real de
     * publicação é este campo; nada em `content` controla acesso, é só texto.
     *
     * @return list<array{slug: string, title: string, content: string, meta_description: string, status?: PageStatus}>
     */
    public static function all(): array
    {
        return [
            ...self::quemSomos(),
            ...self::educacaoInfantil(),
            ...self::contraturno(),
            ...self::bazar(),
            ...self::comoAjudar(),
            ...self::transparencia(),
        ];
    }

    /**
     * @return list<array{slug: string, title: string, content: string, meta_description: string}>
     */
    private static function quemSomos(): array
    {
        return [
            [
                'slug' => 'quem-somos',
                'title' => 'Quem Somos',
                'meta_description' => 'O Lar Anália Franco é uma associação civil beneficente, filantrópica e de natureza espírita de Londrina, com três frentes: creche, contraturno e bazar beneficente.',
                'content' => <<<'HTML'
                    <p>O Lar Anália Franco de Londrina é uma associação civil beneficente, filantrópica e de natureza espírita. A sede, onde também funciona o CEI Anália Franco, fica na Av. Anália Franco, 33, Jd. Aeroporto, Londrina/PR — telefone (43) 3325-8060.</p><p>Hoje a instituição já opera duas frentes — o Centro de Educação Infantil Anália Franco (CEI Anália Franco), creche e pré-escola conveniada com a Prefeitura de Londrina, e o Bazar Beneficente, loja de doações que sustenta boa parte do orçamento da casa — e prepara uma terceira, a Escola de Contraturno, com estrutura pronta e início de turmas previsto para 2027.</p><p>As páginas desta seção contam a história da instituição, sua estrutura de governança e o que ela é hoje.</p>
                    HTML,
            ],
            [
                'slug' => 'quem-somos/nossa-historia',
                'title' => 'Nossa História',
                'meta_description' => 'De orfanato a instituição com creche, contraturno e bazar: a linha do tempo do Lar Anália Franco de Londrina.',
                'content' => <<<'HTML'
                    <p>O Lar Anália Franco foi criado por um grupo espírita de Londrina, originalmente como orfanato. O atendimento que a instituição presta hoje é laico.</p><p>O nome é uma homenagem a Anália Franco (1853–1919), educadora, jornalista, abolicionista e filantropa que fundou mais de 70 escolas e 23 asilos para crianças órfãs no Brasil.</p><ul><li><p>1953 — fundação da associação, por um grupo espírita, originalmente como orfanato.</p></li><li><p>1957 — início da obra da sede, na Av. Anália Franco.</p></li><li><p>1963 — inauguração da sede.</p></li><li><p>1968 — início do Bazar Beneficente, em funcionamento ininterrupto desde então.</p></li><li><p>2002 — criação do Centro de Educação Infantil Anália Franco, com convênio junto à Secretaria Municipal de Educação de Londrina.</p></li><li><p>2016 — recebe a Medalha Ouro Verde, maior honraria da Câmara Municipal de Londrina.</p></li><li><p>2026 — inauguração da sala de informática da Escola de Contraturno.</p></li></ul>
                    HTML,
            ],
            [
                'slug' => 'quem-somos/missao-visao-valores',
                'title' => 'Missão, Visão e Valores',
                'meta_description' => 'Rascunho de trabalho da missão, visão e valores do Lar Anália Franco, ainda pendente de validação pela direção da instituição.',
                // Fora do escopo de lançamento — página sai do ar (Draft) e da navegação até o
                // texto ser validado pela direção. O status é o controle; o texto público não
                // repete "isto é rascunho" (ver Etapa 3 de docs/tarefas/01-....md).
                'status' => PageStatus::Draft,
                'content' => <<<'HTML'
                    <p>A missão anterior descrevia o antigo serviço de acolhimento, hoje encerrado, e por isso não é mais usada.</p><h2>Missão</h2><p>Sustentar, em Londrina, uma educação infantil de qualidade e oportunidades de formação para adolescentes em situação de vulnerabilidade social, financiadas em parte pelo próprio trabalho da instituição.</p><h2>Visão</h2><p>Ser reconhecida em Londrina como uma instituição que presta contas do que arrecada e do que faz.</p><h2>Valores</h2><ul><li><p>Transparência: os documentos de prestação de contas são públicos, não apenas entregues ao órgão fiscalizador.</p></li><li><p>Continuidade: a creche funciona sem interrupção desde 2002.</p></li><li><p>Autossustentação: o Bazar Beneficente existe desde 1968 para custear o que o convênio público não cobre.</p></li></ul>
                    HTML,
            ],
            [
                'slug' => 'quem-somos/governanca',
                'title' => 'Governança',
                'meta_description' => 'Como o Lar Anália Franco é administrado: associação civil beneficente, filantrópica e de natureza espírita, diretoria eleita e prestação de contas pública.',
                'content' => <<<'HTML'
                    <p>O Lar Anália Franco é uma associação civil beneficente, filantrópica e de natureza espírita, CNPJ 78.614.096/0001-75, administrada por uma diretoria eleita pelos associados.</p><h2>Diretoria — gestão 2026–2027</h2><h3>Diretoria Executiva</h3><ul><li><p>Presidente (interino): Sidnei Pereira do Nascimento</p></li><li><p>Secretário: Marcos Aurélio Batyras</p></li><li><p>Diretor de Patrimônio: Domingos Geraldo Stersa Junior</p></li><li><p>1º Tesoureiro: Marcos Adriano Dornelas Pinheiro</p></li><li><p>2º Tesoureiro: Ângelo Pamplona da Costa</p></li></ul><h3>Conselho Deliberativo</h3><ul><li><p>Presidente: André Luiz Gonçalves Salvador</p></li><li><p>Vice-presidente: Rogério Caetano da Silva</p></li></ul><p>A prestação de contas da instituição — balanços, atas e editais — está reunida na seção <a href="/transparencia">Transparência</a>.</p>
                    HTML,
            ],
        ];
    }

    /**
     * @return list<array{slug: string, title: string, content: string, meta_description: string}>
     */
    private static function educacaoInfantil(): array
    {
        return [
            [
                'slug' => 'educacao-infantil',
                'title' => 'Educação Infantil',
                'meta_description' => 'CEI Anália Franco: creche e pré-escola do Lar Anália Franco para crianças de 1 a 5 anos, 15 turmas, período integral, conveniada com a Prefeitura de Londrina.',
                'content' => <<<'HTML'
                    <p>O Centro de Educação Infantil Anália Franco é a creche e pré-escola do Lar Anália Franco, para crianças de 1 a 5 anos, em 15 turmas (C1 a P5, plano de trabalho do Termo de Colaboração 06/2022, exercício 2026). Funciona em período integral, das 7h30 às 17h30, com convênio junto à Secretaria Municipal de Educação de Londrina.</p><p>O CEI Anália Franco foi criado em 2002 e tem autorização de funcionamento renovada até janeiro de 2028. A rotina inclui aulas de inglês, educação física, horta e uso da quadra poliesportiva do terreno.</p><p>As páginas desta seção detalham a proposta pedagógica, a alimentação e a estrutura física do CEI, além de como fazer a <a href="/educacao-infantil/matricula">matrícula</a>.</p>
                    HTML,
            ],
            [
                'slug' => 'educacao-infantil/dia-da-crianca',
                'title' => 'Dia da Criança',
                'meta_description' => 'O CEI Anália Franco reserva uma programação especial de Dia da Criança, em outubro, para as crianças atendidas pela creche.',
                // Fora do escopo de lançamento — conteúdo de uma frase, não sai do ar até
                // ganhar corpo (ver docs/roadmap.md, pendências do site público).
                'status' => PageStatus::Draft,
                'content' => <<<'HTML'
                    <p>O Centro de Educação Infantil Anália Franco reserva uma programação especial para o Dia da Criança, em outubro, para as crianças atendidas pela creche.</p>
                    HTML,
            ],
            [
                'slug' => 'educacao-infantil/proposta-pedagogica',
                'title' => 'Proposta Pedagógica',
                'meta_description' => 'Rotina do CEI Anália Franco: aulas de inglês, educação física, horta e quadra poliesportiva, em período integral para crianças de 1 a 5 anos.',
                'content' => <<<'HTML'
                    <p>O CEI Anália Franco atende crianças de 1 a 5 anos (turmas de C1 a P5) em período integral, das 7h30 às 17h30, sob convênio com a Secretaria Municipal de Educação de Londrina.</p><p>Fazem parte da rotina aulas de inglês, educação física, atividades na horta da instituição e uso da quadra poliesportiva do terreno.</p>
                    HTML,
            ],
            [
                'slug' => 'educacao-infantil/alimentacao-e-saude',
                'title' => 'Alimentação e Saúde',
                'meta_description' => 'O CEI Anália Franco serve cinco refeições diárias com cardápio de nutricionista e registra alergias e restrições alimentares informadas na matrícula.',
                'content' => <<<'HTML'
                    <p>O CEI Anália Franco serve cinco refeições diárias, com cardápio elaborado por nutricionista, para todas as crianças em período integral.</p><p>Restrições alimentares e alergias informadas pela família na matrícula são registradas e levadas em conta no preparo das refeições da criança.</p>
                    HTML,
            ],
            [
                'slug' => 'educacao-infantil/estrutura',
                'title' => 'Estrutura',
                'meta_description' => 'O CEI Anália Franco funciona no terreno do Lar Anália Franco, com horta e quadra poliesportiva entre os espaços mais usados.',
                'content' => <<<'HTML'
                    <p>O CEI Anália Franco funciona no terreno da instituição, na Av. Anália Franco, 33, Jd. Aeroporto, Londrina/PR.</p><p>O terreno tem horta, usada nas atividades pedagógicas, e quadra poliesportiva, que também vai ser usada pela Escola de Contraturno e, fora desse uso, é alugada como fonte de receita da instituição.</p>
                    HTML,
            ],
            [
                'slug' => 'educacao-infantil/depoimentos',
                'title' => 'Depoimentos',
                'meta_description' => 'O CEI Anália Franco tem avaliação pública de 4,5 estrelas no Google. Depoimentos individuais só entram aqui com autorização de quem os deu.',
                // Fora do escopo de lançamento — página sem depoimento nenhum ainda, não sai
                // do ar até a instituição colher as autorizações (ver docs/contexto.md, "Regras
                // de conteúdo").
                'status' => PageStatus::Draft,
                'content' => <<<'HTML'
                    <p>O CEI Anália Franco tem avaliação pública de 4,5 estrelas, com mais de duas centenas de avaliações, no perfil do Google da instituição.</p><p>Depoimentos de pais e responsáveis só entram nesta página com autorização expressa de quem os deu — nenhuma avaliação pública é reproduzida aqui sem esse consentimento. Esta seção será preenchida à medida que a instituição colher essas autorizações.</p>
                    HTML,
            ],
            [
                'slug' => 'educacao-infantil/matricula',
                'title' => 'Matrícula',
                'meta_description' => 'A matrícula no CEI Anália Franco é feita pela Central de Vagas da Prefeitura de Londrina, na Rua Benjamin Constant, 800, Centro.',
                'content' => <<<'HTML'
                    <p>A matrícula no CEI Anália Franco é feita exclusivamente pela Central de Vagas da Secretaria Municipal de Educação de Londrina — o Lar Anália Franco não recebe pedido de vaga diretamente, nem pelo site nem por telefone.</p><p><strong>Central de Vagas</strong><br />Rua Benjamin Constant, 800 — Centro, Londrina/PR</p><p>Para telefone, horário de atendimento e outros canais de contato, consulte o site oficial da Prefeitura de Londrina.</p><p>Depois que a Prefeitura encaminha a vaga ao CEI Anália Franco, a secretaria da instituição entra em contato para os próximos passos da matrícula efetiva.</p>
                    HTML,
            ],
        ];
    }

    /**
     * @return list<array{slug: string, title: string, content: string, meta_description: string}>
     */
    private static function contraturno(): array
    {
        return [
            [
                'slug' => 'contraturno',
                'title' => 'Escola de Contraturno',
                'meta_description' => 'Programa em preparação do Lar Anália Franco para crianças e adolescentes de 6 a 15 anos, com meta de 100 atendidos. Início previsto para 2027.',
                'content' => <<<'HTML'
                    <p>A Escola de Contraturno é um programa em preparação do Lar Anália Franco, para crianças e adolescentes de 6 a 15 anos de famílias com renda de até 3 salários mínimos. A meta é atender 100 crianças e adolescentes.</p><p>A estrutura já existe: laboratório de informática com 20 computadores doados pelo Centro de Recondicionamento de Computadores, parceria com o SENAI, ginásio de esportes e auditório, no terreno da instituição.</p><p>O programa vai oferecer oficinas de produção audiovisual, produção de podcast, grafite, patrimônio histórico-cultural, capoeira, música, dança, literatura, tecnologias criativas, informática básica e um time de futebol.</p><p>Início das turmas previsto para 2027. Nenhuma turma funciona ainda, e não há aluno matriculado — cadastre-se para ser avisado quando as inscrições abrirem.</p><p><a class="btn btn--primary" href="/contraturno/inscricao">Avise-me quando abrir</a></p><p>As páginas desta seção detalham para quem é o programa, a estrutura já existente, as oficinas previstas e o que vem a seguir.</p>
                    HTML,
            ],
            [
                'slug' => 'contraturno/o-projeto',
                'title' => 'O Projeto',
                'meta_description' => 'A Escola de Contraturno nasceu da receita do Bazar Beneficente, de equipamentos doados e de uma parceria com o SENAI — início previsto para 2027.',
                'content' => <<<'HTML'
                    <p>O projeto nasceu da combinação de coisas que o Lar Anália Franco já tinha: espaço disponível no terreno da instituição e a receita do Bazar Beneficente, que viabilizou o investimento inicial.</p><p>Os computadores do laboratório de informática — 20 ao todo — foram doados pelo Centro de Recondicionamento de Computadores, programa federal de reaproveitamento de equipamentos. A instituição também firmou parceria com o SENAI, e o programa vai usar o ginásio de esportes e o auditório já existentes no terreno.</p><p>O programa ainda não abriu turmas — início previsto para 2027. A meta é atender 100 crianças e adolescentes de 6 a 15 anos de famílias com renda de até 3 salários mínimos.</p>
                    HTML,
            ],
            [
                'slug' => 'contraturno/para-quem-e',
                'title' => 'Para Quem É',
                'meta_description' => 'A Escola de Contraturno é para crianças e adolescentes de 6 a 15 anos de famílias com renda de até 3 salários mínimos, com meta de 100 atendidos.',
                'content' => <<<'HTML'
                    <p>O programa é para crianças e adolescentes de 6 a 15 anos, de famílias com renda de até 3 salários mínimos. A meta é atender 100 crianças e adolescentes.</p><p>O programa ainda não abriu turmas — início previsto para 2027. Outros critérios de seleção, além da faixa etária e da renda familiar, ainda estão em definição.</p>
                    HTML,
            ],
            [
                'slug' => 'contraturno/como-funciona',
                'title' => 'Como Funciona',
                'meta_description' => 'A Escola de Contraturno vai oferecer oficinas de produção audiovisual, podcast, grafite, capoeira, música, dança, literatura e informática básica.',
                'content' => <<<'HTML'
                    <p>O programa vai oferecer oficinas de produção audiovisual, produção de podcast, grafite, patrimônio histórico-cultural, capoeira, música, dança, literatura, tecnologias criativas, informática básica e um time de futebol — usando o laboratório de informática, o ginásio de esportes e o auditório já existentes no terreno da instituição.</p><p>Frequência das oficinas, forma de inscrição e se há vínculo com a escola regular da criança ou adolescente são pontos que a instituição ainda está definindo, por se tratar de um programa que ainda não começou — início previsto para 2027.</p>
                    HTML,
            ],
            [
                'slug' => 'contraturno/parceiros',
                'title' => 'Parceiros',
                'meta_description' => 'O Centro de Recondicionamento de Computadores doou os equipamentos do laboratório de informática, e o SENAI é parceiro da Escola de Contraturno.',
                'content' => <<<'HTML'
                    <p>O Centro de Recondicionamento de Computadores, programa do governo federal de reaproveitamento de equipamentos, doou os 20 computadores do laboratório de informática da Escola de Contraturno.</p><p>O SENAI é parceiro do programa. A lista completa de parceiros — para o contraturno e para os demais pilares — ainda está sendo consolidada.</p>
                    HTML,
            ],
            [
                'slug' => 'contraturno/o-que-vem-por-ai',
                'title' => 'O Que Vem por Aí',
                'meta_description' => 'Início das turmas da Escola de Contraturno previsto para 2027. A direção já declarou intenção de estender cursos de acessibilidade digital a idosos no futuro.',
                'content' => <<<'HTML'
                    <p>Início das turmas previsto para 2027. A abertura das inscrições será anunciada aqui e em contato direto com quem se cadastrar para ser avisado.</p><p>A direção do Lar Anália Franco já declarou a intenção de, no futuro, estender cursos de acessibilidade digital a pessoas acima de 60 anos, além das crianças e adolescentes que o programa vai atender. Essa expansão ainda não tem data nem formato definidos.</p><p><a class="btn btn--primary" href="/contraturno/inscricao">Avise-me quando abrir</a></p>
                    HTML,
            ],
        ];
    }

    /**
     * @return list<array{slug: string, title: string, content: string, meta_description: string}>
     */
    private static function bazar(): array
    {
        return [
            [
                'slug' => 'bazar',
                'title' => 'Bazar Beneficente',
                'meta_description' => 'O Bazar Beneficente do Lar Anália Franco existe desde 1968 e financia parte relevante do orçamento da instituição.',
                'content' => <<<'HTML'
                    <p>O Bazar Beneficente existe desde 1968, sem interrupção. É uma loja física que recebe doações de itens e revende ao público, e financia parte relevante do orçamento do Lar Anália Franco — o que o convênio da creche com a Prefeitura de Londrina não cobre.</p><p>É a receita do bazar que está viabilizando a Escola de Contraturno, programa em preparação com início de turmas previsto para 2027.</p><p>As páginas desta seção explicam onde fica a loja, o que a instituição aceita em doação e para onde vai o resultado das vendas.</p>
                    HTML,
            ],
            [
                'slug' => 'bazar/visite-a-loja',
                'title' => 'Visite a Loja',
                'meta_description' => 'O Bazar Beneficente funciona em endereço próprio, na Rua Rosa Siqueira, 152, Jd. Aeroporto, em Londrina/PR — separado da sede do Lar Anália Franco.',
                'content' => <<<'HTML'
                    <p>O Bazar Beneficente funciona em endereço próprio, separado da sede do Lar Anália Franco: Rua Rosa Siqueira, 152, Jd. Aeroporto, Londrina/PR. Telefone (43) 3322-2373 ou <a target="_blank" href="https://wa.me/5543999500183?text&#61;Ol%C3%A1!%20Gostaria%20de%20agendar%20uma%20coleta%20de%20doa%C3%A7%C3%A3o%20para%20o%20Bazar%20Beneficente." rel="noopener noreferrer">WhatsApp (43) 99950-0183</a>.</p>
                    HTML,
            ],
            [
                'slug' => 'bazar/o-que-aceitamos',
                'title' => 'O Que Aceitamos',
                'meta_description' => 'O Bazar Beneficente do Lar Anália Franco recebe doações de itens para revenda na loja física da instituição.',
                'content' => <<<'HTML'
                    <p>O Bazar Beneficente recebe doações de itens para revenda na loja física da instituição.</p><p>A lista detalhada do que é aceito — e do que não é — está em confirmação com a equipe do bazar. Para <a href="/bazar/agendar-coleta">agendar uma coleta</a>, o jeito mais rápido é pelo <a target="_blank" href="https://wa.me/5543999500183?text&#61;Ol%C3%A1!%20Gostaria%20de%20agendar%20uma%20coleta%20de%20doa%C3%A7%C3%A3o%20para%20o%20Bazar%20Beneficente." rel="noopener noreferrer">WhatsApp (43) 99950-0183</a>.</p>
                    HTML,
            ],
            [
                'slug' => 'bazar/para-onde-vai',
                'title' => 'Para Onde Vai',
                'meta_description' => 'A receita do Bazar Beneficente sustenta o que o convênio com a Prefeitura de Londrina não cobre, incluindo a nova Escola de Contraturno.',
                'content' => <<<'HTML'
                    <p>A receita do Bazar Beneficente sustenta parte relevante do orçamento do Lar Anália Franco — o convênio com a Prefeitura de Londrina cobre a creche, e é o bazar que banca o restante da operação da instituição.</p><p>É a receita do bazar, por exemplo, que está viabilizando a Escola de Contraturno, programa em preparação com equipamentos já doados pelo Centro de Recondicionamento de Computadores e início de turmas previsto para 2027.</p><p>Os números exatos de arrecadação e destinação estão nos documentos reunidos na seção <a href="/transparencia">Transparência</a>.</p>
                    HTML,
            ],
            [
                'slug' => 'bazar/sua-compra-vira-educacao',
                'title' => 'Sua Compra Vira Educação',
                'meta_description' => 'Cada peça comprada no Bazar Beneficente ajuda a sustentar o CEI Anália Franco e a Escola de Contraturno do Lar Anália Franco.',
                'content' => <<<'HTML'
                    <p>Cada peça comprada no Bazar Beneficente ajuda a sustentar o Centro de Educação Infantil Anália Franco e a Escola de Contraturno — as duas frentes educacionais do Lar Anália Franco, uma já em funcionamento e outra em preparação.</p><p>O convênio da instituição com a Prefeitura de Londrina cobre a creche, mas não cobre tudo. É a receita do bazar que completa o que falta, e que está viabilizando a Escola de Contraturno, com início de turmas previsto para 2027.</p>
                    HTML,
            ],
        ];
    }

    /**
     * @return list<array{slug: string, title: string, content: string, meta_description: string}>
     */
    private static function comoAjudar(): array
    {
        return [
            [
                'slug' => 'como-ajudar',
                'title' => 'Como Ajudar',
                'meta_description' => 'Formas de apoiar o Lar Anália Franco: PIX, transferência bancária e doação de itens.',
                'content' => <<<'HTML'
                    <p>O Lar Anália Franco não opera gateway de pagamento no site — as formas de contribuir hoje passam por canais já existentes, explicados nas páginas desta seção.</p><p>É possível apoiar a instituição via PIX ou transferência bancária, ou doando itens para o Bazar Beneficente.</p>
                    HTML,
            ],
            [
                'slug' => 'como-ajudar/doar',
                'title' => 'Doar',
                'meta_description' => 'Doe para o Lar Anália Franco via PIX ou transferência bancária.',
                'content' => <<<'HTML'
                    <p>O Lar Anália Franco ainda não processa doações em dinheiro diretamente pelo site. Doe por PIX ou transferência bancária:</p><ul><li><p><strong>PIX</strong> (chave CNPJ): 78.614.096/0001-75 — nome exibido: LAR ANALIA FRANCO DE LONDRINA</p></li><li><p><strong>Transferência:</strong> Banco do Brasil (001), agência 2755-3, conta corrente 4963-8</p></li></ul><p>Doações de itens para o Bazar Beneficente também sustentam diretamente a instituição.</p>
                    HTML,
            ],
            [
                'slug' => 'como-ajudar/parceiros',
                'title' => 'Parceiros',
                'meta_description' => 'Parcerias que sustentam os três pilares do Lar Anália Franco, da doação de equipamentos ao convênio com a Secretaria de Educação.',
                'content' => <<<'HTML'
                    <p>O Lar Anália Franco mantém parcerias que sustentam seus três pilares — da doação de equipamentos de informática pelo Centro de Recondicionamento de Computadores, programa do governo federal, ao convênio com a Secretaria Municipal de Educação de Londrina para o Centro de Educação Infantil Anália Franco.</p><p>A lista completa de parceiros e apoiadores da instituição está sendo consolidada.</p>
                    HTML,
            ],
        ];
    }

    /**
     * @return list<array{slug: string, title: string, content: string, meta_description: string}>
     */
    private static function transparencia(): array
    {
        return [
            [
                'slug' => 'transparencia',
                'title' => 'Transparência',
                'meta_description' => 'O acervo de prestação de contas do Lar Anália Franco — balanços, atas e editais — organizado e público.',
                'content' => <<<'HTML'
                    <p>O Lar Anália Franco mantém um acervo de prestação de contas — balanços, atas, editais e relatórios — hoje com {{documentos_transparencia}}.</p><p>Reunimos esses documentos nesta página para que qualquer pessoa consiga localizar o que precisa.</p><p>O convênio com a Secretaria Municipal de Educação de Londrina, que sustenta o Centro de Educação Infantil Anália Franco, também exige prestação de contas periódica. O repasse municipal previsto para 2026 é de R$ 2.819.892,84 (Termo de Colaboração 06/2022).</p><p>Acesse o <a href="/transparencia/documentos">acervo de documentos</a>, com filtro por ano e por tipo.</p>
                    HTML,
            ],
        ];
    }
}
