<?php

declare(strict_types=1);

namespace App\Actions\Content;

use App\Models\Page;
use App\Models\TransparencyDocument;
use App\Support\Content\ContentPackage;
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
     * @return array{path: string, pages: int, documents: int, files: int}
     */
    public function handle(string $destinationDir): array
    {
        $root = rtrim($destinationDir, '/').'/conteudo-'.now()->format('Ymd-His');

        if (file_exists($root)) {
            throw new RuntimeException("O destino já existe: {$root}");
        }

        $disk = Storage::disk('local');

        $pages = Page::query()
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
            ->values()
            ->all();

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

        $pagesJson = $this->json($pages);
        $documentsJson = $this->json($documents);

        $manifest = $this->json([
            'format_version' => ContentPackage::FORMAT_VERSION,
            'generated_at' => now()->toIso8601String(),
            'source_environment' => app()->environment(),
            'counts' => ['pages' => count($pages), 'documents' => count($documents), 'files' => count($files)],
            'checksums' => [
                ContentPackage::PAGES => hash('sha256', $pagesJson),
                ContentPackage::DOCUMENTS => hash('sha256', $documentsJson),
            ],
        ]);

        $this->write($root.'/'.ContentPackage::PAGES, $pagesJson);
        $this->write($root.'/'.ContentPackage::DOCUMENTS, $documentsJson);

        foreach ($files as $path => $file) {
            $this->write($root.'/'.ContentPackage::FILES_DIR.'/'.$path, $file['contents']);
        }

        // O manifesto por último: pacote sem manifesto é pacote incompleto, e é o que o
        // importador recusa.
        $this->write($root.'/'.ContentPackage::MANIFEST, $manifest);

        return ['path' => $root, 'pages' => count($pages), 'documents' => count($documents), 'files' => count($files)];
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
