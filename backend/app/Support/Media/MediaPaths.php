<?php

declare(strict_types=1);

namespace App\Support\Media;

use App\Models\Media;

/**
 * Onde os arquivos de uma imagem moram no disco "local" (fora do webroot). O caminho é sempre
 * derivado — uuid, versão, extensão —, nunca lido de coluna: o nome que a pessoa deu ao
 * arquivo no computador dela não chega ao disco nem ao banco.
 */
final class MediaPaths
{
    public static function directory(Media $media, ?int $version = null): string
    {
        return 'media/'.$media->uuid.'/'.($version ?? $media->version);
    }

    public static function original(Media $media, ?int $version = null, ?string $extension = null): string
    {
        return self::directory($media, $version).'/original.'.($extension ?? $media->extension);
    }

    public static function derivative(Media $media, int $width, ?int $version = null): string
    {
        return self::directory($media, $version).'/'.$width.'.webp';
    }

    /** Pasta de todas as versões — é o que a exclusão apaga. */
    public static function root(Media $media): string
    {
        return 'media/'.$media->uuid;
    }
}
