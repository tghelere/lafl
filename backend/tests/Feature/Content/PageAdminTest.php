<?php

declare(strict_types=1);

use App\Enums\PageStatus;
use App\Enums\Role;
use App\Models\Page;
use App\Models\PageSlugHistory;
use Spatie\Activitylog\Models\Activity;

test('não autenticado recebe 401 ao listar páginas', function (): void {
    $this->getJson('/api/v1/pages')->assertUnauthorized();
});

test('direcao pode criar página', function (): void {
    $user = userWithRole(Role::Direcao->value);

    $response = $this->actingAs($user)->postJson('/api/v1/pages', [
        'slug' => 'quem-somos',
        'title' => 'Quem Somos',
        'content' => 'Conteúdo institucional de teste.',
        'status' => PageStatus::Draft->value,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.slug', 'quem-somos')
        ->assertJsonPath('data.status', 'draft');

    expect(Page::where('slug', 'quem-somos')->exists())->toBeTrue();
});

test('comunicacao pode criar e publicar página', function (): void {
    $user = userWithRole(Role::Comunicacao->value);

    $response = $this->actingAs($user)->postJson('/api/v1/pages', [
        'slug' => 'quem-somos',
        'title' => 'Quem Somos',
        'content' => 'Conteúdo institucional de teste.',
        'status' => PageStatus::Published->value,
    ]);

    $response->assertCreated()->assertJsonPath('data.status', 'published');
    expect($response->json('data.published_at'))->not->toBeNull();
});

test('atendimento não acessa pages em nenhuma operação', function (): void {
    $user = userWithRole(Role::Atendimento->value);
    $page = Page::factory()->create();

    $this->actingAs($user)->getJson('/api/v1/pages')->assertForbidden();
    $this->actingAs($user)->getJson("/api/v1/pages/{$page->uuid}")->assertForbidden();
    $this->actingAs($user)->postJson('/api/v1/pages', [
        'slug' => 'x',
        'title' => 'X',
        'content' => 'x',
        'status' => PageStatus::Draft->value,
    ])->assertForbidden();
});

test('bazar não acessa pages em nenhuma operação', function (): void {
    $user = userWithRole(Role::Bazar->value);
    $page = Page::factory()->create();

    $this->actingAs($user)->getJson('/api/v1/pages')->assertForbidden();
    $this->actingAs($user)->getJson("/api/v1/pages/{$page->uuid}")->assertForbidden();
    $this->actingAs($user)->postJson('/api/v1/pages', [
        'slug' => 'x',
        'title' => 'X',
        'content' => 'x',
        'status' => PageStatus::Draft->value,
    ])->assertForbidden();
});

test('super_admin tem acesso total independente da policy', function (): void {
    $user = userWithRole(Role::SuperAdmin->value);

    $this->actingAs($user)->postJson('/api/v1/pages', [
        'slug' => 'quem-somos',
        'title' => 'Quem Somos',
        'content' => 'Conteúdo institucional de teste.',
        'status' => PageStatus::Published->value,
    ])->assertCreated();
});

test('slug com mais de dois níveis é rejeitado', function (): void {
    $user = userWithRole(Role::Direcao->value);

    $this->actingAs($user)->postJson('/api/v1/pages', [
        'slug' => 'a/b/c',
        'title' => 'X',
        'content' => 'x',
        'status' => PageStatus::Draft->value,
    ])->assertStatus(422)->assertJsonValidationErrors('slug');
});

test('slug de dois níveis sem página-mãe existente é rejeitado', function (): void {
    $user = userWithRole(Role::Direcao->value);

    $this->actingAs($user)->postJson('/api/v1/pages', [
        'slug' => 'quem-somos/nossa-historia',
        'title' => 'Nossa História',
        'content' => 'x',
        'status' => PageStatus::Draft->value,
    ])->assertStatus(422)->assertJsonValidationErrors('slug');
});

test('slug de dois níveis com página-mãe existente é aceito', function (): void {
    $user = userWithRole(Role::Direcao->value);
    Page::factory()->create(['slug' => 'quem-somos']);

    $this->actingAs($user)->postJson('/api/v1/pages', [
        'slug' => 'quem-somos/nossa-historia',
        'title' => 'Nossa História',
        'content' => 'x',
        'status' => PageStatus::Draft->value,
    ])->assertCreated();
});

test('slug com formato inválido é rejeitado', function (): void {
    $user = userWithRole(Role::Direcao->value);

    $this->actingAs($user)->postJson('/api/v1/pages', [
        'slug' => 'Quem Somos!',
        'title' => 'X',
        'content' => 'x',
        'status' => PageStatus::Draft->value,
    ])->assertStatus(422)->assertJsonValidationErrors('slug');
});

test('renomear slug grava histórico', function (): void {
    $user = userWithRole(Role::Direcao->value);
    $page = Page::factory()->published()->create(['slug' => 'antigo']);

    $this->actingAs($user)->putJson("/api/v1/pages/{$page->uuid}", [
        'slug' => 'novo',
        'title' => $page->title,
        'content' => $page->content,
        'status' => PageStatus::Published->value,
    ])->assertOk()->assertJsonPath('data.slug', 'novo');

    expect(PageSlugHistory::where('slug', 'antigo')->where('page_id', $page->id)->exists())->toBeTrue();
});

test('criar página registra auditoria', function (): void {
    $user = userWithRole(Role::Direcao->value);

    $this->actingAs($user)->postJson('/api/v1/pages', [
        'slug' => 'quem-somos',
        'title' => 'Quem Somos',
        'content' => 'x',
        'status' => PageStatus::Published->value,
    ])->assertCreated();

    expect(Activity::where('event', 'created')->where('subject_type', Page::class)->exists())->toBeTrue();
});

test('excluir página é auditado e some das listagens', function (): void {
    $user = userWithRole(Role::Direcao->value);
    $page = Page::factory()->create();

    $this->actingAs($user)->deleteJson("/api/v1/pages/{$page->uuid}")->assertNoContent();

    // Page::find() respeita o escopo global de SoftDeletes; fresh() não, por isso não serve
    // para provar soft delete.
    expect(Page::find($page->id))->toBeNull()
        ->and($page->fresh()->deleted_at)->not->toBeNull()
        ->and(Activity::where('event', 'deleted')->where('subject_id', $page->id)->where('subject_type', Page::class)->exists())->toBeTrue();
});

test('conteúdo salvo pela API é sanitizado antes de ir para o banco', function (): void {
    $user = userWithRole(Role::Direcao->value);

    $this->actingAs($user)->postJson('/api/v1/pages', [
        'slug' => 'quem-somos',
        'title' => 'Quem Somos',
        'content' => '<p>Texto legítimo.</p><script>alert(1)</script><p><a href="javascript:alert(1)">x</a></p>',
        'status' => PageStatus::Published->value,
    ])->assertCreated();

    $content = Page::where('slug', 'quem-somos')->value('content');

    expect($content)->toBe('<p>Texto legítimo.</p><p><a>x</a></p>');
});

test('conteúdo atualizado pela API também é sanitizado', function (): void {
    $user = userWithRole(Role::Comunicacao->value);
    $page = Page::factory()->published()->create(['slug' => 'quem-somos']);

    $this->actingAs($user)->putJson("/api/v1/pages/{$page->uuid}", [
        'slug' => $page->slug,
        'title' => $page->title,
        'content' => '<p onclick="alert(1)">Texto</p><iframe src="https://exemplo.invalid"></iframe>',
        'status' => PageStatus::Published->value,
    ])->assertOk();

    expect($page->fresh()->content)->toBe('<p>Texto</p>');
});

/**
 * Recorte desta fatia: a tela do painel não expõe slug nem status, mas a API continua
 * aceitando os dois campos. Para quem não tem `direcao`, o que vier neles é ignorado — ver
 * UpdatePageRequest::prepareForValidation() e PagePolicy::managePublication().
 */
test('comunicacao não muda slug nem status pela API: os valores atuais são mantidos', function (): void {
    $user = userWithRole(Role::Comunicacao->value);
    $page = Page::factory()->create([
        'slug' => 'quem-somos',
        'status' => PageStatus::Draft,
        'published_at' => null,
    ]);

    $this->actingAs($user)->putJson("/api/v1/pages/{$page->uuid}", [
        'slug' => 'endereco-sequestrado',
        'title' => 'Título novo',
        'content' => '<p>Conteúdo novo.</p>',
        'status' => PageStatus::Published->value,
    ])->assertOk()
        ->assertJsonPath('data.slug', 'quem-somos')
        ->assertJsonPath('data.status', 'draft');

    $page->refresh();

    // O que a fatia permite mudar mudou de verdade; o que ela não permite ficou parado.
    expect($page->title)->toBe('Título novo')
        ->and($page->content)->toBe('<p>Conteúdo novo.</p>')
        ->and($page->slug)->toBe('quem-somos')
        ->and($page->status)->toBe(PageStatus::Draft)
        ->and($page->published_at)->toBeNull()
        ->and(PageSlugHistory::where('page_id', $page->id)->exists())->toBeFalse();
});

test('direcao continua podendo mudar slug e status', function (): void {
    $user = userWithRole(Role::Direcao->value);
    $page = Page::factory()->create([
        'slug' => 'antigo',
        'status' => PageStatus::Draft,
        'published_at' => null,
    ]);

    $this->actingAs($user)->putJson("/api/v1/pages/{$page->uuid}", [
        'slug' => 'novo',
        'title' => $page->title,
        'content' => '<p>x</p>',
        'status' => PageStatus::Published->value,
    ])->assertOk()
        ->assertJsonPath('data.slug', 'novo')
        ->assertJsonPath('data.status', 'published');
});

test('super_admin continua podendo mudar slug e status', function (): void {
    $user = userWithRole(Role::SuperAdmin->value);
    $page = Page::factory()->create(['slug' => 'antigo', 'status' => PageStatus::Draft]);

    $this->actingAs($user)->putJson("/api/v1/pages/{$page->uuid}", [
        'slug' => 'novo',
        'title' => $page->title,
        'content' => '<p>x</p>',
        'status' => PageStatus::Published->value,
    ])->assertOk()
        ->assertJsonPath('data.slug', 'novo')
        ->assertJsonPath('data.status', 'published');
});
