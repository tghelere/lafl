<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Content\ListContentMarkers;
use App\Http\Controllers\Controller;
use App\Http\Resources\ContentMarkerResource;
use App\Models\Page;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Autorizado pela mesma ability de ver páginas (App\Policies\PagePolicy::viewAny): a lista só
 * serve ao editor de páginas, e quem não pode abrir o editor não tem o que fazer com ela.
 */
final class ContentMarkerController extends Controller
{
    public function index(ListContentMarkers $action): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Page::class);

        return ContentMarkerResource::collection($action->handle());
    }
}
