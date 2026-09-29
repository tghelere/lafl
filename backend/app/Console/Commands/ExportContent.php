<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Content\ExportContentPackage;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Exporta páginas do CMS, documentos de transparência e os PDFs para um pacote versionado
 * (ver App\Support\Content\ContentPackage e docs/deploy.md §10). Só lê.
 */
final class ExportContent extends Command
{
    protected $signature = 'conteudo:exportar {destino : Pasta onde o pacote será criado (dentro dela nasce conteudo-AAAAMMDD-HHMMSS/)}';

    protected $description = 'Exporta o conteúdo institucional (páginas, documentos de transparência e PDFs) para um pacote.';

    public function handle(ExportContentPackage $export): int
    {
        try {
            $result = $export->handle((string) $this->argument('destino'));
        } catch (RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            '%d página(s), %d documento(s), %d arquivo(s) exportados.',
            $result['pages'],
            $result['documents'],
            $result['files'],
        ));
        $this->line('  '.$result['path']);

        return self::SUCCESS;
    }
}
