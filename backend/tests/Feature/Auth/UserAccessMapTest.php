<?php

declare(strict_types=1);

use App\Enums\Role;

test('mapa de acesso de comunicacao só libera pages', function (): void {
    $user = userWithRole(Role::Comunicacao->value);

    $access = $this->actingAs($user)->getJson('/api/v1/auth/user')->json('data.access');

    expect($access)->toBe([
        'pages' => true,
        'transparency-documents' => false,
        'users' => false,
        'program-applications' => false,
        'pickup-requests' => false,
        'volunteer-applications' => false,
        'partnership-inquiries' => false,
        'contact-messages' => false,
    ]);
});

test('mapa de acesso de financeiro só libera transparency-documents', function (): void {
    $user = userWithRole(Role::Financeiro->value);

    $access = $this->actingAs($user)->getJson('/api/v1/auth/user')->json('data.access');

    expect($access['transparency-documents'])->toBeTrue()
        ->and($access['pages'])->toBeFalse()
        ->and($access['users'])->toBeFalse()
        ->and(collect($access)->except(['transparency-documents'])->every(fn (bool $value) => $value === false))->toBeTrue();
});

test('mapa de acesso de super_admin libera tudo, inclusive users', function (): void {
    $user = userWithRole(Role::SuperAdmin->value);

    $access = $this->actingAs($user)->getJson('/api/v1/auth/user')->json('data.access');

    expect(collect($access)->every(fn (bool $value) => $value === true))->toBeTrue();
});

test('mapa de acesso de usuário com dois papéis soma os dois', function (): void {
    $user = userWithRole(Role::Financeiro->value);
    $user->assignRole(Role::Contraturno->value);

    $access = $this->actingAs($user)->getJson('/api/v1/auth/user')->json('data.access');

    expect($access['transparency-documents'])->toBeTrue()
        ->and($access['program-applications'])->toBeTrue()
        ->and($access['partnership-inquiries'])->toBeTrue()
        ->and($access['pages'])->toBeFalse()
        ->and($access['pickup-requests'])->toBeFalse()
        ->and($access['volunteer-applications'])->toBeFalse()
        ->and($access['contact-messages'])->toBeFalse()
        ->and($access['users'])->toBeFalse();
});
