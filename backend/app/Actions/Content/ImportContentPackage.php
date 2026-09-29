<?php

declare(strict_types=1);

namespace App\Actions\Content;

use App\Enums\PageStatus;
use App\Enums\TransparencyDocumentType;
use App\Models\Page;
use App\Models\PageSlugHistory;
use App\Models\TransparencyDocument;
use App\Support\Cache\PublicPageCache;
use App\Support\Content\ContentPackage;
use App\Support\Html\ContentSanitizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use JsonException;
use Throwable;
use ValueError;

/**
 * Carrega um pacote de conteúdo (ver App\Support\Content\ContentPackage) neste ambiente.
 *
 * **Nunca apaga nem sobrescreve o que já existe, a não ser com `$replace = true`.** Sem o
 * flag, item que já existe (página ou documento com o mesmo slug, mesmo na lixeira) é
 * pulado e listado no resultado; só o que falta é criado. Com o flag, o item existente é
 * reescrito com o do pacote — e só ele: nada que o pacote não traga é removido, e um PDF
 * substituído continua no disco (mesma regra de `SaveTransparencyDocument`).
 *
 * Tudo é conferido ANTES da primeira escrita: versão do formato, SHA-256 de cada arquivo,
 * enum, caminho de arquivo, colisão de uuid/slug entre itens diferentes. Pacote adulterado ou
 * incompleto não chega a tocar o banco. Depois disso o banco é escrito numa transação só, e
 * os PDFs gravados por esta execução são removidos se a transação falhar.
 *
 * O HTML das páginas passa de novo pelo ContentSanitizer (regra do CLAUDE.md: todo caminho
 * de escrita passa pelo mesmo filtro). Um pacote é entrada externa como outra qualquer.
 */
final class ImportContentPackage
{
    public function __construct(private readonly ContentSanitizer $sanitizer) {}

