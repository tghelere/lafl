<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Media\ImportPageMediaPackage;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Carrega de um pacote de `conteudo:exportar` só as imagens e a capa e a galeria das páginas,
 * sem tocar no texto (ver App\Actions\Media\ImportPageMediaPackage e docs/deploy.md §10.2).
 */
final class ImportPageMedia extends Command
{
    protected $signature = 'midia:importar
        {pacote : Pasta do pacote (a que contém manifest.json)}
        {--simular : Mostra o que seria feito, sem escrever nada}
        {--verificar : Só confere se o ambiente já tem as imagens, os arquivos e os vínculos do pacote}';

    protected $description = 'Importa de um pacote de conteúdo só as imagens e a capa e a galeria das páginas (casadas pelo slug). Nunca sobrescreve.';

    public function handle(ImportPageMediaPackage $import): int
    {
        $dryRun = (bool) $this->option('simular');

        if ($this->option('verificar')) {
            return $this->verify($import);
        }

        try {
            $result = $import->handle((string) $this->argument('pacote'), $dryRun);
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());
            $this->line('  Nada foi escrito.');

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->components->warn('Simulação: nada foi escrito.');
        }

        foreach ($result['media']['created'] as $label) {
            $this->components->twoColumnDetail("Imagem {$label}", '<fg=green>criada</>');
        }

        foreach ($result['media']['skipped'] as $label) {
            $this->components->twoColumnDetail("Imagem {$label}", '<fg=gray>já existia — mantida</>');
        }

        foreach ($result['pages']['linked'] as $slug => $count) {
            $this->components->twoColumnDetail("Página {$slug}", "<fg=green>{$count} vínculo(s) de capa/galeria</>");
        }

        foreach ($result['pages']['kept'] as $slug) {
            $this->components->twoColumnDetail("Página {$slug}", '<fg=gray>já tinha capa ou galeria — mantida</>');
        }

        foreach ($result['pages']['missing'] as $slug) {
            $this->components->twoColumnDetail("Página {$slug}", '<fg=yellow>não existe aqui — vínculos não criados</>');
        }

        foreach ($result['broken_text_refs'] as $slug => $uuids) {
            $this->components->twoColumnDetail("Texto de {$slug}", '<fg=yellow>cita imagem inexistente: '.implode(', ', $uuids).'</>');
        }

        $this->newLine();
        $this->components->info(sprintf(
            'Imagens: %d criada(s), %d mantida(s). Páginas: %d com vínculos novos (%d vínculos), %d mantida(s), %d sem par.',
            count($result['media']['created']),
            count($result['media']['skipped']),
            count($result['pages']['linked']),
            array_sum($result['pages']['linked']),
            count($result['pages']['kept']),
            count($result['pages']['missing']),
        ));

        return self::SUCCESS;
    }

    private function verify(ImportPageMediaPackage $import): int
    {
        try {
            $problems = $import->verify((string) $this->argument('pacote'));
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($problems as $problem) {
            $this->components->error($problem);
        }

        if ($problems !== []) {
            return self::FAILURE;
        }

        $this->components->info('Verificação ok: imagens, arquivos e vínculos do pacote estão todos aqui.');

        return self::SUCCESS;
    }
}
