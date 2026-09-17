<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PageStatus;
use App\Models\Page;
use App\Support\Content\InitialPages;
use Illuminate\Database\Seeder;

class ContentPagesSeeder extends Seeder
{
    /**
     * Conteúdo institucional para o site público em desenvolvimento — só roda em
     * local/testing/e2e. O texto em si mora em App\Support\Content\InitialPages (fonte
     * única, ver o comentário no topo daquela classe); aqui fica só a política deste
     * seeder, que é diferente da do comando de produção:
     *
     * - aqui, `updateOrCreate`: reseedar SOBRESCREVE a página existente, porque num banco de
     *   desenvolvimento o que vale é o texto do repositório;
     * - em staging/production, `php artisan conteudo:importar-inicial` só CRIA o que falta e
     *   nunca toca em página existente, porque lá o texto é do painel (ver
     *   App\Actions\Content\ImportInitialPages).
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing', 'e2e'])) {
            return;
        }

        // quem-somos/o-lar-hoje saiu do conteúdo inicial por completo (ver o comentário em
        // InitialPages sobre o processo de 2022) — remoção explícita porque updateOrCreate()
        // só cria/atualiza o que está em InitialPages::all(), nunca apaga o que ficou de
        // fora. Sem esta linha, um banco de desenvolvimento que já tinha essa página (mesmo
        // em Draft) manteria o texto. Apagar só é aceitável aqui, em banco de
        // desenvolvimento — o comando de importação nunca apaga nada.
        Page::query()->withTrashed()->where('slug', 'quem-somos/o-lar-hoje')->forceDelete();

        foreach (InitialPages::all() as $data) {
            $status = $data['status'] ?? PageStatus::Published;

            Page::query()->updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'title' => $data['title'],
                    'content' => $data['content'],
                    'meta_title' => "{$data['title']} — Lar Anália Franco",
                    'meta_description' => $data['meta_description'],
                    'status' => $status,
                    'published_at' => $status === PageStatus::Published ? now() : null,
                ],
            );
        }
    }
}