    /**
     * @return array{
     *     pages: array{created: list<string>, replaced: list<string>, skipped: list<string>},
     *     documents: array{created: list<string>, replaced: list<string>, skipped: list<string>},
     *     dry_run: bool
     * }
     *
     * @throws InvalidArgumentException pacote inválido ou em conflito — nada foi escrito
     */
    public function handle(string $packageDir, bool $replace = false, bool $dryRun = false): array
    {
        $packageDir = rtrim($packageDir, '/');

        [$pages, $documents] = $this->readAndVerify($packageDir);

        $result = [
            'pages' => ['created' => [], 'replaced' => [], 'skipped' => []],
            'documents' => ['created' => [], 'replaced' => [], 'skipped' => []],
            'dry_run' => $dryRun,
        ];

        $disk = Storage::disk('local');

        // Fase 1: decidir, sem escrever. Cada item vira "criar", "substituir" ou "pular".
        $pagePlan = [];
        foreach ($pages as $data) {
            $existing = Page::query()->withTrashed()->where('slug', $data['slug'])->first();
            $this->assertNoUuidClash(Page::class, $data['uuid'], $existing?->id, "página {$data['slug']}");

            if ($existing === null) {
                $pagePlan[] = [$data, null];
                $result['pages']['created'][] = $data['slug'];
            } elseif ($replace) {
                $pagePlan[] = [$data, $existing];
                $result['pages']['replaced'][] = $data['slug'];
            } else {
                $result['pages']['skipped'][] = $data['slug'];
            }
        }

        $documentPlan = [];
        foreach ($documents as $data) {
            $existing = TransparencyDocument::query()->withTrashed()->where('slug', $data['slug'])->first();
            $this->assertNoUuidClash(TransparencyDocument::class, $data['uuid'], $existing?->id, "documento {$data['slug']}");

            if ($existing === null) {
                $documentPlan[] = [$data, null];
                $result['documents']['created'][] = $data['slug'];
            } elseif ($replace) {
                $documentPlan[] = [$data, $existing];
                $result['documents']['replaced'][] = $data['slug'];
            } else {
                $result['documents']['skipped'][] = $data['slug'];

                continue;
            }

            // Arquivo já no disco com conteúdo DIFERENTE do do pacote: só se sobrescreve com
            // o flag — e como o nome do PDF é aleatório, isso quase nunca acontece.
            if (! $replace && $disk->exists($data['file_path']) && hash('sha256', (string) $disk->get($data['file_path'])) !== $data['sha256']) {
                throw new InvalidArgumentException("O arquivo {$data['file_path']} já existe no disco com outro conteúdo. Use --substituir se for isso mesmo que se quer.");
            }
        }

        // Um slug antigo do histórico não pode já pertencer a OUTRA página: o índice é único
        // globalmente e o redirecionamento ficaria ambíguo.
        foreach ($pagePlan as [$data, $existing]) {
            foreach ($data['slug_history'] as $oldSlug) {
                $owner = PageSlugHistory::query()->where('slug', $oldSlug)->first();

                if ($owner !== null && $owner->page_id !== $existing?->id) {
                    throw new InvalidArgumentException("O slug antigo \"{$oldSlug}\" (página {$data['slug']}) já pertence ao histórico de outra página.");
                }
            }
        }

        if ($dryRun) {
            return $result;
        }

        // Fase 2: escrever. PDFs primeiro (inofensivos se a transação cair, e removidos se
        // for o caso), banco numa transação.
        $written = [];

        try {
            foreach ($documentPlan as [$data]) {
                $target = $data['file_path'];

                if ($disk->exists($target) && hash('sha256', (string) $disk->get($target)) === $data['sha256']) {
                    continue;
                }

                $disk->put($target, (string) file_get_contents($packageDir.'/'.ContentPackage::FILES_DIR.'/'.$target));
                $written[] = $target;
            }

            DB::transaction(function () use ($pagePlan, $documentPlan): void {
                foreach ($pagePlan as [$data, $existing]) {
                    $this->writePage($data, $existing);
                }

                foreach ($documentPlan as [$data, $existing]) {
                    $this->writeDocument($data, $existing);
                }
            });
        } catch (Throwable $e) {
            foreach ($written as $path) {
                $disk->delete($path);
            }

            throw $e;
        }

        foreach ($pagePlan as [$data]) {
            Cache::forget(PublicPageCache::key($data['slug']));
        }

        return $result;
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}
     */
    private function readAndVerify(string $dir): array
    {
        $manifest = $this->readManifest($dir.'/'.ContentPackage::MANIFEST);

        if (($manifest['format_version'] ?? null) !== ContentPackage::FORMAT_VERSION) {
            throw new InvalidArgumentException(sprintf(
                'Versão de formato não suportada: %s (esta aplicação lê a %d).',
                json_encode($manifest['format_version'] ?? null),
                ContentPackage::FORMAT_VERSION,
            ));
        }

        foreach ([ContentPackage::PAGES, ContentPackage::DOCUMENTS] as $name) {
            $raw = @file_get_contents($dir.'/'.$name);

            if ($raw === false || hash('sha256', $raw) !== ($manifest['checksums'][$name] ?? null)) {
                throw new InvalidArgumentException("{$name} ausente ou não confere com o manifesto — o pacote está incompleto ou foi alterado.");
            }
        }

        $pages = $this->readRecords($dir.'/'.ContentPackage::PAGES, ContentPackage::PAGES);
        $documents = $this->readRecords($dir.'/'.ContentPackage::DOCUMENTS, ContentPackage::DOCUMENTS);

        foreach ($pages as $i => $page) {
            foreach (['uuid', 'slug', 'title', 'content', 'status', 'slug_history'] as $key) {
                if (! array_key_exists($key, $page)) {
                    throw new InvalidArgumentException("pages.json[{$i}] sem o campo {$key}.");
                }
            }

            try {
                PageStatus::from($page['status']);
            } catch (ValueError) {
                throw new InvalidArgumentException("Status desconhecido na página {$page['slug']}: {$page['status']}");
            }
        }

        foreach ($documents as $i => $document) {
            foreach (['uuid', 'slug', 'title', 'year', 'type', 'file_path', 'file_size', 'sha256'] as $key) {
                if (! array_key_exists($key, $document)) {
                    throw new InvalidArgumentException("documents.json[{$i}] sem o campo {$key}.");
                }
            }

            try {
                TransparencyDocumentType::from($document['type']);
            } catch (ValueError) {
                throw new InvalidArgumentException("Tipo desconhecido no documento {$document['slug']}: {$document['type']}");
            }

            // Bloqueia caminho fora de transparency-documents/ (`..`, absoluto) — o valor vem
            // de um arquivo que pode ter sido montado à mão.
            if (preg_match(ContentPackage::DOCUMENT_FILE_PATTERN, $document['file_path']) !== 1) {
                throw new InvalidArgumentException("Caminho de arquivo inválido no documento {$document['slug']}: {$document['file_path']}");
            }

            $file = $dir.'/'.ContentPackage::FILES_DIR.'/'.$document['file_path'];

            if (! is_file($file) || hash_file('sha256', $file) !== $document['sha256']) {
                throw new InvalidArgumentException("O arquivo do documento {$document['slug']} está ausente ou não confere com o SHA-256 do pacote.");
            }
        }

        return [$pages, $documents];
    }

