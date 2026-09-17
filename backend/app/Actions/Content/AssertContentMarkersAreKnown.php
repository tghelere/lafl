<?php

declare(strict_types=1);

namespace App\Actions\Content;

use App\Enums\ContentMarker;
use Illuminate\Validation\ValidationException;

/**
 * Recusa o salvamento de uma página cujo conteúdo traga um marcador que não existe (ver
 * App\Enums\ContentMarker).
 *
 * A recusa é na escrita, não na leitura, porque é na escrita que existe alguém a quem avisar:
 * quem digitou `{{idade_da_casa}}` ainda está na tela e corrige. Descobrir na leitura só
 * daria a escolha entre publicar o marcador cru ou apagar o trecho em silêncio.
 */
final class AssertContentMarkersAreKnown
{
    public function handle(string $content): void
    {
        preg_match_all(ContentMarker::PATTERN, $content, $matches);

        /** @var list<string> $found */
        $found = $matches[1];
        $unknown = array_values(array_unique(array_filter(
            $found,
            static fn (string $name): bool => ContentMarker::tryFrom($name) === null,
        )));

        if ($unknown === []) {
            return;
        }

        $invalid = self::join(array_map(static fn (string $name): string => '{{'.$name.'}}', $unknown));
        $valid = self::join(ContentMarker::placeholders());

        throw ValidationException::withMessages([
            'content' => [
                count($unknown) === 1
                    ? "O conteúdo usa um marcador que não existe: {$invalid}. Os marcadores disponíveis são {$valid}."
                    : "O conteúdo usa marcadores que não existem: {$invalid}. Os marcadores disponíveis são {$valid}.",
            ],
        ]);
    }

    /**
     * Lista em português: vírgula entre os primeiros e "e" antes do último.
     *
     * @param  list<string>  $items
     */
    private static function join(array $items): string
    {
        if (count($items) < 2) {
            return implode('', $items);
        }

        $last = array_pop($items);

        return implode(', ', $items).' e '.$last;
    }
}
