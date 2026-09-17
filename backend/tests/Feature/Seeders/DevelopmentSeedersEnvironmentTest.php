<?php

declare(strict_types=1);

use App\Models\Page;
use App\Models\TransparencyDocument;
use App\Models\User;
use Database\Seeders\ContentPagesSeeder;
use Database\Seeders\DevSuperAdminSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TransparencyDocumentsSeeder;

/**
 * Seeder de desenvolvimento em staging/production seria dado de mentira dentro de um
 * ambiente que a instituição usa para valer: uma conta com senha `password` no painel de
 * homologação e um acervo de transparência com PDFs em branco no lugar dos documentos
 * reais. Cada seeder já tem sua guarda de ambiente; este teste é o que impede alguém de
 * afrouxá-la sem perceber.
 *
 * Instanciados direto (não via `db:seed`) de propósito: `db:seed` em production dispara a
 * confirmação do próprio Artisan e o que se testa aqui é a guarda dentro do seeder, que é
 * quem realmente protege.
 */
test('nenhum seeder de desenvolvimento roda em staging ou production', function (string $environment): void {
    app(RoleSeeder::class)->run();

    app()['env'] = $environment;

    try {
        app(DevSuperAdminSeeder::class)->run();
        app(ContentPagesSeeder::class)->run();
        app(TransparencyDocumentsSeeder::class)->run();
    } finally {
        app()['env'] = 'testing';
    }

    expect(User::query()->count())->toBe(0)
        ->and(Page::query()->withTrashed()->count())->toBe(0)
        ->and(TransparencyDocument::query()->count())->toBe(0);
})->with(['staging', 'production']);

/**
 * O contrapositivo: a guarda barra staging/production, não o ambiente de teste. Sem isto,
 * um `environment()` sempre falso passaria despercebido pelo teste acima.
 */
test('os mesmos seeders rodam em testing', function (): void {
    app(RoleSeeder::class)->run();
    app(DevSuperAdminSeeder::class)->run();
    app(ContentPagesSeeder::class)->run();

    expect(User::query()->count())->toBe(1)
        ->and(Page::query()->count())->toBeGreaterThan(0);
});
