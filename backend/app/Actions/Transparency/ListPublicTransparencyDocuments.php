<?php

declare(strict_types=1);

namespace App\Actions\Transparency;

use App\Enums\TransparencyDocumentType;
use App\Models\TransparencyDocument;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListPublicTransparencyDocuments
{
    /**
     * @return LengthAwarePaginator<int, TransparencyDocument>
     */
    public function handle(?int $year, ?TransparencyDocumentType $type, int $perPage): LengthAwarePaginator
    {
        return TransparencyDocument::query()
            ->published()
            ->when($year !== null, fn ($query) => $query->where('year', $year))
            ->when($type !== null, fn ($query) => $query->where('type', $type))
            ->orderByDesc('year')
            ->orderBy('title')
            ->paginate($perPage);
    }
}
