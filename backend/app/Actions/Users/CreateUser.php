<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

final class CreateUser
{
    /**
     * @param  list<string>  $roles
     */
    public function handle(string $name, string $email, array $roles): User
    {
        $user = new User;
        $user->name = $name;
        $user->email = $email;
        // Nasce sem senha utilizável — string aleatória que ninguém conhece, hasheada pelo
        // cast 'hashed' do model ao salvar. Só a etapa de definição de senha por link (ver
        // App\Actions\Users\SetUserPassword) troca isto por uma senha de verdade.
        $user->password = Str::random(40);
        $user->save();

        $user->syncRoles($roles);

        // Criação em si é logada automaticamente por User::getActivitylogOptions()
        // (LogsActivity, evento 'created' com name/email) — papel atribuído não é coluna de
        // users (pivot do spatie/permission), por isso este evento manual, mesmo formato do
        // usado em alteração de papéis (ver App\Actions\Users\UpdateUser).
        activity('users')
            ->causedBy(Auth::user())
            ->performedOn($user)
            ->withProperties(['from' => [], 'to' => $roles])
            ->event('roles_updated')
            ->log('Papéis atribuídos na criação');

        return $user;
    }
}
