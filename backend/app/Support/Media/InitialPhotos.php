<?php

declare(strict_types=1);

namespace App\Support\Media;

use App\Enums\PageImageRole;

/**
 * As fotos que o site mostrava até a sessão 28 como arquivos fixos em
 * `frontend-site/public/fotos/`, agora carregadas na biblioteca por
 * `midia:importar-fotos-iniciais` (App\Actions\Media\ImportInitialPhotos).
 *
 * É o par de App\Support\Content\InitialPages para imagens, com o mesmo estatuto: **só
 * semente**. Depois do lançamento, o banco é a fonte de verdade (ver
 * docs/decisoes/0023-banco-e-a-fonte-de-verdade-do-conteudo.md), e a equipe troca, corrige e
 * reorganiza as fotos pelo painel sem que nada aqui acompanhe.
 *
 * Os arquivos ficam em `backend/resources/initial-photos/{chave}.jpg`: a maior largura que o
 * site já servia de cada foto (a original de câmera nunca esteve no repositório), já sem EXIF
 * desde a preparação manual (docs/fotos.md). Mesmo assim passam pelo mesmo processamento de
 * um envio pelo painel.
 *
 * `alt` e `caption` são os textos que o site já exibia, copiados de
 * `frontend-site/app/data/fotos.ts` e das legendas escritas em `nossa-historia.vue`. A
 * `recepcao` estava em `public/fotos/` sem entrada em `fotos.ts`, e o texto dela veio do
 * catálogo de docs/fotos.md. Nenhum texto foi inventado nesta migração, e todas as 24 fotos
 * tinham texto alternativo.
 *
 * `places` diz onde a foto entra. A ORDEM da lista é a ordem na galeria de cada página. A capa
 * não aparece na própria página, e sim onde o site representa a página (App\Enums\
 * PageImageRole). Sete fotos não têm lugar porque nenhuma página as mostrava. Entram só na
 * biblioteca, à disposição da equipe.
 *
 * Todas declaradas como NÃO mostrando criança ou adolescente atendido. Foram conferidas uma a
 * uma na sessão 28: são prédios, salas vazias, o bazar e a equipe (adultos) numa formação.
 *
 * @phpstan-type Place array{page: string, role: PageImageRole}
 * @phpstan-type Photo array{alt: string, caption: ?string, places: list<Place>}
 */
final class InitialPhotos
{
    public static function directory(): string
    {
        return resource_path('initial-photos');
    }

    public static function path(string $key): string
    {
        return self::directory().'/'.$key.'.jpg';
    }

