<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Content\ImportInitialPages;
use Illuminate\Console\Command;

/**
 * Primeira carga de conteúdo de um ambiente novo (ver docs/deploy.md). Existe porque o
 * `ContentPagesSeeder` se recusa a rodar fora de local/testing/e2e, de propósito — um deploy
 * subiria com o site inteiro em 404.
 *
 * Idempotente e não destrutivo: rodar duas vezes seguidas não muda nada na segunda, e
 * nenhuma página existente é tocada. Depois do primeiro deploy o texto é da instituição, que
 * o edita pelo painel; este comando nunca pisa nisso.
 */
final class ImportInitialContent extends Command
{
    protected $signature = 'conteudo:importar-inicial';

    protected $description = 'Cria as páginas institucionais que ainda não existem (nunca sobrescreve conteúdo existente).';

    public function handle(ImportInitialPages $import): int
    {
        $result = $import->handle();

        $this->newLine();

        foreach ($result['created'] as $slug) {
            $this->components->twoColumnDetail('/'.$slug, '<fg=green>criada</>');
        }

        foreach ($result['skipped'] as $slug) {
            $this->components->twoColumnDetail('/'.$slug, '<fg=gray>já existia</>');
        }

        foreach ($result['trashed'] as $slug) {
            $this->components->twoColumnDetail('/'.$slug, '<fg=yellow>na lixeira — não recriada</>');
        }

        $this->newLine();
        $this->components->info(sprintf(
            '%d criada(s), %d já existente(s), %d na lixeira.',
            count($result['created']),
            count($result['skipped']),
            count($result['trashed']),
        ));

        if ($result['trashed'] !== []) {
            $this->components->warn(
                'Página na lixeira não é recriada: o slug continua ocupado no banco. '.
                'Restaure pelo painel ou remova em definitivo antes de importar de novo.',
            );
        }

        return self::SUCCESS;
    }
}
