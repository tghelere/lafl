<?php

declare(strict_types=1);

namespace App\Actions\Transparency\Import;

use App\Actions\Transparency\Data\TransparencyDocumentData;
use App\Actions\Transparency\SaveTransparencyDocument;
use App\Enums\TransparencyDocumentType;
use App\Http\Requests\Transparency\StoreTransparencyDocumentRequest;
use App\Models\TransparencyDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Importa em lote documentos de transparência de uma pasta com PDFs e um `manifesto.json`.
 *
 * Só ADICIONA: nunca atualiza nem apaga documento existente, e não abre, converte nem altera
 * o PDF — o arquivo é entregue como está à mesma Action do painel (SaveTransparencyDocument),
 * com as mesmas regras do StoreTransparencyDocumentRequest. Conteúdo idêntico entre dois
 * arquivos não é motivo de recusa; a identidade de um item é título + ano.
 */
final class ImportTransparencyBatch
{
    /**
     * Tipo descritivo do manifesto → enum existente. Os sem correspondente exato usam o mais
     * próximo (ver docs/importar-transparencia.md).
     *
     * @var array<string, TransparencyDocumentType>
     */
    public const TYPE_MAP = [
        'ata' => TransparencyDocumentType::Minutes,
        'edital' => TransparencyDocumentType::Notice,
        'termo_de_colaboracao' => TransparencyDocumentType::AgreementAccounting,
        'termo_aditivo' => TransparencyDocumentType::AgreementAccounting,
        'prestacao_de_contas' => TransparencyDocumentType::AgreementAccounting,
        'quadro_de_pessoal' => TransparencyDocumentType::AgreementAccounting,
    ];

    public const STATUS_WOULD_CREATE = 'seria criado';

    public const STATUS_CREATED = 'criado';

    public const STATUS_EXISTED = 'já existia';

    public function __construct(private readonly SaveTransparencyDocument $save) {}

    /**
     * @return list<array{title: string, type: TransparencyDocumentType, date: string, status: string}>
     *
     * @throws ImportTransparencyBatchException
     */
    public function handle(string $folder, bool $execute): array
    {
        $items = $this->readManifest($folder);
        $this->validate($items);

        foreach ($items as $i => $item) {
            $items[$i]['exists'] = TransparencyDocument::withTrashed()
                ->where('title', $item['title'])
                ->where('year', $item['year'])
                ->exists();
        }

        if (! $execute) {
            return $this->rows($items, fn (array $item): string => $item['exists'] ? self::STATUS_EXISTED : self::STATUS_WOULD_CREATE);
        }

        $copied = [];

        try {
            DB::transaction(function () use ($items, &$copied): void {
                foreach ($items as $item) {
                    if ($item['exists']) {
                        continue;
                    }

                    $document = $this->save->handle(new TransparencyDocumentData(
                        title: $item['title'],
                        year: $item['year'],
                        type: $item['type'],
                        file: new UploadedFile($item['path'], $item['file'], 'application/pdf', null, true),
                        publish: $item['publish'],
                    ));

                    $copied[] = $document->file_path;
                }
            });
        } catch (Throwable $e) {
            // Só o que ESTA execução copiou: o rollback desfez as linhas, não os arquivos.
            Storage::disk('local')->delete($copied);

            throw new ImportTransparencyBatchException(['Falha ao gravar o lote, nada foi mantido: '.$e->getMessage()]);
        }

        return $this->rows($items, fn (array $item): string => $item['exists'] ? self::STATUS_EXISTED : self::STATUS_CREATED);
    }

    /**
     * @param  list<array{title: string, type: TransparencyDocumentType, date: string, exists: bool}>  $items
     * @param  callable(array<string, mixed>): string  $status
     * @return list<array{title: string, type: TransparencyDocumentType, date: string, status: string}>
     */
    private function rows(array $items, callable $status): array
    {
        return array_map(fn (array $item): array => [
            'title' => $item['title'],
            'type' => $item['type'],
            'date' => $item['date'],
            'status' => $status($item),
        ], $items);
    }

