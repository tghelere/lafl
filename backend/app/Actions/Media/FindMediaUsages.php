<?php

declare(strict_types=1);

namespace App\Actions\Media;

use App\Models\Media;
use App\Models\Page;
use App\Support\Media\MediaUrl;

/**
 * As páginas cujo conteúdo usa a imagem — é o que bloqueia a exclusão e o que diz à pessoa
 * onde a imagem está.
 *
 * Busca pelo `src` canônico entre aspas (`src="/midia/{uuid}"`), que é a única forma que
 * App\Support\Html\ContentSanitizer deixa gravar. LIKE sem índice de texto: a tabela de
 * páginas é curta e fechada (as seções do site), a mesma avaliação da busca de
 * App\Http\Controllers\Api\V1\PageController::index.
 *
 * Página na lixeira não conta: ela não está no ar, e o painel não oferece como restaurá-la ou
 * apagá-la em definitivo — bloquear a exclusão por causa dela seria um beco sem saída. Se for
 * restaurada, a imagem ausente some da leitura pública (App\Actions\Media\ExpandContentImages)
 * e o próximo salvamento da página avisa (App\Actions\Content\AssertContentImagesArePublishable).
 */
final class FindMediaUsages
{
    /**
     * @return list<array{uuid: string, title: string, slug: string, status: string}>
     */
    public function handle(Media $media): array
    {
        return array_values(Page::query()
            ->where('content', 'like', '%src="'.MediaUrl::canonical($media->uuid).'"%')
            ->orderBy('title')
            ->get(['uuid', 'title', 'slug', 'status'])
            ->map(fn (Page $page): array => [
                'uuid' => $page->uuid,
                'title' => $page->title,
                'slug' => $page->slug,
                'status' => $page->status->value,
            ])
            ->all());
    }
}
