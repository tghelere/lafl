<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Transparency\Import\ImportTransparencyBatch;
use App\Actions\Transparency\Import\ImportTransparencyBatchException;
use Illuminate\Console\Command;

/**
 * Importa em lote documentos de transparência de uma pasta com PDFs e manifesto.json (ver
 * App\Actions\Transparency\Import\ImportTransparencyBatch e docs/importar-transparencia.md).
 */
final class ImportTransparencyDocuments extends Command
{
    protected $signature = 'transparencia:importar
        {pasta : Pasta com os PDFs e o manifesto.json}
        {--executar : Grava de verdade. Sem esta opção é só simulação}';

    protected $description = 'Importa documentos de transparência de uma pasta com manifesto.json. Só adiciona; nunca altera nem apaga o que já existe.';

    public function handle(ImportTransparencyBatch $import): int
    {
        $execute = (bool) $this->option('executar');

        try {
            $rows = $import->handle((string) $this->argument('pasta'), $execute);
        } catch (ImportTransparencyBatchException $e) {
            foreach ($e->problems as $problem) {
                $this->components->error($problem);
            }
            $this->line('  Nada foi gravado.');

            return self::FAILURE;
        }

        if (! $execute) {
            $this->components->warn('Simulação: nada foi gravado. Use --executar para importar.');
        }

        $this->table(
            ['Título', 'Tipo', 'Data', 'Status'],
            array_map(fn (array $row): array => [$row['title'], $row['type']->label(), $row['date'], $row['status']], $rows),
        );

        return self::SUCCESS;
    }
}
