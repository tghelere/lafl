<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Media\ImportInitialPhotos as ImportInitialPhotosAction;
use Illuminate\Console\Command;

/**
 * Leva para a biblioteca as fotos que o site servia como arquivos fixos, e as põe na capa ou na
 * galeria das páginas (ver App\Support\Media\InitialPhotos). Roda depois de
 * `conteudo:importar-inicial`, porque as fotos precisam das páginas.
 *
 * Idempotente e não destrutivo: rodar de novo só completa o que falta e nunca mexe no que a
 * equipe mudou pelo painel. Produção não roda este comando: lá as fotos chegam pelo pacote de
 * conteúdo, com as páginas (docs/deploy.md, §10.1).
 */
final class ImportInitialPhotos extends Command
{
    protected $signature = 'midia:importar-fotos-iniciais';

    protected $description = 'Carrega na biblioteca as fotos iniciais do site e as põe nas páginas (nunca duplica nem sobrescreve).';

    public function handle(ImportInitialPhotosAction $import): int
    {
        $result = $import->handle();

        $this->newLine();

        foreach ($result['created'] as $key) {
            $this->components->twoColumnDetail($key, '<fg=green>importada</>');
        }

        foreach ($result['skipped'] as $key) {
            $this->components->twoColumnDetail($key, '<fg=gray>já estava na biblioteca</>');
        }

        foreach ($result['missing_page'] as $key => $slug) {
            $this->components->twoColumnDetail($key, "<fg=yellow>não importada — a página /{$slug} não existe</>");
        }

        foreach ($result['cover_kept'] as $key => $slug) {
            $this->components->twoColumnDetail($key, "<fg=yellow>capa de /{$slug} já ocupada — mantida</>");
        }

        $this->newLine();
        $this->components->info(sprintf(
            '%d importada(s), %d já existente(s), %d sem página.',
            count($result['created']),
            count($result['skipped']),
            count($result['missing_page']),
        ));

        if ($result['missing_page'] !== []) {
            $this->components->warn('Rode `php artisan conteudo:importar-inicial` e repita este comando.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
