<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Content\ImportContentPackage;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Carrega um pacote gerado por `conteudo:exportar` (ver docs/deploy.md §10).
 *
 * Sem `--substituir`, só cria o que falta e nunca toca no que já existe. É o flag que
 * autoriza sobrescrever, e por isso ele pede confirmação interativa além de ser explícito.
 */
final class ImportContent extends Command
{
    protected $signature = 'conteudo:importar
        {pacote : Pasta do pacote (a que contém manifest.json)}
        {--substituir : Sobrescreve páginas e documentos que já existem com o mesmo slug}
        {--simular : Mostra o que seria feito, sem escrever nada}
        {--force : Não pede a confirmação do --substituir (para uso em script)}';

    protected $description = 'Importa um pacote de conteúdo. Nunca apaga nem sobrescreve o que existe sem --substituir.';

    public function handle(ImportContentPackage $import): int
    {
        $replace = (bool) $this->option('substituir');
        $dryRun = (bool) $this->option('simular');

        if ($replace && ! $dryRun && ! $this->option('force')
            && ! $this->components->confirm('--substituir vai SOBRESCREVER as páginas e os documentos que já existem com o mesmo slug. Continuar?', false)) {
            $this->components->warn('Cancelado. Nada foi alterado.');

            return self::FAILURE;
        }

        try {
            $result = $import->handle((string) $this->argument('pacote'), $replace, $dryRun);
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());
            $this->line('  Nada foi escrito.');

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->components->warn('Simulação: nada foi escrito.');
        }

        foreach (['media' => 'Imagem', 'pages' => 'Página', 'documents' => 'Documento'] as $key => $label) {
            foreach ($result[$key]['created'] as $slug) {
                $this->components->twoColumnDetail("{$label} {$slug}", '<fg=green>criado</>');
            }

            foreach ($result[$key]['replaced'] as $slug) {
                $this->components->twoColumnDetail("{$label} {$slug}", '<fg=yellow>substituído</>');
            }

            foreach ($result[$key]['skipped'] as $slug) {
                $this->components->twoColumnDetail("{$label} {$slug}", '<fg=gray>já existia — mantido</>');
            }
        }

        $skipped = count($result['pages']['skipped']) + count($result['documents']['skipped']) + count($result['media']['skipped']);

        $this->newLine();
        $this->components->info(sprintf(
            'Páginas: %d criada(s), %d substituída(s), %d mantida(s). Documentos: %d, %d, %d. Imagens: %d, %d, %d.',
            count($result['pages']['created']),
            count($result['pages']['replaced']),
            count($result['pages']['skipped']),
            count($result['documents']['created']),
            count($result['documents']['replaced']),
            count($result['documents']['skipped']),
            count($result['media']['created']),
            count($result['media']['replaced']),
            count($result['media']['skipped']),
        ));

        if ($skipped > 0) {
            $this->components->warn('O que já existia foi mantido. Para sobrescrever, rode de novo com --substituir.');
        }

        return self::SUCCESS;
    }
}