    /**
     * @return array<string, Photo>
     */
    public static function all(): array
    {
        $cover = PageImageRole::Cover;
        $gallery = PageImageRole::Gallery;

        return [
            // Página inicial: o destaque é a capa de "Quem somos".
            'fachada-sede' => [
                'alt' => 'Fachada da sede do Lar Anália Franco, com o letreiro da instituição sobre a entrada principal',
                'caption' => null,
                'places' => [['page' => 'quem-somos', 'role' => $cover]],
            ],
            'equipe-formacao' => [
                'alt' => 'Equipe do Lar Anália Franco reunida em sessão de formação no auditório',
                'caption' => null,
                'places' => [['page' => 'quem-somos', 'role' => $gallery]],
            ],

            // Nossa história: a primeira da galeria é a placa em destaque, e as outras seguem em
            // pares de antes e depois (frontend-site/app/pages/quem-somos/nossa-historia.vue).
            'placa-inauguracao' => [
                'alt' => 'Placa de bronze na parede da sede: obra iniciada em 18 de abril de 1957 e inaugurada em 15 de novembro de 1963',
                'caption' => 'Placa de bronze na entrada da sede, com a frase dos fundadores.',
                'places' => [['page' => 'quem-somos/nossa-historia', 'role' => $gallery]],
            ],
            'fachada-antes' => [
                'alt' => 'Fachada da sede antes da reforma, com pintura desgastada em cinza e azul',
                'caption' => 'Fachada — antes',
                'places' => [['page' => 'quem-somos/nossa-historia', 'role' => $gallery]],
            ],
            'fachada-depois' => [
                'alt' => 'Fachada da sede depois da reforma, vista do jardim da entrada',
                'caption' => 'Fachada — depois (registro de época)',
                'places' => [['page' => 'quem-somos/nossa-historia', 'role' => $gallery]],
            ],
            'patio-antes' => [
                'alt' => 'Pátio interno antes da reforma, com parede de tinta descascada',
                'caption' => 'Pátio interno — antes',
                'places' => [['page' => 'quem-somos/nossa-historia', 'role' => $gallery]],
            ],
            'patio-depois' => [
                'alt' => 'Pátio interno depois da reforma, com parede pintada de branco e faixa geométrica colorida',
                'caption' => 'Pátio interno — depois (registro de época)',
                'places' => [['page' => 'quem-somos/nossa-historia', 'role' => $gallery]],
            ],

            // Educação infantil: a horta é também a capa (cartão de "O que fazemos").
            'horta-kids' => [
                'alt' => 'Horta Kids: canteiros feitos com pneus coloridos diante de um muro com desenho de crianças plantando',
                'caption' => null,
                'places' => [
                    ['page' => 'educacao-infantil', 'role' => $gallery],
                    ['page' => 'educacao-infantil', 'role' => $cover],
                ],
            ],
            'sala-multiuso-conto' => [
                'alt' => 'Sala multiuso com tapete de tatame colorido, cantinho da Hora do Conto e Casa do Faz de Conta',
                'caption' => null,
                'places' => [['page' => 'educacao-infantil', 'role' => $gallery]],
            ],
            'sala-multiuso-imaginacao' => [
                'alt' => 'Cantinho Mundo da Imaginação, com brinquedos, casa de bonecas e tatame colorido',
                'caption' => null,
                'places' => [['page' => 'educacao-infantil', 'role' => $gallery]],
            ],
            'sala-multiuso-brinquedos' => [
                'alt' => 'Brinquedoteca da sala multiuso, com fantasias, carrinhos e material de faz de conta',
                'caption' => null,
                'places' => [['page' => 'educacao-infantil', 'role' => $gallery]],
            ],

            // Bazar: a entrada é a capa (cartão de "O que fazemos"), não a primeira da galeria.
            'bazar-placa' => [
                'alt' => 'Placa externa do Bazar Beneficente do Lar Anália Franco, com telefone e endereço',
                'caption' => null,
                'places' => [['page' => 'bazar', 'role' => $gallery]],
            ],
            'bazar-entrada' => [
                'alt' => 'Entrada do Bazar Beneficente, com toldo azul e carrinhos de compra ao lado',
                'caption' => null,
                'places' => [
                    ['page' => 'bazar', 'role' => $gallery],
                    ['page' => 'bazar', 'role' => $cover],
                ],
            ],
            'bazar-moveis' => [
                'alt' => 'Setor de móveis do bazar, com sofás, poltronas e utensílios à venda',
                'caption' => null,
                'places' => [['page' => 'bazar', 'role' => $gallery]],
            ],
            'bazar-salao' => [
                'alt' => 'Salão do bazar com araras de roupas, manequins e chapéus expostos',
                'caption' => null,
                'places' => [['page' => 'bazar', 'role' => $gallery]],
            ],

            'almoxarifado-alimentos' => [
                'alt' => 'Almoxarifado de alimentos, com prateleiras organizadas de arroz, feijão e mantimentos',
                'caption' => null,
                'places' => [['page' => 'transparencia', 'role' => $gallery]],
            ],
            'almoxarifado-limpeza' => [
                'alt' => 'Almoxarifado de materiais de limpeza e higiene, com prateleiras organizadas',
                'caption' => null,
                'places' => [['page' => 'transparencia', 'role' => $gallery]],
            ],

            // Sem lugar em página nenhuma hoje: só na biblioteca.
            'fachada-sede-atual' => [
                'alt' => 'Entrada da sede do Lar Anália Franco vista do gramado da frente',
                'caption' => null,
                'places' => [],
            ],
            'recanto-da-amizade' => [
                'alt' => 'Entrada do Recanto da Amizade, área arborizada com mesas no terreno da instituição',
                'caption' => null,
                'places' => [],
            ],
            'recanto-vista' => [
                'alt' => 'Área arborizada do Recanto da Amizade, com mangueiras e mesas de concreto',
                'caption' => null,
                'places' => [],
            ],
            'recepcao' => [
                'alt' => 'Recepção da sede, com parede listrada em laranja e amarelo',
                'caption' => null,
                'places' => [],
            ],
            'horta-kids-vista' => [
                'alt' => 'Vista ampla da Horta Kids, com canteiros de pneu no gramado e painéis de paletes no muro',
                'caption' => null,
                'places' => [],
            ],
            'bazar-deposito' => [
                'alt' => 'Depósito de móveis do bazar, com mesas, cadeiras e armários organizados',
                'caption' => null,
                'places' => [],
            ],
            'bazar-leitura' => [
                'alt' => 'Setor de livros do bazar, com estantes cheias e bancos para leitura',
                'caption' => null,
                'places' => [],
            ],
        ];
    }
}
