<?php

declare(strict_types=1);

namespace App\Support\Media;

/**
 * Os endereços públicos de uma imagem, no domínio do SITE (servidos pela rota de proxy
 * frontend-site/server/routes/midia/, o mesmo desenho do PDF de transparência — ver
 * docs/decisoes/0017-url-publica-dos-documentos-de-transparencia.md).
 *
 * `/midia/{uuid}` é a forma CANÔNICA, a única que o conteúdo das páginas guarda: não tem
 * largura nem versão, e por isso sobrevive à substituição do arquivo. As larguras concretas
 * (`/midia/{uuid}/{largura}.webp`) só aparecem no HTML que o site recebe, montadas na leitura
 * pública por App\Actions\Media\ExpandContentImages a partir das derivadas que existem agora.
 */
final class MediaUrl
{
    /** Casa exatamente a forma canônica; o grupo 1 é o uuid. */
    public const CANONICAL_PATTERN = '#^/midia/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})$#';

    /** A mesma forma, para achar dentro de um HTML (atributo `src` entre aspas duplas). */
    public const CANONICAL_IN_HTML_PATTERN = '#src="/midia/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})"#';

    public static function canonical(string $uuid): string
    {
        return '/midia/'.$uuid;
    }

    public static function derivative(string $uuid, int $width): string
    {
        return '/midia/'.$uuid.'/'.$width.'.webp';
    }

    /**
     * `srcset` com as derivadas que existem AGORA (a lista muda quando o arquivo é trocado).
     *
     * @param  list<int>  $widths
     */
    public static function srcset(string $uuid, array $widths): string
    {
        return implode(', ', array_map(
            static fn (int $width): string => self::derivative($uuid, $width).' '.$width.'w',
            $widths,
        ));
    }
}
