<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Media\ImportInitialPhotos;
use Illuminate\Database\Seeder;

/**
 * As fotos do site na biblioteca de desenvolvimento, pelo mesmo caminho de
 * `midia:importar-fotos-iniciais`. Fica fora do E2eSeeder de propósito: a bateria de ponta a
 * ponta começa com a biblioteca vazia (e2e/tests/midia/biblioteca-vazia.spec.ts) e cria as
 * imagens de que cada teste precisa.
 *
 * Os arquivos de uma execução anterior ficam em storage/app/private/media: `migrate:fresh`
 * apaga as linhas, não o disco. Não atrapalha (cada importação cria pastas novas, por uuid),
 * mas ocupa espaço.
 */
class InitialPhotosSeeder extends Seeder
{
    public function run(ImportInitialPhotos $import): void
    {
        $result = $import->handle();

        $this->command->info(sprintf('%d foto(s) importada(s) para a biblioteca.', count($result['created'])));
    }
}
