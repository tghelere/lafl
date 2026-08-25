<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Transparency\DeleteTransparencyDocument;
use App\Actions\Transparency\SaveTransparencyDocument;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transparency\StoreTransparencyDocumentRequest;
use App\Http\Requests\Transparency\UpdateTransparencyDocumentRequest;
use App\Http\Resources\TransparencyDocumentResource;
use App\Models\TransparencyDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

final class TransparencyDocumentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', TransparencyDocument::class);

        $perPage = min($request->integer('per_page', 15), 100);

        $documents = TransparencyDocument::query()->latest('updated_at')->paginate($perPage);

        return TransparencyDocumentResource::collection($documents);
    }

    public function store(StoreTransparencyDocumentRequest $request, SaveTransparencyDocument $action): TransparencyDocumentResource
    {
        $document = $action->handle($request->toDto());

        return new TransparencyDocumentResource($document);
    }

    public function show(TransparencyDocument $transparencyDocument): TransparencyDocumentResource
    {
        Gate::authorize('view', $transparencyDocument);

        return new TransparencyDocumentResource($transparencyDocument);
    }

    public function update(UpdateTransparencyDocumentRequest $request, TransparencyDocument $transparencyDocument, SaveTransparencyDocument $action): TransparencyDocumentResource
    {
        $document = $action->handle($request->toDto(), $transparencyDocument);

        return new TransparencyDocumentResource($document);
    }

    public function destroy(TransparencyDocument $transparencyDocument, DeleteTransparencyDocument $action): Response
    {
        Gate::authorize('delete', $transparencyDocument);

        $action->handle($transparencyDocument);

        return response()->noContent();
    }
}
