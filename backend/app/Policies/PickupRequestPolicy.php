<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;

final class PickupRequestPolicy extends FormSubmissionPolicy
{
    protected function allowedRoles(): array
    {
        return [...parent::allowedRoles(), Role::Bazar->value];
    }
}
