<?php

declare(strict_types=1);

use App\Enums\PageStatus;
use App\Enums\Role;
use App\Models\Page;
use App\Models\PageSlugHistory;

test('endpoint público devolve página publicada', function (): void {
    Page::factory()->published()->create([
        'slug' => 'quem-somos',
        'title' => 'Quem Somos',
    ]);

    $response = $this->getJson('/api/v1/public/pages/quem-somos');

    $response->assertOk()
        ->assertJsonPath('data.slug', 'quem-somos')
        ->assertJsonPath('data.title', 'Quem Somos')
        ->assertJsonMissingPath('data.status')
        ->assertJsonMissingPath('data.id')
        ->assertJsonMissingPath('data.created_at');
});

test('endpoint público nunca devolve página em rascunho ou arquivada', function (): void {
    Page::factory()->create(['slug' => 'rascunho', 'status' => PageStatus::Draft]);
    Page::factory()->archived()->create(['slug' => 'arquivada']);

    $this->getJson('/api/v1/public/pages/rascunho')->assertNotFound();
    $this->getJson('/api/v1/public/pages/arquivada')->assertNotFound();
});

test('endpoint público resolve slug de dois níveis', function (): void {
    Page::factory()->published()->create(['slug' => 'quem-somos']);
    Page::factory()->published()->create(['slug' => 'quem-somos/nossa-historia', 'title' => 'Nossa História']);

    $this->getJson('/api/v1/public/pages/quem-somos/nossa-historia')
        ->assertOk()
        ->assertJsonPath('data.slug', 'quem-somos/nossa-historia')
        ->assertJsonPath('data.title', 'Nossa História');
});

test('endpoint público sinaliza slug antigo com redirect_to', function (): void {
    // 200, não 301: este é o hop servidor-a-servidor (Nuxt → API); o 301 real, visível ao
    // navegador, é responsabilidade do frontend a partir deste campo (ver
    // App\Http\Controllers\Api\V1\Public\PageController).
    $page = Page::factory()->published()->create(['slug' => 'novo-slug']);
    PageSlugHistory::create(['page_id' => $page->id, 'slug' => 'slug-antigo']);

    $response = $this->getJson('/api/v1/public/pages/slug-antigo');

    $response->assertOk()->assertJsonPath('redirect_to', 'novo-slug');
});

test('slug nunca existente devolve 404', function (): void {
    $this->getJson('/api/v1/public/pages/nao-existe')->assertNotFound();
});

test('renomear página via admin muda a resolução pública imediatamente', function (): void {
    $user = userWithRole(Role::Direcao->value);
    $page = Page::factory()->published()->create(['slug' => 'antigo']);

    // Aquece o cache do slug antigo antes da renomeação.
    $this->getJson('/api/v1/public/pages/antigo')->assertOk();

    $this->actingAs($user)->putJson("/api/v1/pages/{$page->uuid}", [
        'slug' => 'novo',
        'title' => $page->title,
        'content' => $page->content,
        'status' => PageStatus::Published->value,
    ])->assertOk();

    $this->getJson('/api/v1/public/pages/antigo')
        ->assertOk()
        ->assertJsonPath('redirect_to', 'novo');

    $this->getJson('/api/v1/public/pages/novo')
        ->assertOk()
        ->assertJsonPath('data.slug', 'novo');
});
