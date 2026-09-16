<?php

declare(strict_types=1);

namespace App\Support\Html;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * `class` em `<a>` existe só para o botão de chamada para ação do site (ver
 * frontend-site/app/assets/css/components.css) — duas páginas do conteúdo atual usam
 * `class="btn btn--primary"`. Sem restrição de valor, o campo viraria um jeito de aplicar
 * qualquer classe do CSS do site dentro do texto; com allowlist de token, o que já existe
 * continua funcionando e nada além disso entra.
 */
final class LinkClassAttributeSanitizer implements AttributeSanitizerInterface
{
    /**
     * @var list<string>
     */
    private const ALLOWED_TOKENS = ['btn', 'btn--primary', 'btn--secondary'];

    /**
     * @return list<string>
     */
    public function getSupportedElements(): array
    {
        return ['a'];
    }

    /**
     * @return list<string>
     */
    public function getSupportedAttributes(): array
    {
        return ['class'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        $tokens = array_values(array_intersect(
            preg_split('/\s+/', trim($value)) ?: [],
            self::ALLOWED_TOKENS,
        ));

        // Nenhum token conhecido sobrou: devolve null para o atributo sumir por inteiro, em
        // vez de deixar um class="" vazio no HTML salvo.
        return $tokens === [] ? null : implode(' ', $tokens);
    }
}
