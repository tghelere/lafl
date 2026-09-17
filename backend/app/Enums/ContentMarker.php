<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Marcadores que o pessoal da instituição escreve dentro do texto de uma página do CMS para
 * que o número saia calculado, nunca digitado (ver docs/tarefas/02-numeros-calculados.md).
 *
 * São texto puro, de propósito: atravessam o editor Tiptap do painel e o
 * App\Support\Html\ContentSanitizer sem alteração, porque `{` e `}` não são sintaxe de HTML.
 * Nome em português — quem escreve o conteúdo é quem os digita.
 *
 * Quem resolve é App\Actions\Content\ResolveContentMarkers, e SÓ na leitura pública: o
 * endpoint administrativo devolve o marcador cru, senão o primeiro salvamento gravaria o
 * número do dia em texto fixo e o cálculo morreria em silêncio.
 */
enum ContentMarker: string
{
    /**
     * Casa QUALQUER `{{...}}` escrito no conteúdo, conhecido ou não, com o nome no grupo 1 —
     * é o que permite recusar marcador inventado ao salvar (App\Actions\Content\
     * AssertContentMarkersAreKnown) em vez de deixá-lo vazar cru para o site. Espaço em volta
     * do nome é tolerado: quem escreve `{{ idade_bazar }}` quis o mesmo marcador.
     */
    public const PATTERN = '/\{\{\s*([^{}]*?)\s*\}\}/';

    case AssociationAge = 'idade_associacao';
    case HeadquartersAge = 'idade_sede';
    case BazaarAge = 'idade_bazar';
    case CeiAge = 'idade_cei';
    case TransparencyDocumentCount = 'documentos_transparencia';

    /**
     * O marcador como se escreve no conteúdo da página.
     */
    public function placeholder(): string
    {
        return '{{'.$this->value.'}}';
    }

    /**
     * Rótulo curto para a lista de marcadores do editor de páginas.
     */
    public function label(): string
    {
        return match ($this) {
            self::AssociationAge => 'Idade da associação',
            self::HeadquartersAge => 'Idade da sede',
            self::BazaarAge => 'Tempo de funcionamento do bazar',
            self::CeiAge => 'Tempo de funcionamento do CEI Anália Franco',
            self::TransparencyDocumentCount => 'Documentos publicados em Transparência',
        };
    }

    /**
     * Chave do marco em `config('institution.milestones')` de que sai a idade, ou null quando
     * o valor do marcador não é idade.
     */
    public function milestone(): ?string
    {
        return match ($this) {
            self::AssociationAge => 'association_founded',
            self::HeadquartersAge => 'headquarters_inaugurated',
            self::BazaarAge => 'bazaar_opened',
            self::CeiAge => 'cei_created',
            self::TransparencyDocumentCount => null,
        };
    }

    /**
     * Todos os marcadores válidos como se escrevem, para mensagem de erro e documentação.
     *
     * @return list<string>
     */
    public static function placeholders(): array
    {
        return array_map(static fn (self $marker): string => $marker->placeholder(), self::cases());
    }
}