    /**
     * @return array<string, mixed>
     */
    private function readManifest(string $path): array
    {
        $decoded = $this->decode($path, ContentPackage::MANIFEST);

        $manifest = [];
        foreach ($decoded as $key => $value) {
            $manifest[(string) $key] = $value;
        }

        return $manifest;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readRecords(string $path, string $label): array
    {
        $records = [];

        foreach ($this->decode($path, $label) as $record) {
            if (! is_array($record)) {
                throw new InvalidArgumentException("{$label} não é uma lista de registros.");
            }

            $typed = [];
            foreach ($record as $key => $value) {
                $typed[(string) $key] = $value;
            }

            $records[] = $typed;
        }

        return $records;
    }

    /**
     * @return array<mixed>
     */
    private function decode(string $path, string $label): array
    {
        $raw = @file_get_contents($path);

        if ($raw === false) {
            throw new InvalidArgumentException("{$label} não encontrado em {$path} — não é um pacote de conteúdo.");
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException("{$label} não é um JSON válido.");
        }

        if (! is_array($decoded)) {
            throw new InvalidArgumentException("{$label} não é um JSON válido.");
        }

        return $decoded;
    }

    /**
     * @param  class-string<Page|TransparencyDocument>  $model
     */
    private function assertNoUuidClash(string $model, string $uuid, ?int $sameRowId, string $label): void
    {
        $holder = $model::query()->withTrashed()->where('uuid', $uuid)->first();

        if ($holder !== null && $holder->id !== $sameRowId) {
            throw new InvalidArgumentException("O uuid de {$label} já pertence a outro registro deste ambiente.");
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function writePage(array $data, ?Page $existing): void
    {
        $page = $existing ?? new Page;

        if ($page->trashed()) {
            $page->restore();
        }

        $page->forceFill([
            'uuid' => $data['uuid'],
            'slug' => $data['slug'],
            'title' => $data['title'],
            'content' => $this->sanitizer->sanitize($data['content']),
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'status' => PageStatus::from($data['status']),
            'published_at' => $this->date($data['published_at'] ?? null),
        ]);

        // Preserva as datas da origem: "idêntico" inclui quando a página foi criada e
        // publicada, e o updated_at é o que a instituição vê no painel.
        $page->timestamps = false;
        $page->created_at = $this->date($data['created_at'] ?? null) ?? now();
        $page->updated_at = $this->date($data['updated_at'] ?? null) ?? now();
        $page->save();

        foreach ($data['slug_history'] as $oldSlug) {
            PageSlugHistory::query()->firstOrCreate(['slug' => $oldSlug], ['page_id' => $page->id]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function writeDocument(array $data, ?TransparencyDocument $existing): void
    {
        $document = $existing ?? new TransparencyDocument;

        if ($document->trashed()) {
            $document->restore();
        }

        $document->forceFill([
            'uuid' => $data['uuid'],
            'slug' => $data['slug'],
            'title' => $data['title'],
            'year' => $data['year'],
            'type' => TransparencyDocumentType::from($data['type']),
            'file_path' => $data['file_path'],
            'file_size' => $data['file_size'],
            'published_at' => $this->date($data['published_at'] ?? null),
        ]);

        $document->timestamps = false;
        $document->created_at = $this->date($data['created_at'] ?? null) ?? now();
        $document->updated_at = $this->date($data['updated_at'] ?? null) ?? now();
        $document->save();
    }

    private function date(?string $value): ?Carbon
    {
        return $value === null ? null : Carbon::parse($value);
    }
}
