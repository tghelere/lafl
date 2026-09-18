<?php

declare(strict_types=1);

namespace App\Actions\Content;

use App\Models\Page;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListPublicPages
{
    /**
     * Listagem mínima das páginas publicadas — só o que um sitemap precisa: o slug e a data
     * da última alteração. Nada de conteúdo: quem quer o texto de uma página pede a página
     * (App\Actions\Content\ResolvePublicPageBySlug), e devolver o site inteiro numa resposta
     * só seria um jeito caro de fazer o que já existe.
     *
     * Paginada como toda listagem do projeto (ver CLAUDE.md). Quem monta o sitemap percorre
     * as páginas até a última — ver frontend-site/server/routes/sitemap.xml.ts.
     *
     * @return LengthAwarePaginator<int, Page>
     */
    public function handle(int $perPage): LengthAwarePaginator
    {
        return Page::query()
            ->published()
            ->select(['slug', 'updated_at'])
            ->orderBy('slug')
            ->paginate($perPage);
    }
}
