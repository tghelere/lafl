<?php

declare(strict_types=1);

namespace App\Support\Html;

use App\Support\Media\MediaUrl;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * `src` de `<img>` no conteúdo das páginas só pode ser a forma canônica de uma imagem da
 * própria biblioteca (`/midia/{uuid}`, ver App\Support\Media\MediaUrl). Qualquer outra coisa
 * — endereço externo, `data:`, caminho relativo para outro lugar do site, a mesma imagem com
 * largura ou query string — perde o atributo, e o `<img>` sem `src` é retirado depois (ver
 * ContentSanitizer::dropImagesWithoutSource).
 *
 * Endereço externo nunca: imagem de outro servidor é um rastreador de quem visita o site (o
 * servidor dela recebe IP e página de cada visitante), foge da remoção de EXIF, e pode trocar
 * de conteúdo sem ninguém da instituição saber.
 */
final class MediaSourceAttributeSanitizer implements AttributeSanitizerInterface
{
    /**
     * @return list<string>
     */
    public function getSupportedElements(): array
    {
        return ['img'];
    }

    /**
     * @return list<string>
     */
    public function getSupportedAttributes(): array
    {
        return ['src'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        $value = strtolower(trim($value));

        return preg_match(MediaUrl::CANONICAL_PATTERN, $value) === 1 ? $value : null;
    }
}
