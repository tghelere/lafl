<?php

declare(strict_types=1);

namespace App\Actions\Content;

use App\Models\Page;
use App\Support\Cache\PublicPageCache;
use Illuminate\Support\Facades\Cache;

final class DeletePage
{
    public function handle(Page $page): void
    {
        $slug = $page->slug;

        $page->delete();

        Cache::forget(PublicPageCache::key($slug));
    }
}
