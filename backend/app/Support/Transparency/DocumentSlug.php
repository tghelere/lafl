<?php

declare(strict_types=1);

namespace App\Support\Transparency;

use App\Models\TransparencyDocument;
use Illuminate\Support\Str;

/**
 * Gera o slug legível de um documento de transparência a partir do título.
 *
 * O slug é gravado na CRIAÇÃO e nunca recalculado: renomear o título depois não pode trocar
 * a URL de um PDF já indexado pelo Google (ver docs/tarefas/06-seo-e-pdfs-da-transparencia.md,
 * etapa 3). Quem chama é App\Actions\Transparency\SaveTransparencyDocument — e a migration
 * que criou a coluna, para os documentos que já existiam.
 *
 * O ano NÃO entra no slug: ele já é um segmento próprio da URL
 * (/transparencia/documentos/{ano}/{slug}.pdf), e mantê-lo fora significa que corrigir o ano
 * de um documento não invalida o slug — só muda o segmento de ano, e a URL antiga passa a
 * responder 301 (ver App\Http\Controllers\Api\V1\Public\TransparencyDocumentController::file).
 */
final class DocumentSlug
{
    /**
     * Títulos são `string` de até 255 caracteres; sem corte, um título longo viraria uma URL
     * que nenhum ser humano copia. O corte é feito no hífen anterior ao limite para não
     * partir palavra ao meio.
     */
    private const MAX_LENGTH = 120;

    /**
     * Título só com pontuação ou emoji produz slug vazio — cai aqui em vez de gerar uma URL
     * terminada em barra.
     */
    private const FALLBACK = 'documento';

    /**
     * Slug único na tabela inteira (a unicidade é do slug, não do par ano+slug: a URL é
     * resolvida só pelo slug, e o ano é conferido depois). O índice UNIQUE da coluna é a
     * garantia final — este método é quem escolhe um nome bonito; dois cadastros simultâneos
     * com o mesmo título ainda podem colidir no banco, e aí o segundo falha em vez de gravar
     * duplicata.
     */
    public static function unique(string $title): string
    {
        $base = self::base($title);
        $candidate = $base;
        $suffix = 1;

        while (self::taken($candidate)) {
            $suffix++;
            $candidate = $base.'-'.$suffix;
        }

        return $candidate;
    }

    public static function base(string $title): string
    {
        $slug = Str::slug($title);

        if ($slug === '') {
            return self::FALLBACK;
        }

        if (mb_strlen($slug) <= self::MAX_LENGTH) {
            return $slug;
        }

        $cut = mb_substr($slug, 0, self::MAX_LENGTH);
        $lastHyphen = mb_strrpos($cut, '-');

        return trim($lastHyphen === false ? $cut : mb_substr($cut, 0, $lastHyphen), '-');
    }

    private static function taken(string $slug): bool
    {
        return TransparencyDocument::query()
            // withTrashed: documento excluído continua ocupando a linha (soft delete) e o
            // índice UNIQUE não sabe de deleted_at — reaproveitar o slug daria erro de banco.
            ->withTrashed()
            ->where('slug', $slug)
            ->exists();
    }
}
