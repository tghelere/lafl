<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Enums\TransparencyDocumentType;
use App\Models\TransparencyDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    Storage::fake('local');
});

test('não autenticado recebe 401 ao listar documentos', function (): void {
    $this->getJson('/api/v1/transparency-documents')->assertUnauthorized();
});

test('listagem administrativa filtra por ano', function (): void {
    $user = userWithRole(Role::Direcao->value);
    TransparencyDocument::factory()->create(['title' => '2023', 'year' => 2023]);
    TransparencyDocument::factory()->create(['title' => '2024', 'year' => 2024]);

    $response = $this->actingAs($user)->getJson('/api/v1/transparency-documents?year=2024');

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.title'))->toBe('2024');
});

test('listagem administrativa filtra por tipo', function (): void {
    $user = userWithRole(Role::Direcao->value);
    TransparencyDocument::factory()->create(['title' => 'Balanço', 'type' => TransparencyDocumentType::Balance]);
    TransparencyDocument::factory()->create(['title' => 'Estatuto', 'type' => TransparencyDocumentType::Bylaws]);

    $response = $this->actingAs($user)->getJson('/api/v1/transparency-documents?type=bylaws');

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.title'))->toBe('Estatuto');
});

test('listagem administrativa combina filtro por ano e tipo', function (): void {
    $user = userWithRole(Role::Direcao->value);
    TransparencyDocument::factory()->create(['title' => 'Balanço 2024', 'year' => 2024, 'type' => TransparencyDocumentType::Balance]);
    TransparencyDocument::factory()->create(['title' => 'Estatuto 2024', 'year' => 2024, 'type' => TransparencyDocumentType::Bylaws]);
    TransparencyDocument::factory()->create(['title' => 'Balanço 2023', 'year' => 2023, 'type' => TransparencyDocumentType::Balance]);

    $response = $this->actingAs($user)->getJson('/api/v1/transparency-documents?year=2024&type=balance');

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.title'))->toBe('Balanço 2024');
});

test('tipo desconhecido no filtro administrativo é ignorado, não gera erro', function (): void {
    $user = userWithRole(Role::Direcao->value);
    TransparencyDocument::factory()->create();

    $this->actingAs($user)->getJson('/api/v1/transparency-documents?type=nao-existe')
        ->assertOk();
});

test('paginação administrativa respeita o filtro aplicado', function (): void {
    $user = userWithRole(Role::Direcao->value);
    TransparencyDocument::factory()->count(12)->create(['year' => 2020]);
    TransparencyDocument::factory()->count(3)->create(['year' => 2024]);

    $firstPage = $this->actingAs($user)->getJson('/api/v1/transparency-documents?year=2024&per_page=2');

    $firstPage->assertOk();
    expect($firstPage->json('data'))->toHaveCount(2)
        ->and($firstPage->json('meta.total'))->toBe(3)
        ->and($firstPage->json('meta.last_page'))->toBe(2)
        ->and(collect($firstPage->json('data'))->pluck('year')->unique()->all())->toBe([2024]);

    $secondPage = $this->actingAs($user)->getJson('/api/v1/transparency-documents?year=2024&per_page=2&page=2');

    expect($secondPage->json('data'))->toHaveCount(1)
        ->and($secondPage->json('data.0.year'))->toBe(2024);
});