    /**
     * Conferência técnica: manifesto legível, total certo, tipo conhecido e todo arquivo
     * presente. Reúne TODOS os problemas antes de recusar.
     *
     * @return list<array{file: string, path: string, title: string, year: int, type: TransparencyDocumentType, date: string, publish: bool}>
     */
    private function readManifest(string $folder): array
    {
        $manifestPath = rtrim($folder, '/').'/manifesto.json';

        if (! is_file($manifestPath)) {
            throw new ImportTransparencyBatchException(["Manifesto não encontrado: {$manifestPath}"]);
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);

        if (! is_array($manifest) || ! is_array($manifest['documentos'] ?? null)) {
            throw new ImportTransparencyBatchException(['manifesto.json inválido: falta a lista "documentos".']);
        }

        $problems = [];
        $items = [];
        $seen = [];

        if (($manifest['total'] ?? null) !== count($manifest['documentos'])) {
            $problems[] = sprintf('O total do manifesto (%s) não confere com os documentos listados (%d).', json_encode($manifest['total'] ?? null), count($manifest['documentos']));
        }

        foreach ($manifest['documentos'] as $n => $entry) {
            $position = '#'.($n + 1);
            $file = is_array($entry) ? ($entry['arquivo'] ?? null) : null;
            $title = is_array($entry) ? ($entry['titulo'] ?? null) : null;
            $type = is_array($entry) ? (self::TYPE_MAP[$entry['tipo'] ?? ''] ?? null) : null;

            if (! is_string($file) || $file === '' || basename($file) !== $file) {
                $problems[] = "Item {$position}: campo \"arquivo\" ausente ou inválido.";

                continue;
            }

            if (! is_string($title) || trim($title) === '') {
                $problems[] = "Item {$position} ({$file}): falta o título.";

                continue;
            }

            if ($type === null) {
                $problems[] = "Item {$position} ({$file}): tipo desconhecido ".json_encode($entry['tipo'] ?? null).'.';
            }

            $path = rtrim($folder, '/').'/'.$file;

            if (! is_file($path)) {
                $problems[] = "Arquivo ausente: {$file}";
            }

            $key = $title.'|'.($entry['ano'] ?? '');

            if (isset($seen[$key])) {
                $problems[] = "Item {$position} ({$file}): título e ano repetidos no manifesto.";
            }
            $seen[$key] = true;

            if ($type !== null) {
                $items[] = [
                    'file' => $file,
                    'path' => $path,
                    'title' => $title,
                    'year' => is_numeric($entry['ano'] ?? null) ? (int) $entry['ano'] : 0,
                    'type' => $type,
                    'date' => (string) ($entry['data_documento'] ?? ''),
                    'publish' => (bool) ($entry['publicar'] ?? false),
                ];
            }
        }

        if ($problems !== []) {
            throw new ImportTransparencyBatchException($problems);
        }

        return $items;
    }

    /**
     * As mesmas regras do painel (StoreTransparencyDocumentRequest::rules()), item a item.
     *
     * @param  list<array{file: string, path: string, title: string, year: int, type: TransparencyDocumentType, publish: bool}>  $items
     */
    private function validate(array $items): void
    {
        $rules = (new StoreTransparencyDocumentRequest)->rules();
        $messages = (new StoreTransparencyDocumentRequest)->messages();
        $problems = [];

        foreach ($items as $item) {
            $validator = Validator::make([
                'title' => $item['title'],
                'year' => $item['year'],
                'type' => $item['type']->value,
                'file' => new UploadedFile($item['path'], $item['file'], null, null, true),
                'published' => $item['publish'],
            ], $rules, $messages);

            foreach ($validator->errors()->all() as $message) {
                $problems[] = "{$item['file']}: {$message}";
            }
        }

        if ($problems !== []) {
            throw new ImportTransparencyBatchException($problems);
        }
    }
}
