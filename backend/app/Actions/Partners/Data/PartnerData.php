<?php

declare(strict_types=1);

namespace App\Actions\Partners\Data;

final readonly class PartnerData
{
    /**
     * @param  ?string  $logoPath  caminho do arquivo enviado; null na atualização = manter a logo
     * @param  ?int  $position  null = fim da lista, na criação; manter, na atualização
     */
    public function __construct(
        public string $name,
        public ?string $url,
        public ?string $logoPath,
        public ?int $position,
        public bool $isActive,
    ) {}
}
