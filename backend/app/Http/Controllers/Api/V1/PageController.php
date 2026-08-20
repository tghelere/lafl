<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Content\DeletePage;
use App\Actions\Content\SavePage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Content\StorePageRequest;
use App\Http\Requests\Content\UpdatePageRequest;
use App\Http\Resources\PageResource;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

final class PageController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Page::class);

        $perPage = min($request->integer('per_page', 15), 100);

        $pages = Page::query()->latest('updated_at')->paginate($perPage);

        return PageResource::collection($pages);
    }

    public function store(StorePageRequest $request, SavePage $action): PageResource
    {
        $page = $action->handle($request->toDto());

        return new PageResource($page);
    }

    public function show(Page $page): PageResource
    {
        Gate::authorize('view', $page);

        return new PageResource($page);
    }

    public function update(UpdatePageRequest $request, Page $page, SavePage $action): PageResource
    {
        $page = $action->handle($request->toDto(), $page);

        return new PageResource($page);
    }

    public function destroy(Page $page, DeletePage $action): Response
    {
        Gate::authorize('delete', $page);

        $action->handle($page);

        return response()->noContent();
    }
}
