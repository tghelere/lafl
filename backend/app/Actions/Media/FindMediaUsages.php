<?php

declare(strict_types=1);

namespace App\Actions\Media;

use App\Enums\PageImageRole;
use App\Models\Media;
use App\Models\Page;
use App\Support\Media\MediaUrl;

/**
 * As páginas que usam a imagem, e onde: no texto, na capa ou na galeria. É o que bloqueia a
 * exclusão, o que diz à pessoa onde a imagem está e o que diz a ForgetPagesUsingMedia qual
 * cache esquecer.
 *
 * No texto: busca pelo `src` canônico entre aspas (`src="/midia/{uuid}"`), que é a única forma
 * que App\Support\Html\ContentSanitizer deixa gravar. LIKE sem índice de texto: a tabela de
 * páginas é curta e fechada (as seções do site), a mesma avaliação da busca de
 * App\Http\Controllers\Api\V1\PageController::index. Capa e galeria: pela ligação em
 * `page_images`.
 *
 * Página na lixeira não conta: ela não está no ar, e o painel não oferece como restaurá-la ou
 * apagá-la em definitivo — bloquear a exclusão por causa dela seria um beco sem saída. Se for
 * restaurada, a imagem ausente some da leitura pública (App\Actions\Media\ExpandContentImages)
 * e o próximo salvamento da página avisa (App\Actions\Content\AssertContentImagesArePublishable).
 */
final class FindMediaUsages
{
    /**
     * @return list<array{uuid: string, title: string, slug: string, status: string, places: list<string>}>
     */
    public function handle(Media $media): array
    {
        $pages = Page::query()
            ->where(fn ($query) => $query
                ->where('content', 'like', '%src="'.MediaUrl::canonical($media->uuid).'"%')
                ->orWhereHas('images', fn ($images) => $images->where('media_id', $media->id)))
            ->with(['images' => fn ($images) => $images->where('media_id', $media->id)])
            ->orderBy('title')
            ->get(['id', 'uuid', 'title', 'slug', 'status', 'content']);

        return array_values($pages->map(function (Page $page) use ($media): array {
            $places = str_contains($page->content, 'src="'.MediaUrl::canonical($media->uuid).'"') ? ['content'] : [];

            foreach ([PageImageRole::Cover, PageImageRole::Gallery] as $role) {
                if ($page->images->contains(fn ($image): bool => $image->role === $role)) {
                    $places[] = $role->value;
                }
            }

            return [
                'uuid' => $page->uuid,
                'title' => $page->title,
                'slug' => $page->slug,
                'status' => $page->status->value,
                'places' => $places,
            ];
        })->all());
    }
}
