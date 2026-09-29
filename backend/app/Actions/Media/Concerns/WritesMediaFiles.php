<?php

declare(strict_types=1);

namespace App\Actions\Media\Concerns;

use App\Models\Media;
use App\Services\Media\ProcessedImage;
use App\Support\Media\MediaPaths;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

trait WritesMediaFiles
{
    /**
     * Grava a original e as derivadas na pasta da versão dada. A pasta é nova a cada versão,
     * então nunca sobrescreve arquivo que esteja sendo servido.
     */
    private function writeFiles(Media $media, int $version, ProcessedImage $image): void
    {
        $disk = Storage::disk('local');

        $files = [MediaPaths::original($media, $version, $image->extension) => $image->original];
        foreach ($image->derivatives as $width => $bytes) {
            $files[MediaPaths::derivative($media, $width, $version)] = $bytes;
        }

        foreach ($files as $path => $bytes) {
            if (! $disk->put($path, $bytes)) {
                throw new RuntimeException("Falha ao gravar {$path}.");
            }
        }
    }

    private function fillFromImage(Media $media, ProcessedImage $image): void
    {
        $media->mime = $image->mime;
        $media->extension = $image->extension;
        $media->size = strlen($image->original);
        $media->width = $image->width;
        $media->height = $image->height;
        $media->widths = $image->widths();
        $media->sha256 = $image->sha256();
    }
}
