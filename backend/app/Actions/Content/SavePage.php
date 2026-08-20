<?php

declare(strict_types=1);

namespace App\Actions\Content;

use App\Actions\Content\Data\PageData;
use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\PageSlugHistory;
use App\Support\Cache\PublicPageCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SavePage
{
    /**
     * Cria ou atualiza uma página. Slug muda → histórico gravado, cache público invalidado.
     */
    public function handle(PageData $data, ?Page $page = null): Page
    {
        $this->assertValidSlugDepth($data->slug);

        return DB::transaction(function () use ($data, $page): Page {
            $page ??= new Page;
            $previousSlug = $page->exists ? $page->slug : null;
            $wasPublished = $page->exists && $page->status === PageStatus::Published;

            $page->slug = $data->slug;
            $page->title = $data->title;
            $page->content = $data->content;
            $page->meta_title = $data->metaTitle;
            $page->meta_description = $data->metaDescription;
            $page->status = $data->status;

            if ($data->status === PageStatus::Published && ! $wasPublished && $page->published_at === null) {
                $page->published_at = now();
            }

            $page->save();

            if ($previousSlug !== null && $previousSlug !== $page->slug) {
                PageSlugHistory::query()->create([
                    'page_id' => $page->id,
                    'slug' => $previousSlug,
                ]);

                Cache::forget(PublicPageCache::key($previousSlug));
            }

            Cache::forget(PublicPageCache::key($page->slug));

            return $page;
        });
    }

    /**
     * No máximo dois níveis; havendo dois, o primeiro precisa ser o slug de uma página já
     * existente — é o único risco real de manter `pages` plana em vez de hierárquica (ver
     * docs/decisoes, decisão de formato de slug tomada na sessão de "Quem somos").
     */
    private function assertValidSlugDepth(string $slug): void
    {
        $segments = explode('/', $slug);

        if (count($segments) > 2) {
            throw ValidationException::withMessages([
                'slug' => ['O slug pode ter no máximo dois níveis, como "quem-somos/nossa-historia".'],
            ]);
        }

        if (count($segments) === 2) {
            $parentSlug = $segments[0];

            if (! Page::query()->where('slug', $parentSlug)->exists()) {
                throw ValidationException::withMessages([
                    'slug' => ["A página-mãe \"{$parentSlug}\" não existe. Crie-a antes de usar um slug de dois níveis."],
                ]);
            }
        }
    }
}
