<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;

final class VolunteerApplicationPolicy extends FormSubmissionPolicy
{
    protected function allowedRoles(): array
    {
        return [...parent::allowedRoles(), Role::Atendimento->value];
    }
}