test('direcao pode criar e publicar documento de transparência', function (): void {
    $user = userWithRole(Role::Direcao->value);

    $response = $this->actingAs($user)->post('/api/v1/transparency-documents', [
        'title' => 'Balanço patrimonial 2024',
        'year' => 2024,
        'type' => TransparencyDocumentType::Balance->value,
        'file' => UploadedFile::fake()->create('balanco-2024.pdf', 100, 'application/pdf'),
        'published' => true,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Balanço patrimonial 2024')
        ->assertJsonPath('data.type', 'balance')
        ->assertJsonMissingPath('data.file_path');

    expect($response->json('data.published_at'))->not->toBeNull();

    $document = TransparencyDocument::query()->firstOrFail();
    Storage::disk('local')->assertExists($document->file_path);
});

test('criar documento sem arquivo é rejeitado', function (): void {
    $user = userWithRole(Role::Direcao->value);

    $this->actingAs($user)->postJson('/api/v1/transparency-documents', [
        'title' => 'Sem arquivo',
        'year' => 2024,
        'type' => TransparencyDocumentType::Balance->value,
    ])->assertStatus(422)->assertJsonValidationErrors('file');
});

test('comunicacao não administra documentos de transparência', function (): void {
    $user = userWithRole(Role::Comunicacao->value);
    $document = TransparencyDocument::factory()->create();

    $this->actingAs($user)->getJson('/api/v1/transparency-documents')->assertForbidden();
    $this->actingAs($user)->getJson("/api/v1/transparency-documents/{$document->uuid}")->assertForbidden();
    $this->actingAs($user)->post('/api/v1/transparency-documents', [
        'title' => 'X',
        'year' => 2024,
        'type' => TransparencyDocumentType::Balance->value,
        'file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
    ])->assertForbidden();
});

test('atendimento não administra documentos de transparência', function (): void {
    $user = userWithRole(Role::Atendimento->value);
    $document = TransparencyDocument::factory()->create();

    $this->actingAs($user)->getJson('/api/v1/transparency-documents')->assertForbidden();
    $this->actingAs($user)->getJson("/api/v1/transparency-documents/{$document->uuid}")->assertForbidden();
});

test('bazar não administra documentos de transparência', function (): void {
    $user = userWithRole(Role::Bazar->value);
    $document = TransparencyDocument::factory()->create();

    $this->actingAs($user)->getJson('/api/v1/transparency-documents')->assertForbidden();
    $this->actingAs($user)->getJson("/api/v1/transparency-documents/{$document->uuid}")->assertForbidden();
});

test('super_admin tem acesso total independente da policy', function (): void {
    $user = userWithRole(Role::SuperAdmin->value);

    $this->actingAs($user)->post('/api/v1/transparency-documents', [
        'title' => 'Balanço patrimonial 2024',
        'year' => 2024,
        'type' => TransparencyDocumentType::Balance->value,
        'file' => UploadedFile::fake()->create('balanco-2024.pdf', 100, 'application/pdf'),
    ])->assertCreated();
});

test('atualizar documento troca metadado sem exigir novo arquivo', function (): void {
    $user = userWithRole(Role::Direcao->value);
    $document = TransparencyDocument::factory()->create(['title' => 'Antigo']);

    $this->actingAs($user)->put("/api/v1/transparency-documents/{$document->uuid}", [
        'title' => 'Novo título',
        'year' => $document->year,
        'type' => $document->type->value,
    ])->assertOk()->assertJsonPath('data.title', 'Novo título');

    expect($document->fresh()->file_path)->toBe($document->file_path);
});

test('atualizar metadado de documento publicado não despublica por efeito colateral', function (): void {
    $user = userWithRole(Role::Direcao->value);
    $document = TransparencyDocument::factory()->published()->create(['title' => 'Antigo']);
    $publishedAt = $document->published_at;

    $response = $this->actingAs($user)->put("/api/v1/transparency-documents/{$document->uuid}", [
        'title' => 'Novo título',
        'year' => $document->year,
        'type' => $document->type->value,
    ])->assertOk();

    expect($response->json('data.published_at'))->not->toBeNull();
    expect($document->fresh()->published_at)->not->toBeNull()
        ->and($document->fresh()->published_at->equalTo($publishedAt))->toBeTrue();
});

test('despublicar via atualização exige published explícito como false', function (): void {
    $user = userWithRole(Role::Direcao->value);
    $document = TransparencyDocument::factory()->published()->create();

    $response = $this->actingAs($user)->put("/api/v1/transparency-documents/{$document->uuid}", [
        'title' => $document->title,
        'year' => $document->year,
        'type' => $document->type->value,
        'published' => false,
    ])->assertOk();

    expect($response->json('data.published_at'))->toBeNull();
    expect($document->fresh()->published_at)->toBeNull();
});

test('publicar via atualização com published true', function (): void {
    $user = userWithRole(Role::Direcao->value);
    $document = TransparencyDocument::factory()->create();

    $response = $this->actingAs($user)->put("/api/v1/transparency-documents/{$document->uuid}", [
        'title' => $document->title,
        'year' => $document->year,
        'type' => $document->type->value,
        'published' => true,
    ])->assertOk();

    expect($response->json('data.published_at'))->not->toBeNull();
    expect($document->fresh()->published_at)->not->toBeNull();
});

test('excluir documento é auditado e some das listagens', function (): void {
    $user = userWithRole(Role::Direcao->value);
    $document = TransparencyDocument::factory()->create();

    $this->actingAs($user)->deleteJson("/api/v1/transparency-documents/{$document->uuid}")->assertNoContent();

    expect(TransparencyDocument::find($document->id))->toBeNull()
        ->and($document->fresh()->deleted_at)->not->toBeNull()
        ->and(Activity::where('event', 'deleted')->where('subject_id', $document->id)->where('subject_type', TransparencyDocument::class)->exists())->toBeTrue();
});

test('o slug do documento nasce do título, na criação', function (): void {
    $user = userWithRole(Role::Direcao->value);

    $this->actingAs($user)->post('/api/v1/transparency-documents', [
        'title' => 'Balanço patrimonial 2024',
        'year' => 2024,
        'type' => TransparencyDocumentType::Balance->value,
        'file' => UploadedFile::fake()->create('balanco-2024.pdf', 100, 'application/pdf'),
    ])->assertCreated();

    expect(TransparencyDocument::query()->firstOrFail()->slug)->toBe('balanco-patrimonial-2024');
});

test('renomear o título NÃO muda o slug — a URL já indexada continua valendo', function (): void {
    $user = userWithRole(Role::Direcao->value);
    $document = TransparencyDocument::factory()->published()->create([
        'title' => 'Balanço patrimonial 2024',
        'slug' => 'balanco-patrimonial-2024',
    ]);

    $this->actingAs($user)->put("/api/v1/transparency-documents/{$document->uuid}", [
        'title' => 'Balanço patrimonial do exercício de 2024',
        'year' => $document->year,
        'type' => $document->type->value,
    ])->assertOk();

    expect($document->fresh())
        ->title->toBe('Balanço patrimonial do exercício de 2024')
        ->slug->toBe('balanco-patrimonial-2024');
});

test('dois documentos com o mesmo título recebem slugs distintos', function (): void {
    $user = userWithRole(Role::Direcao->value);

    foreach ([1, 2] as $_) {
        $this->actingAs($user)->post('/api/v1/transparency-documents', [
            'title' => 'Ata de assembleia',
            'year' => 2024,
            'type' => TransparencyDocumentType::Minutes->value,
            'file' => UploadedFile::fake()->create('ata.pdf', 10, 'application/pdf'),
        ])->assertCreated();
    }

    expect(TransparencyDocument::query()->orderBy('id')->pluck('slug')->all())
        ->toBe(['ata-de-assembleia', 'ata-de-assembleia-2']);
});

test('o slug ignora documento excluído por engano — soft delete continua ocupando o endereço', function (): void {
    $user = userWithRole(Role::Direcao->value);
    TransparencyDocument::factory()->create(['title' => 'Edital 2025', 'slug' => 'edital-2025'])->delete();

    $this->actingAs($user)->post('/api/v1/transparency-documents', [
        'title' => 'Edital 2025',
        'year' => 2025,
        'type' => TransparencyDocumentType::Notice->value,
        'file' => UploadedFile::fake()->create('edital.pdf', 10, 'application/pdf'),
    ])->assertCreated();

    expect(TransparencyDocument::query()->latest('id')->firstOrFail()->slug)->toBe('edital-2025-2');
});
