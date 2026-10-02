<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Public\PartnerResource;
use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class PartnerController extends Controller
{
    /**
     * Só parceiros ativos, na ordem do painel. Logo marcada depois como foto de assistido
     * (impublicável) tira o parceiro da lista, pelo mesmo motivo de BuildPublicPageImages: a
     * imagem não pode sair, e a lista não pode apontar para um 404.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $partners = Partner::query()
            ->activeOrdered()
            ->whereHas('media', fn ($media) => $media->where('depicts_assisted_minor', false))
            ->with('media')
            ->paginate(min($request->integer('per_page', 50), 100));

        return PartnerResource::collection($partners);
    }
}
