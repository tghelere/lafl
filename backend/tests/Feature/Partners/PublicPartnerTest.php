<?php

declare(strict_types=1);

use App\Models\Media;
use App\Models\Partner;

test('lista só parceiros ativos, na ordem, sem autenticação', function (): void {
    Partner::factory()->withUrl('https://c.example.com')->create(['name' => 'C', 'position' => 3]);
    Partner::factory()->create(['name' => 'A', 'position' => 1]);
    Partner::factory()->inactive()->create(['name' => 'Inativo', 'position' => 2]);
    Partner::factory()->create(['name' => 'Excluído', 'position' => 0])->delete();

    $response = $this->getJson('/api/v1/public/partners');

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('name')->all())->toBe(['A', 'C'])
        ->and($response->json('data.0.url'))->toBeNull()
        ->and($response->json('data.1.url'))->toBe('https://c.example.com');
});

test('empate de ordem se desfaz pela criação', function (): void {
    Partner::factory()->create(['name' => 'Primeiro', 'position' => 1]);
    Partner::factory()->create(['name' => 'Segundo', 'position' => 1]);

    $names = collect($this->getJson('/api/v1/public/partners')->json('data'))->pluck('name')->all();

    expect($names)->toBe(['Primeiro', 'Segundo']);
});

test('expõe só nome, link e logo pública — nada interno', function (): void {
    $partner = Partner::factory()->create(['name' => 'A']);

    $row = $this->getJson('/api/v1/public/partners')->json('data.0');

    expect(array_keys($row))->toBe(['name', 'url', 'logo'])
        ->and($row['logo']['src'])->toStartWith('/midia/'.$partner->media->uuid.'/')
        ->and($row['logo']['src'])->toEndWith('.webp')
        ->and($row['logo']['srcset'])->toContain($partner->media->uuid)
        ->and($row['logo']['width'])->toBe(1280)
        ->and($row['logo']['height'])->toBe(960);
});

test('logo marcada como foto de assistido tira o parceiro da lista', function (): void {
    Partner::factory()->create(['name' => 'Visível']);
    Partner::factory()->for(Media::factory()->depictingAssistedMinor())->create(['name' => 'Oculto']);

    $names = collect($this->getJson('/api/v1/public/partners')->json('data'))->pluck('name')->all();

    expect($names)->toBe(['Visível']);
});

test('sem parceiros, a lista vem vazia e paginada', function (): void {
    $this->getJson('/api/v1/public/partners')
        ->assertOk()
        ->assertJsonPath('data', [])
        ->assertJsonPath('meta.total', 0);
});
