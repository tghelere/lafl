<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Actions\Transparency\ListPublicTransparencyDocuments;
use App\Actions\Transparency\RegisterTransparencyDocumentDownload;
use App\Enums\TransparencyDocumentType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Public\TransparencyDocumentResource;
use App\Models\TransparencyDocument;
use App\Support\Transparency\DocumentUrl;
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

    /**
     * Serve o PDF pela URL legível. Quem chama é a rota de proxy do site (ver
     * App\Support\Transparency\DocumentUrl) — o visitante nunca vê este endereço.
     *
     * `inline`, não `attachment`: buscador indexa PDF que abre no navegador e ignora, na
     * prática, o que é entregue como anexo. O botão "Baixar PDF" do site continua baixando
     * pelo atributo `download` do link, sem precisar de uma segunda URL.
     */
    public function file(Request $request, int $year, string $slug, RegisterTransparencyDocumentDownload $action): Response
    {
        $document = TransparencyDocument::query()->published()->where('slug', $slug)->first();
        $disk = Storage::disk('local');

        if ($document === null || ! $disk->exists($document->file_path)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        // O ano faz parte da URL, mas não da identidade: corrigir o ano de um documento já
        // publicado move o endereço canônico, e o antigo tem de continuar levando até ele.
        if ($document->year !== $year) {
            return redirect()->away(DocumentUrl::absolute($document), Response::HTTP_MOVED_PERMANENTLY);
        }

        $action->handle($document, $request->userAgent());

        return $disk->response($document->file_path, $document->slug.'.pdf', [
            'Content-Type' => 'application/pdf',
            // Uma hora: o arquivo pode ser substituído pelo painel mantendo o mesmo endereço
            // (o slug não muda), então cache eterno serviria versão velha de um documento
            // oficial. Uma hora já absorve a rajada de um documento recém-divulgado.
            'Cache-Control' => 'public, max-age=3600',
        ], 'inline');
    }

    /**
     * Endereço antigo, por uuid, de quando o PDF saía como `attachment` pela API. Continua
     * existindo só para não quebrar link já distribuído: responde 301 para a URL legível no
     * domínio do site, que é a canônica.
     */
    public function download(string $uuid): Response
    {
        $document = TransparencyDocument::query()->published()->where('uuid', $uuid)->first();

        if ($document === null) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return redirect()->away(DocumentUrl::absolute($document), Response::HTTP_MOVED_PERMANENTLY);
    }
}
