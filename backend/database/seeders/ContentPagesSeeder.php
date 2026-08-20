<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PageStatus;
use App\Models\Page;
use Illuminate\Database\Seeder;

class ContentPagesSeeder extends Seeder
{
    /**
     * Conteúdo placeholder para "Quem somos" e suas quatro subpáginas — só roda em
     * local/testing, nunca dado real da instituição (ver CLAUDE.md, regra 10). Fatos sobre
     * a instituição em docs/contexto.md marcados [CONFIRMAR] não entram aqui — o texto é
     * genérico de propósito, para o front ter o que renderizar antes de haver conteúdo
     * validado.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        foreach ($this->pages() as $data) {
            Page::query()->updateOrCreate(
                ['slug' => $data['slug']],
                [...$data, 'status' => PageStatus::Published, 'published_at' => now()],
            );
        }
    }

    /**
     * @return list<array{slug: string, title: string, content: string, meta_title: string, meta_description: string}>
     */
    private function pages(): array
    {
        $placeholder = 'Conteúdo de exemplo para desenvolvimento — texto placeholder, ainda '
            .'não validado pela instituição. Não publicar como conteúdo real (ver '
            .'docs/contexto.md).';

        $titles = [
            'quem-somos' => 'Quem Somos',
            'quem-somos/nossa-historia' => 'Nossa História',
            'quem-somos/missao-visao-valores' => 'Missão, Visão e Valores',
            'quem-somos/governanca' => 'Governança',
            'quem-somos/o-lar-hoje' => 'O Lar Hoje',
        ];

        $pages = [];

        foreach ($titles as $slug => $title) {
            $pages[] = [
                'slug' => $slug,
                'title' => $title,
                'content' => $placeholder,
                'meta_title' => "{$title} — Lar Anália Franco",
                'meta_description' => $placeholder,
            ];
        }

        return $pages;
    }
}
