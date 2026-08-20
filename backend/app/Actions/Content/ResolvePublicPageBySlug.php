<?php

declare(strict_types=1);

namespace App\Actions\Content;

use App\Models\Page;
use App\Models\PageSlugHistory;
use App\Support\Cache\PublicPageCache;
use Illuminate\Support\Facades\Cache;

final class ResolvePublicPageBySlug
{
    /**
     * Devolve os campos públicos da página publicada no slug pedido, um array só com
     * `redirect_to` se o slug for histórico de uma página que hoje vive em outro lugar, ou
     * null se não existe (nunca 403 — "existe mas você não pode ver" não se aplica a
     * conteúdo público).
     *
     * Retorno é sempre array (nunca objeto/Model): `config('cache.serializable_classes')`
     * vem `false` por padrão no Laravel 13 (ver config/cache.php) — unserialize() recusa
     * reconstruir qualquer objeto vindo do cache, inclusive Eloquent models e DTOs simples,
     * e devolve silenciosamente `__PHP_Incomplete_Class`. Cachear só array evita depender de
     * afrouxar essa trava de segurança só para este caso de uso.
     *
     * @return array{slug: string, title: string, content: string, meta_title: ?string, meta_description: ?string, updated_at: string}|array{redirect_to: string}|null
     */
    public function handle(string $slug): ?array
    {
        return Cache::remember(
            PublicPageCache::key($slug),
            now()->addMinutes(10),
            fn () => $this->resolve($slug),
        );
    }

    /**
     * @return array{slug: string, title: string, content: string, meta_title: ?string, meta_description: ?string, updated_at: string}|array{redirect_to: string}|null
     */
    private function resolve(string $slug): ?array
    {
        $page = Page::query()->published()->where('slug', $slug)->first();

        if ($page !== null) {
            return [
                'slug' => $page->slug,
                'title' => $page->title,
                'content' => $page->content,
                'meta_title' => $page->meta_title,
                'meta_description' => $page->meta_description,
                'updated_at' => $page->updated_at?->toIso8601String() ?? '',
            ];
        }

        $history = PageSlugHistory::query()->where('slug', $slug)->first();

        if ($history === null) {
            return null;
        }

        $target = Page::query()->published()->find($history->page_id);

        return $target !== null ? ['redirect_to' => $target->slug] : null;
    }
}
