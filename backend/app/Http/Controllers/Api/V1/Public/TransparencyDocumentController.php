<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Actions\Transparency\ListPublicTransparencyDocuments;
use App\Actions\Transparency\RegisterTransparencyDocumentDownload;
use App\Enums\TransparencyDocumentType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Public\TransparencyDocumentResource;
use App\Models\TransparencyDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

final class TransparencyDocumentController extends Controller
{
    /**
     * Filtro por ano e tipo via query string (ver docs/estrutura-site.md §3.1) — valor de
     * tipo desconhecido é ignorado em vez de gerar erro, mesma tolerância de um filtro
     * opcional de listagem pública sem autenticação.
     */
    public function index(Request $request, ListPublicTransparencyDocuments $action): AnonymousResourceCollection
    {
        $year = $request->filled('year') ? $request->integer('year') : null;
        $type = $request->filled('type') ? TransparencyDocumentType::tryFrom($request->string('type')->value()) : null;
        $perPage = min($request->integer('per_page', 20), 50);

        $documents = $action->handle($year, $type, $perPage);

        return TransparencyDocumentResource::collection($documents);
    }

    public function download(string $uuid, RegisterTransparencyDocumentDownload $action): Response
    {
        $document = TransparencyDocument::query()->published()->where('uuid', $uuid)->first();

        if ($document === null) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $action->handle($document);

        return Storage::disk('local')->download($document->file_path, $document->title.'.pdf');
    }
}
