<?php

declare(strict_types=1);

use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\User;
use App\Support\Content\InitialPages;
use Database\Seeders\DatabaseSeeder;

/**
 * O que o setup documentado no README (`php artisan migrate:fresh --seed`) promete entregar:
 * um painel em que dá para entrar e um site em que todas as páginas publicadas respondem.
 *
 * Existe porque esses dois sintomas — login recusado e página do site em 404 — são
 * indistinguíveis, para quem olha de fora, de uma queda de infraestrutura (ver README,
 * "Troubleshooting"). Uma hora do diagnóstico da sessão 22 foi gasta descartando "o seeder
 * mudou"; com este teste, essa hipótese se descarta em segundos.
 *
 * Passa pelo endpoint real de login e pelo endpoint real de conteúdo, não pelos models: o que
 * quebraria o setup é justamente o que só aparece atravessando a pilha inteira — usuário sem
 * papel, conta desativada, senha que não confere, página semeada como rascunho.
 */
test('o setup do zero cria um dev que entra de fato no painel', function (): void {
    $this->seed(DatabaseSeeder::class);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'dev@laranaliafranco.local',
        'password' => 'password',
    ])->assertOk()->assertJsonPath('data.roles', ['super_admin']);

    expect(User::query()->where('email', 'dev@laranaliafranco.local')->first())
        ->deactivated_at->toBeNull();
});

/**
 * Reseedar um banco que já existe também precisa devolver o acesso. `migrate:fresh --seed`
 * nunca cai neste caso (recria a tabela), mas `db:seed` sozinho cairia, e é o que a pessoa
 * roda quando o dev foi desativado sem querer pelo CRUD de usuários do painel.
 */
test('reseedar reativa o dev que tinha sido desativado', function (): void {
    $this->seed(DatabaseSeeder::class);

    User::query()->where('email', 'dev@laranaliafranco.local')
        ->update(['deactivated_at' => now()]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'dev@laranaliafranco.local',
        'password' => 'password',
    ])->assertStatus(422);

    $this->seed(DatabaseSeeder::class);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'dev@laranaliafranco.local',
        'password' => 'password',
    ])->assertOk();
});

test('toda página que o conteúdo inicial marca como publicada responde no site', function (): void {
    $this->seed(DatabaseSeeder::class);

    $publicadas = array_values(array_filter(
        InitialPages::all(),
        fn (array $data): bool => ($data['status'] ?? PageStatus::Published) === PageStatus::Published,
    ));

    expect($publicadas)->not->toBeEmpty();

    foreach ($publicadas as $data) {
        $this->getJson("/api/v1/public/pages/{$data['slug']}")
            ->assertOk()
            ->assertJsonPath('data.slug', $data['slug']);
    }

    // O contrapositivo: rascunho continua invisível no site. Sem isto, um seeder que
    // publicasse tudo passaria no teste acima e estragaria em silêncio o estado de rascunho.
    expect(Page::query()->where('status', PageStatus::Draft)->count())->toBeGreaterThan(0);
});
