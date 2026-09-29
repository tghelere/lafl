<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Content\PageImages\ListPageImages;
use App\Actions\Content\PageImages\MoveGalleryImage;
use App\Actions\Content\PageImages\RemoveImageFromPage;
use App\Actions\Content\PageImages\SetPageCover;
use App\Actions\Content\PageImages\UploadImageToPage;
use App\Actions\Media\FindMediaUsages;
use App\Http\Controllers\Controller;
use App\Http\Requests\Content\PageImages\MoveGalleryImageRequest;
use App\Http\Requests\Content\PageImages\RemovePageImageRequest;
use App\Http\Requests\Content\PageImages\SetPageCoverRequest;
use App\Http\Requests\Content\PageImages\StorePageImageRequest;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Models\Page;
use App\Support\Content\CoverPlacements;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * "Imagens desta página" (ver docs/decisoes/0025-imagens-da-pagina.md): a mesma biblioteca,
 * vista pela porta da página. Substituir o arquivo e corrigir texto alternativo e legenda não
 * têm rota aqui: são as de App\Http\Controllers\Api\V1\MediaController, porque o arquivo e o
 * metadado são da imagem, não da página.
 *
 * Sem paginação na listagem, de propósito: é o que UMA página mostra (uma capa, uma galeria de
 * poucas fotos e as imagens do texto), não um acervo.
 */
final class PageImageController extends Controller
{
    public function index(Page $page, ListPageImages $action, FindMediaUsages $usages): JsonResponse
    {
        Gate::authorize('update', $page);

        $images = $action->handle($page);
        $present = fn (Media $media): MediaResource => (new MediaResource($media))->withUsages($usages->handle($media));

        return response()->json(['data' => [
            'cover' => $images['cover'] !== null ? $present($images['cover']) : null,
            'gallery' => array_map($present, $images['gallery']),
            'content' => array_map($present, $images['content']),
            // Onde o site mostra a capa desta página: a capa não aparece nela.
            'cover_shown_on' => CoverPlacements::for($page->slug),
        ]]);
    }

    public function store(StorePageImageRequest $request, Page $page, UploadImageToPage $action): MediaResource
    {
        $media = $action->handle($page, $request->uploadedPath(), $request->toDetailsDto(), $request->role(), $request->user());

        return new MediaResource($media);
    }

    /** Troca a capa por uma imagem que já está na biblioteca. */
    public function setCover(SetPageCoverRequest $request, Page $page, SetPageCover $action): Response
    {
        $action->handle($page, $request->media(), $request->user());

        return response()->noContent();
    }

    /** Uma posição para cima ou para baixo na galeria. Devolve a posição nova, para a tela anunciar. */
    public function move(MoveGalleryImageRequest $request, Page $page, Media $media, MoveGalleryImage $action): JsonResponse
    {
        return response()->json(['data' => $action->handle($page, $media, $request->direction(), $request->user())]);
    }

    public function destroy(RemovePageImageRequest $request, Page $page, Media $media, RemoveImageFromPage $action): Response
    {
        $action->handle($page, $media, $request->role(), $request->user());

        return response()->noContent();
    }
}
