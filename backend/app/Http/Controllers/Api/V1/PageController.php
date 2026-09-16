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
    /**
     * Busca por título (`search`) — sem filtro, devolve todas. Busca só por título, não por
     * conteúdo: a tela de páginas é uma lista curta e fechada (as seções do site), onde
     * procurar pelo nome da página é o caso real; varrer o corpo do texto exigiria índice
     * de texto completo para não ficar lento à toa.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Page::class);

        $perPage = min($request->integer('per_page', 15), 100);
        $search = trim((string) $request->string('search'));

        $query = Page::query()->latest('updated_at');

        if ($search !== '') {
            // whereLike() em vez de where(..., 'like', ...): no PostgreSQL o LIKE é sensível
            // a maiúscula, então "bazar" não acharia "Bazar Beneficente". whereLike() resolve
            // pelo driver (ILIKE no Postgres), em vez de acoplar o Controller ao banco.
            $query->whereLike('title', "%{$search}%");
        }

        return PageResource::collection($query->paginate($perPage));
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
