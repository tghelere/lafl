<?php

declare(strict_types=1);

namespace App\Http\Requests\Partners;

/**
 * Mesmas regras da criação, exceto a logo: trocá-la é opcional.
 */
final class UpdatePartnerRequest extends StorePartnerRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('partner')) ?? false;
    }

    protected function logoRequired(): bool
    {
        return false;
    }
}
