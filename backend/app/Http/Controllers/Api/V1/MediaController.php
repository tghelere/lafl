<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Media\DeleteMedia;
use App\Actions\Media\FindMediaUsages;
use App\Actions\Media\ReplaceMediaFile;
use App\Actions\Media\StoreMedia;
use App\Actions\Media\UpdateMediaDetails;
use App\Http\Controllers\Controller;
use App\Http\Requests\Media\ReplaceMediaFileRequest;
use App\Http\Requests\Media\StoreMediaRequest;
use App\Http\Requests\Media\UpdateMediaRequest;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Support\Media\MediaFileResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class MediaController extends Controller
{
    /**
     * Mais recentes primeiro; busca por texto alternativo ou legenda (ILIKE no Postgres, pelo
     * mesmo motivo de PageController::index). `publishable=1` restringe ao que pode ir para
     * o site — é o filtro do seletor de imagem do editor.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Media::class);

        $perPage = min($request->integer('per_page', 24), 60);
        $search = trim((string) $request->string('search'));

        $media = Media::query()
            ->when($search !== '', fn ($query) => $query->where(
                fn ($inner) => $inner->whereLike('alt', "%{$search}%")->orWhereLike('caption', "%{$search}%"),
            ))
            ->when($request->boolean('publishable'), fn ($query) => $query->where('depicts_assisted_minor', false))
            ->latest('created_at')
            ->latest('id')
            ->paginate($perPage);

        return MediaResource::collection($media);
    }

    public function store(StoreMediaRequest $request, StoreMedia $action): MediaResource
    {
        $media = $action->handle($request->uploadedPath(), $request->toDetailsDto(), $request->user());

        return (new MediaResource($media))->withUsages([]);
    }

    public function show(Media $media, FindMediaUsages $usages): MediaResource
    {
        Gate::authorize('view', $media);

        return (new MediaResource($media))->withUsages($usages->handle($media));
    }

    public function update(UpdateMediaRequest $request, Media $media, UpdateMediaDetails $action, FindMediaUsages $usages): MediaResource
    {
        $media = $action->handle($media, $request->toDetailsDto(), $request->user());

        return (new MediaResource($media))->withUsages($usages->handle($media));
    }

    public function replaceFile(ReplaceMediaFileRequest $request, Media $media, ReplaceMediaFile $action, FindMediaUsages $usages): MediaResource
    {
        $media = $action->handle($media, $request->uploadedPath(), $request->user());

        return (new MediaResource($media))->withUsages($usages->handle($media));
    }

    public function destroy(Request $request, Media $media, DeleteMedia $action): Response
    {
        Gate::authorize('delete', $media);

        $action->handle($media, $request->user());

        return response()->noContent();
    }

    /**
     * A imagem para o PAINEL — autenticada, e servida inclusive quando impublicável (a pessoa
     * precisa ver o que está marcando ou excluindo). `private`: nunca vai para cache
     * compartilhado.
     */
    public function file(Request $request, Media $media, string $variant): SymfonyResponse
    {
        Gate::authorize('view', $media);

        return MediaFileResponse::make($request, $media, $variant, public: false);
    }
}
