<?php

declare(strict_types=1);

use App\Enums\TransparencyDocumentType;
use App\Models\TransparencyDocument;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Storage::fake('local');
});

test('endpoint público lista só documentos publicados', function (): void {
    TransparencyDocument::factory()->published()->create(['title' => 'Publicado']);
    TransparencyDocument::factory()->create(['title' => 'Rascunho']);

    $response = $this->getJson('/api/v1/public/transparency-documents');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.title'))->toBe('Publicado');
});

test('endpoint público filtra por ano', function (): void {
    TransparencyDocument::factory()->published()->create(['title' => '2023', 'year' => 2023]);
    TransparencyDocument::factory()->published()->create(['title' => '2024', 'year' => 2024]);

    $response = $this->getJson('/api/v1/public/transparency-documents?year=2024');

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.title'))->toBe('2024');
});

test('endpoint público filtra por tipo', function (): void {
    TransparencyDocument::factory()->published()->create(['title' => 'Balanço', 'type' => TransparencyDocumentType::Balance]);
    TransparencyDocument::factory()->published()->create(['title' => 'Estatuto', 'type' => TransparencyDocumentType::Bylaws]);

    $response = $this->getJson('/api/v1/public/transparency-documents?type=bylaws');

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.title'))->toBe('Estatuto');
});

test('tipo desconhecido no filtro é ignorado, não gera erro', function (): void {
    TransparencyDocument::factory()->published()->create();

    $this->getJson('/api/v1/public/transparency-documents?type=nao-existe')
        ->assertOk();
});

test('resource público nunca expõe file_path', function (): void {
    TransparencyDocument::factory()->published()->create();

    $this->getJson('/api/v1/public/transparency-documents')
        ->assertOk()
        ->assertJsonMissingPath('data.0.file_path');
});

test('download soma a contagem e serve o arquivo', function (): void {
    Storage::disk('local')->put('transparency-documents/exemplo.pdf', '%PDF-1.4 conteudo de teste');

    $document = TransparencyDocument::factory()->published()->create([
        'file_path' => 'transparency-documents/exemplo.pdf',
        'download_count' => 0,
    ]);

    $response = $this->get("/api/v1/public/transparency-documents/{$document->uuid}/download");

    $response->assertOk();
    expect($document->fresh()->download_count)->toBe(1);
});

test('download de documento não publicado devolve 404', function (): void {
    $document = TransparencyDocument::factory()->create();

    $this->get("/api/v1/public/transparency-documents/{$document->uuid}/download")
        ->assertNotFound();
});

test('download de uuid inexistente devolve 404', function (): void {
    $this->get('/api/v1/public/transparency-documents/'.Str::uuid().'/download')
        ->assertNotFound();
});
