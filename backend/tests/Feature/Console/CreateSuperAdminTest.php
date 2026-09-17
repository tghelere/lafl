<?php

declare(strict_types=1);

use App\Enums\Role as RoleEnum;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

test('cria a primeira conta de super administrador e imprime o link de definição de senha', function (): void {
    $this->artisan('usuarios:criar-super-admin')
        ->expectsQuestion('Nome completo', 'Maria da Direção')
        ->expectsQuestion('E-mail', 'maria@exemplo.org.br')
        ->expectsOutputToContain('/definir-senha?token=')
        ->assertSuccessful();

    $user = User::query()->where('email', 'maria@exemplo.org.br')->firstOrFail();

    expect($user->name)->toBe('Maria da Direção')
        ->and($user->hasRole(RoleEnum::SuperAdmin->value))->toBeTrue()
        ->and($user->deactivated_at)->toBeNull();
});

test('a conta nasce sem senha utilizável', function (): void {
    $this->artisan('usuarios:criar-super-admin')
        ->expectsQuestion('Nome completo', 'Maria da Direção')
        ->expectsQuestion('E-mail', 'maria@exemplo.org.br')
        ->assertSuccessful();

    $user = User::query()->where('email', 'maria@exemplo.org.br')->firstOrFail();

    // Nenhuma senha previsível: nem a do seeder de desenvolvimento, nem o e-mail, nem vazio.
    foreach (['password', 'maria@exemplo.org.br', '', 'Maria da Direção'] as $guess) {
        expect(Hash::check($guess, $user->password))->toBeFalse();
    }
});

test('o link impresso aponta para o painel configurado e leva um token de verdade', function (): void {
    config(['forms.admin_base_url' => 'https://painel.exemplo.org.br']);

    $this->artisan('usuarios:criar-super-admin')
        ->expectsQuestion('Nome completo', 'Maria da Direção')
        ->expectsQuestion('E-mail', 'maria@exemplo.org.br')
        ->expectsOutputToContain('https://painel.exemplo.org.br/definir-senha?token=')
        ->assertSuccessful();

    expect(DB::table('password_reset_tokens')->where('email', 'maria@exemplo.org.br')->exists())->toBeTrue();
});

test('recusa criar quando já existe super administrador ativo', function (): void {
    userWithRole(RoleEnum::SuperAdmin->value);

    $this->artisan('usuarios:criar-super-admin')
        ->expectsOutputToContain('Já existe super administrador ativo')
        ->assertFailed();

    expect(User::query()->count())->toBe(1);
});

test('--forcar cria mesmo já existindo super administrador ativo', function (): void {
    userWithRole(RoleEnum::SuperAdmin->value);

    $this->artisan('usuarios:criar-super-admin', ['--forcar' => true])
        ->expectsQuestion('Nome completo', 'Segundo Super')
        ->expectsQuestion('E-mail', 'segundo@exemplo.org.br')
        ->assertSuccessful();

    expect(User::query()->role(RoleEnum::SuperAdmin->value)->active()->count())->toBe(2);
});

test('super administrador desativado não bloqueia a criação', function (): void {
    $existing = userWithRole(RoleEnum::SuperAdmin->value);
    $existing->forceFill(['deactivated_at' => now()])->save();

    $this->artisan('usuarios:criar-super-admin')
        ->expectsQuestion('Nome completo', 'Maria da Direção')
        ->expectsQuestion('E-mail', 'maria@exemplo.org.br')
        ->assertSuccessful();

    expect(User::query()->where('email', 'maria@exemplo.org.br')->exists())->toBeTrue();
});

test('recusa e-mail inválido sem criar nada', function (string $email): void {
    $this->artisan('usuarios:criar-super-admin')
        ->expectsQuestion('Nome completo', 'Maria da Direção')
        ->expectsQuestion('E-mail', $email)
        ->assertFailed();

    expect(User::query()->count())->toBe(0);
})->with(['', 'nao-e-email', 'maria@', '@exemplo.org.br']);

test('recusa e-mail já usado por outro usuário', function (): void {
    User::factory()->create(['email' => 'maria@exemplo.org.br']);

    $this->artisan('usuarios:criar-super-admin')
        ->expectsQuestion('Nome completo', 'Maria da Direção')
        ->expectsQuestion('E-mail', 'maria@exemplo.org.br')
        ->expectsOutputToContain('Já existe um usuário com este e-mail.')
        ->assertFailed();

    expect(User::query()->count())->toBe(1);
});

test('recusa nome em branco sem perguntar o e-mail', function (): void {
    $this->artisan('usuarios:criar-super-admin')
        ->expectsQuestion('Nome completo', '   ')
        ->assertFailed();

    expect(User::query()->count())->toBe(0);
});

test('recusa rodar antes dos papéis existirem', function (): void {
    DB::table(config('permission.table_names.roles'))->delete();

    $this->artisan('usuarios:criar-super-admin')
        ->expectsOutputToContain('O papel super_admin não existe neste banco.')
        ->assertFailed();
});

test('registra a criação, o papel e a origem console na auditoria', function (): void {
    $this->artisan('usuarios:criar-super-admin')
        ->expectsQuestion('Nome completo', 'Maria da Direção')
        ->expectsQuestion('E-mail', 'maria@exemplo.org.br')
        ->assertSuccessful();

    $user = User::query()->where('email', 'maria@exemplo.org.br')->firstOrFail();

    $events = Activity::query()
        ->where('subject_type', User::class)
        ->where('subject_id', $user->id)
        ->pluck('event')
        ->all();

    expect($events)->toContain('created')
        ->and($events)->toContain('roles_updated')
        ->and($events)->toContain('super_admin_bootstrapped')
        ->and($events)->toContain('password_link_generated');
});

test('a auditoria não guarda o token nem a URL do link', function (): void {
    $this->artisan('usuarios:criar-super-admin')
        ->expectsQuestion('Nome completo', 'Maria da Direção')
        ->expectsQuestion('E-mail', 'maria@exemplo.org.br')
        ->assertSuccessful();

    $token = DB::table('password_reset_tokens')->where('email', 'maria@exemplo.org.br')->value('token');

    expect($token)->not->toBeNull();

    $logged = Activity::query()->get()->map(
        fn (Activity $activity): string => $activity->description.json_encode($activity->properties),
    )->implode(' ');

    expect($logged)->not->toContain('definir-senha')
        ->and($logged)->not->toContain((string) $token);
});

test('a conta criada consegue definir a senha pelo token do link', function (): void {
    $this->artisan('usuarios:criar-super-admin')
        ->expectsQuestion('Nome completo', 'Maria da Direção')
        ->expectsQuestion('E-mail', 'maria@exemplo.org.br')
        ->assertSuccessful();

    // O token só existe hasheado no banco; o teste refaz o caminho real do painel usando o
    // token cru que o broker devolve, para provar que o fluxo inteiro fecha.
    $user = User::query()->where('email', 'maria@exemplo.org.br')->firstOrFail();
    $token = Password::broker('user_setup')->createToken($user);

    $this->postJson('/api/v1/auth/set-password', [
        'email' => 'maria@exemplo.org.br',
        'token' => $token,
        'password' => 'senha-bem-comprida-2026',
        'password_confirmation' => 'senha-bem-comprida-2026',
    ])->assertSuccessful();

    expect(Hash::check('senha-bem-comprida-2026', $user->fresh()->password))->toBeTrue();
});
