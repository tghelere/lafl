<?php

declare(strict_types=1);

namespace App\Support\Transparency;

use App\Models\TransparencyDocument;

/**
 * URL canônica pública de um documento de transparência — no domínio do SITE, não no da API.
 *
 * O PDF é servido por uma rota do servidor Nitro que faz proxy para a API
 * (frontend-site/server/routes/transparencia/documentos/[year]/[slug].get.ts): é o site que
 * precisa ser indexado, e um PDF hospedado noutro host contaria como conteúdo de outro site
 * na busca. Quem monta a URL é o backend, e só ele — o site nunca concatena este caminho à
 * mão (regra 1 do CLAUDE.md: nenhuma regra de negócio no frontend); o caminho chega pronto no
 * campo `path` de App\Http\Resources\Public\TransparencyDocumentResource.
 *
 * A base absoluta vem de `forms.site_base_url` (env SITE_BASE_URL), a mesma que o e-mail de
 * notificação já usa para montar URL de asset do site.
 */
final class DocumentUrl
{
    /**
     * Caminho relativo à raiz do site, ex.: "/transparencia/documentos/2024/balanco-2024.pdf".
     */
    public static function path(TransparencyDocument $document): string
    {
        return "/transparencia/documentos/{$document->year}/{$document->slug}.pdf";
    }

    /**
     * URL absoluta — usada onde um caminho relativo não serve: o cabeçalho `Location` do 301
     * que a API devolve (o redirect sai de um host e aponta para outro) e o `<loc>` do
     * sitemap.
     */
    public static function absolute(TransparencyDocument $document): string
    {
        return rtrim((string) config('forms.site_base_url'), '/').self::path($document);
    }
}
