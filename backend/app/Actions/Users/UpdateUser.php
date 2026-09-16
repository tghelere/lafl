<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateUser
{
    public function __construct(private readonly AssertLastActiveSuperAdminSurvives $assertLastActiveSuperAdminSurvives) {}

    /**
     * @param  list<string>  $roles
     */
    public function handle(User $actingUser, User $target, string $name, string $email, array $roles): User
    {
        $currentRoles = $target->getRoleNames()->all();
        $removesSuperAdminFromSelf = $target->is($actingUser)
            && in_array(Role::SuperAdmin->value, $currentRoles, true)
            && ! in_array(Role::SuperAdmin->value, $roles, true);

        if ($removesSuperAdminFromSelf) {
            throw ValidationException::withMessages([
                'roles' => ['Não é possível remover o próprio papel de super administrador.'],
            ]);
        }

        return DB::transaction(function () use ($actingUser, $target, $name, $email, $roles, $currentRoles): User {
            if ($target->hasRole(Role::SuperAdmin->value) && ! in_array(Role::SuperAdmin->value, $roles, true)) {
                $this->assertLastActiveSuperAdminSurvives->handle($target);
            }

            $target->name = $name;
            $target->email = $email;
            $target->save();

            $target->syncRoles($roles);

            $rolesChanged = array_diff($currentRoles, $roles) !== [] || array_diff($roles, $currentRoles) !== [];

            if ($rolesChanged) {
                activity('users')
                    ->causedBy($actingUser)
                    ->performedOn($target)
                    ->withProperties(['from' => $currentRoles, 'to' => $roles])
                    ->event('roles_updated')
                    ->log('Papéis atualizados');
            }

            return $target->fresh() ?? $target;
        });
    }
}
