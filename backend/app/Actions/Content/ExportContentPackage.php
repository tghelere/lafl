<?php

declare(strict_types=1);

namespace App\Actions\Content;

use App\Models\Media;
use App\Models\Page;
use App\Models\TransparencyDocument;
use App\Support\Content\ContentPackage;
use App\Support\Media\MediaPaths;
use App\Support\Media\MediaUrl;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Grava o conteúdo institucional deste ambiente num pacote (ver App\Support\Content\
 * ContentPackage). Só lê: nada no banco nem no disco muda.
 *
 * Falha alto, e antes de terminar, se um documento aponta para um arquivo que não existe no
 * disco — um pacote que "exportou" mas não leva o PDF seria descoberto só em produção.
 */
final class ExportContentPackage
{
    /**
     * @return array{path: string, pages: int, documents: int, media: int, files: int}
     */
    public function handle(string $destinationDir): array
    {
        $root = rtrim($destinationDir, '/').'/conteudo-'.now()->format('Ymd-His');

        if (file_exists($root)) {
            throw new RuntimeException("O destino já existe: {$root}");
        }

        $disk = Storage::disk('local');

        $pages = array_values(Page::query()
            ->with('slugHistory')
            ->orderBy('slug')
            ->get()
            ->map(fn (Page $page): array => [
                'uuid' => $page->uuid,
                'slug' => $page->slug,
                'title' => $page->title,
                'content' => $page->content,
                'meta_title' => $page->meta_title,
                'meta_description' => $page->meta_description,
                'status' => $page->status->value,
                'published_at' => $page->published_at?->toIso8601String(),
                'created_at' => $page->created_at?->toIso8601String(),
                'updated_at' => $page->updated_at?->toIso8601String(),
                'slug_history' => $page->slugHistory->pluck('slug')->sort()->values()->all(),
            ])
            ->all());

        $documents = [];
        $files = [];

        foreach (TransparencyDocument::query()->orderBy('slug')->get() as $document) {
            if (preg_match(ContentPackage::DOCUMENT_FILE_PATTERN, $document->file_path) !== 1) {
                throw new RuntimeException("Caminho de arquivo inesperado no documento {$document->slug}: {$document->file_path}");
            }

            if (! $disk->exists($document->file_path)) {
                throw new RuntimeException("O arquivo do documento {$document->slug} não existe no disco: {$document->file_path}");
            }

            $contents = (string) $disk->get($document->file_path);
            $sha256 = hash('sha256', $contents);

            $files[$document->file_path] = ['sha256' => $sha256, 'size' => strlen($contents), 'contents' => $contents];

            $documents[] = [
                'uuid' => $document->uuid,
                'slug' => $document->slug,
                'title' => $document->title,
                'year' => $document->year,
                'type' => $document->type->value,
                'file_path' => $document->file_path,
                'file_size' => $document->file_size,
                'sha256' => $sha256,
                'published_at' => $document->published_at?->toIso8601String(),
                'created_at' => $document->created_at?->toIso8601String(),
                'updated_at' => $document->updated_at?->toIso8601String(),
            ];
        }

        $media = $this->exportMedia($pages, $files);

        $pagesJson = $this->json($pages);
        $documentsJson = $this->json($documents);
        $mediaJson = $this->json($media);

        $manifest = $this->json([
            'format_version' => ContentPackage::FORMAT_VERSION,
            'generated_at' => now()->toIso8601String(),
            'source_environment' => app()->environment(),
            'counts' => ['pages' => count($pages), 'documents' => count($documents), 'media' => count($media), 'files' => count($files)],
            'checksums' => [
                ContentPackage::PAGES => hash('sha256', $pagesJson),
                ContentPackage::DOCUMENTS => hash('sha256', $documentsJson),
                ContentPackage::MEDIA => hash('sha256', $mediaJson),
            ],
        ]);

        $this->write($root.'/'.ContentPackage::PAGES, $pagesJson);
        $this->write($root.'/'.ContentPackage::DOCUMENTS, $documentsJson);
        $this->write($root.'/'.ContentPackage::MEDIA, $mediaJson);

        foreach ($files as $path => $file) {
            $this->write($root.'/'.ContentPackage::FILES_DIR.'/'.$path, $file['contents']);
        }

        // O manifesto por último: pacote sem manifesto é pacote incompleto, e é o que o
        // importador recusa.
        $this->write($root.'/'.ContentPackage::MANIFEST, $manifest);

        return ['path' => $root, 'pages' => count($pages), 'documents' => count($documents), 'media' => count($media), 'files' => count($files)];
    }

    /**
     * As imagens publicáveis, com original e derivadas. Recusa o pacote se alguma página usa
     * imagem que não iria junto — que sumiu, ou que foi marcada como foto de assistido: o
     * pacote chegaria ao outro ambiente com página apontando para o nada, e a imagem sumiria do
     * site em silêncio. Melhor falhar aqui, onde ainda dá para corrigir a página.
     *
     * @param  list<array<string, mixed>>  $pages
     * @param  array<string, array{sha256: string, size: int, contents: string}>  $files
     * @return list<array<string, mixed>>
     */
    private function exportMedia(array $pages, array &$files): array
    {
        $disk = Storage::disk('local');
        $publishable = Media::query()->where('depicts_assisted_minor', false)->orderBy('id')->get()->keyBy('uuid');

        foreach ($pages as $page) {
            preg_match_all(MediaUrl::CANONICAL_IN_HTML_PATTERN, (string) $page['content'], $matches);

            foreach (array_unique($matches[1]) as $uuid) {
                if (! $publishable->has($uuid)) {
                    throw new RuntimeException(
                        "A página {$page['slug']} usa a imagem {$uuid}, que não existe mais ou foi marcada como foto de assistido. Remova-a da página antes de exportar.",
                    );
                }
            }
        }

        $media = [];

        foreach ($publishable as $item) {
            $paths = [MediaPaths::original($item)];
            foreach ($item->widths as $width) {
                $paths[] = MediaPaths::derivative($item, $width);
            }

            $entryFiles = [];
            foreach ($paths as $path) {
                if (! $disk->exists($path)) {
                    throw new RuntimeException("Arquivo da imagem {$item->uuid} não existe no disco: {$path}");
                }

                $contents = (string) $disk->get($path);
                $sha256 = hash('sha256', $contents);
                $files[$path] = ['sha256' => $sha256, 'size' => strlen($contents), 'contents' => $contents];
                $entryFiles[] = ['path' => $path, 'sha256' => $sha256];
            }

            $media[] = [
                'uuid' => $item->uuid,
                'alt' => $item->alt,
                'caption' => $item->caption,
                'version' => $item->version,
                'mime' => $item->mime,
                'extension' => $item->extension,
                'size' => $item->size,
                'width' => $item->width,
                'height' => $item->height,
                'widths' => $item->widths,
                'sha256' => $item->sha256,
                'files' => $entryFiles,
                'created_at' => $item->created_at?->toIso8601String(),
                'updated_at' => $item->updated_at?->toIso8601String(),
            ];
        }

        return $media;
    }

    /**
     * @param  array<mixed>  $data
     */
    private function json(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
    }

    private function write(string $path, string $contents): void
    {
        $dir = dirname($path);

        if (! is_dir($dir) && ! mkdir($dir, 0750, true) && ! is_dir($dir)) {
            throw new RuntimeException("Não consegui criar {$dir}");
        }

        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException("Não consegui gravar {$path}");
        }
    }
}
