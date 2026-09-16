<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;

final class ProgramApplicationPolicy extends FormSubmissionPolicy
{
    protected function allowedRoles(): array
    {
        return [...parent::allowedRoles(), Role::Contraturno->value];
    }
}
