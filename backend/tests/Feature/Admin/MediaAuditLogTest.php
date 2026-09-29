<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Media;
use App\Models\Page;
use Database\Seeders\ContentPagesSeeder;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\Images;

/**
 * Aba "Imagens" da Auditoria — `GET /api/v1/audit-logs/media` (sessão 29, item 4). Percorre a
 * vida de uma imagem pelas rotas de verdade e confere cada acontecimento na tela.
 */
beforeEach(function (): void {
    Storage::fake('local');
});

test('não autenticado recebe 401, e nenhum papel de área entra — nem direcao', function (string $role): void {
    $this->getJson('/api/v1/audit-logs/media')->assertUnauthorized();
    $this->actingAs(userWithRole($role))->getJson('/api/v1/audit-logs/media')->assertForbidden();
})->with([Role::Direcao->value, Role::Comunicacao->value, Role::Financeiro->value]);

test('a vida de uma imagem aparece inteira, do envio à exclusão, com autor e detalhe', function (): void {
    $comunicacao = userWithRole(Role::Comunicacao->value);
    $direcao = userWithRole(Role::Direcao->value);
    $page = Page::factory()->published()->create(['slug' => 'bazar', 'title' => 'Bazar']);

    // Enviada direto para a galeria da página: `uploaded` e `placed`.
    $id = $this->actingAs($comunicacao)->post("/api/v1/pages/{$page->uuid}/images", [
        'file' => Images::jpeg(1600, 1000), 'alt' => 'Salão do bazar', 'depicts_assisted_minor' => '0',
    ], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
    $second = $this->actingAs($comunicacao)->post("/api/v1/pages/{$page->uuid}/images", [
        'file' => Images::jpeg(800, 600), 'alt' => 'Entrada do bazar', 'depicts_assisted_minor' => '0',
    ], ['Accept' => 'application/json'])->json('data.id');

    $this->actingAs($comunicacao)->postJson("/api/v1/pages/{$page->uuid}/images/{$id}/move", ['direction' => 'down'])->assertOk();
    $this->actingAs($comunicacao)->post("/api/v1/media/{$id}/file", ['file' => Images::jpeg(1200, 800)], ['Accept' => 'application/json'])->assertOk();
    $this->actingAs($comunicacao)->putJson("/api/v1/media/{$id}", ['alt' => 'Salão do bazar, visto da porta', 'depicts_assisted_minor' => false])->assertOk();
    $this->actingAs($comunicacao)->deleteJson("/api/v1/pages/{$page->uuid}/images/{$id}?role=gallery")->assertNoContent();
    $this->actingAs($comunicacao)->putJson("/api/v1/media/{$id}", [
        'alt' => 'Salão do bazar, visto da porta', 'depicts_assisted_minor' => true, 'confirm_marking' => true,
    ])->assertOk();
    $this->actingAs($direcao)->deleteJson("/api/v1/media/{$id}")->assertNoContent();

    $rows = $this->actingAs(userWithRole(Role::SuperAdmin->value))
        ->getJson('/api/v1/audit-logs/media?per_page=100')
        ->assertOk()
        ->json('data');

    $mine = array_values(array_filter($rows, fn (array $row): bool => str_starts_with((string) $row['media_alt'], 'Salão do bazar')));
    $byEvent = fn (string $event): array => array_values(array_filter($mine, fn (array $row): bool => $row['event'] === $event))[0];

    // Mais recente primeiro.
    expect(array_column($mine, 'event'))->toBe(['deleted', 'marked', 'removed_from_page', 'updated', 'replaced', 'moved', 'placed', 'uploaded']);

    expect($byEvent('uploaded'))->toMatchArray(['action_label' => 'Imagem enviada', 'detail' => '1600 × 1000 px', 'user' => $comunicacao->name])
        ->and($byEvent('placed')['detail'])->toBe('Galeria de /bazar')
        ->and($byEvent('moved')['detail'])->toBe('Galeria de /bazar: da posição 1 para a 2')
        ->and($byEvent('replaced')['detail'])->toBe('De 1600 × 1000 px para 1200 × 800 px')
        ->and($byEvent('updated')['detail'])->toBe('Alterado: texto alternativo')
        ->and($byEvent('removed_from_page')['action_label'])->toBe('Tirada da página')
        ->and($byEvent('marked'))->toMatchArray([
            'action_label' => 'Marcada como foto de criança ou adolescente atendido',
            'detail' => 'Alterado: declaração',
        ])
        // Excluída: sem link, com o texto que DeleteMedia gravou antes de apagar.
        ->and($byEvent('deleted'))->toMatchArray([
            'user' => $direcao->name,
            'media_uuid' => null,
            'media_alt' => 'Salão do bazar, visto da porta',
        ]);

    // Nenhum valor alterado sai na linha, nem o id sequencial do log.
    expect(json_encode($rows))->not->toContain('"old"')->not->toContain('"id"');
    expect(Media::query()->where('uuid', $second)->exists())->toBeTrue();
});

test('a importação das fotos iniciais aparece sem autor', function (): void {
    $this->seed(ContentPagesSeeder::class);
    $this->artisan('midia:importar-fotos-iniciais')->assertSuccessful();

    $row = $this->actingAs(userWithRole(Role::SuperAdmin->value))
        ->getJson('/api/v1/audit-logs/media?event=imported&per_page=1')
        ->json('data.0');

    expect($row['user'])->toBeNull()
        ->and($row['action_label'])->toBe('Foto inicial importada')
        ->and($row['detail'])->toStartWith('Catálogo inicial: ')
        ->and($row['media_uuid'])->not->toBeNull();
});

test('filtra por acontecimento, e a marcação antiga (updated) entra no filtro de marcação', function (): void {
    $media = Media::factory()->create(['alt' => 'Antiga']);
    activity('media')->performedOn($media)->event('updated')
        ->withProperties(['old' => ['depicts_assisted_minor' => false], 'attributes' => ['depicts_assisted_minor' => true]])
        ->log('Dados da imagem alterados');
    activity('media')->performedOn($media)->event('updated')
        ->withProperties(['old' => ['alt' => 'x'], 'attributes' => ['alt' => 'Antiga']])
        ->log('Dados da imagem alterados');

    $superAdmin = userWithRole(Role::SuperAdmin->value);
    $marked = $this->actingAs($superAdmin)->getJson('/api/v1/audit-logs/media?event=marked')->json('data');

    expect($marked)->toHaveCount(1)
        ->and($marked[0]['event'])->toBe('marked')
        ->and($marked[0]['action_label'])->toBe('Marcada como foto de criança ou adolescente atendido');

    $this->actingAs($superAdmin)->getJson('/api/v1/audit-logs/media?event=qualquer')->assertUnprocessable()->assertJsonValidationErrors(['event']);
});

test('a aba de formulários continua só com formulários', function (): void {
    Media::factory()->create();
    activity('media')->event('uploaded')->log('x');

    expect(Activity::query()->where('log_name', 'media')->count())->toBe(1)
        ->and($this->actingAs(userWithRole(Role::SuperAdmin->value))->getJson('/api/v1/audit-logs')->json('data'))->toBe([]);
});
