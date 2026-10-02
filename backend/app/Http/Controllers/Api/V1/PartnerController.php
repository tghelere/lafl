<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Partners\DeletePartner;
use App\Actions\Partners\SavePartner;
use App\Http\Controllers\Controller;
use App\Http\Requests\Partners\StorePartnerRequest;
use App\Http\Requests\Partners\UpdatePartnerRequest;
use App\Http\Resources\PartnerResource;
use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

final class PartnerController extends Controller
{
    /**
     * Todos os parceiros, ativos e inativos, na ordem da página pública.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Partner::class);

        $partners = Partner::query()
            ->with('media')
            ->orderBy('position')
            ->orderBy('id')
            ->paginate(min($request->integer('per_page', 20), 100));

        return PartnerResource::collection($partners);
    }

    public function store(StorePartnerRequest $request, SavePartner $action): PartnerResource
    {
        return new PartnerResource($action->handle($request->toDto(), null, $request->user()));
    }

    public function show(Partner $partner): PartnerResource
    {
        Gate::authorize('view', $partner);

        return new PartnerResource($partner->load('media'));
    }

    public function update(UpdatePartnerRequest $request, Partner $partner, SavePartner $action): PartnerResource
    {
        return new PartnerResource($action->handle($request->toDto(), $partner, $request->user()));
    }

    public function destroy(Partner $partner, DeletePartner $action): Response
    {
        Gate::authorize('delete', $partner);

        $action->handle($partner);

        return response()->noContent();
    }
}
