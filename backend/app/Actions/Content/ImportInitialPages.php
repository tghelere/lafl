<?php

declare(strict_types=1);

namespace App\Actions\Content;

use App\Enums\PageStatus;
use App\Models\Page;
use App\Support\Cache\PublicPageCache;
use App\Support\Content\InitialPages;
use Illuminate\Support\Facades\Cache;

/**
 * Cria no banco as páginas institucionais de App\Support\Content\InitialPages que ainda não
 * existem — e **somente** essas. Nenhuma página existente é alterada, republicada ou apagada.
 *
 * É a metade de produção do que o `ContentPagesSeeder` faz em desenvolvimento. A diferença
 * não é de conveniência: fora do ambiente local o texto das páginas é editado pelo painel, e
 * um `updateOrCreate` aqui apagaria a edição da instituição toda vez que alguém rodasse o
 * comando depois de um deploy. Por isso a chave é "existe uma linha com este slug?" e a
 * resposta "sim" sempre significa pular.
 *
 * Página na lixeira (soft delete) conta como existente: o índice de `pages.slug` é UNIQUE
 * sem filtro, então recriar o slug estouraria o índice — e "ressuscitar" o que alguém
 * excluiu pelo painel seria pior ainda que sobrescrever. Essas aparecem no resumo em
 * categoria própria, para quem rodar o comando entender por que a página não veio.
 */
final class ImportInitialPages
{
    /**
     * @return array{created: list<string>, skipped: list<string>, trashed: list<string>}
     */
    public function handle(): array
    {
        $created = [];
        $skipped = [];
        $trashed = [];

        foreach (InitialPages::all() as $data) {
            $existing = Page::query()->withTrashed()->where('slug', $data['slug'])->first();

            if ($existing !== null) {
                if ($existing->trashed()) {
                    $trashed[] = $data['slug'];
                } else {
                    $skipped[] = $data['slug'];
                }

                continue;
            }

            $status = $data['status'] ?? PageStatus::Published;

            Page::query()->create([
                'slug' => $data['slug'],
                'title' => $data['title'],
                'content' => $data['content'],
                'meta_title' => "{$data['title']} — Lar Anália Franco",
                'meta_description' => $data['meta_description'],
                'status' => $status,
                'published_at' => $status === PageStatus::Published ? now() : null,
            ]);

            // `ResolvePublicPageBySlug` cacheia por 10 minutos inclusive o "não existe"
            // (null). Sem este esquecimento, uma página recém-criada continuaria devolvendo
            // 404 no site por até 10 minutos depois da importação — o mesmo sintoma da
            // armadilha já registrada no CLAUDE.md, com outra causa.
            Cache::forget(PublicPageCache::key($data['slug']));

            $created[] = $data['slug'];
        }

        return ['created' => $created, 'skipped' => $skipped, 'trashed' => $trashed];
    }
}
