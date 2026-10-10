<?php

declare(strict_types=1);

use App\Actions\Media\ImportInitialPhotos;
use App\Enums\PageImageRole;
use App\Models\Media;
use App\Models\Page;
use App\Models\PageImage;
use App\Support\Media\InitialPhotos;
use Database\Seeders\ContentPagesSeeder;
use Illuminate\Support\Facades\Storage;

/**
 * A migração que leva o lote de fotos do bazar (9 de outubro de 2026) a produção. Roda a
 * migração de verdade sobre um banco que já tem as fotos originais e alterações da equipe.
 */
beforeEach(function (): void {
    Storage::fake('local');
});

function batchMigration(): object
{
    return require database_path('migrations/2026_10_10_120000_add_bazar_photos_batch_two.php');
}

/**
 * @return list<string>
 */
function batchKeys(): array
{
    return array_keys(InitialPhotos::additions());
}

/**
 * @return list<array{string, int}> origem e posição de cada foto da galeria do bazar
 */
function bazarGallery(): array
{
    $page = Page::query()->where('slug', 'bazar')->firstOrFail();

    return array_values($page->images()->with('media')->orderBy('position')->get()
        ->filter(fn (PageImage $image): bool => $image->role === PageImageRole::Gallery)
        ->map(fn (PageImage $image): array => [(string) $image->media->origin_key, $image->position])
        ->all());
}

test('as 7 fotos do lote existem como arquivo, com alt, no bazar e sem criança atendida', function (): void {
    expect(batchKeys())->toHaveCount(7);

    foreach (InitialPhotos::additions() as $key => $photo) {
        expect(is_file(InitialPhotos::path($key)))->toBeTrue()
            ->and(mb_strlen($photo['alt']))->toBeGreaterThan(30)
            ->and($photo['places'])->toBe([['page' => 'bazar', 'role' => PageImageRole::Gallery]]);
    }

    // Foto de lote nunca teve endereço antigo: não entra na lista que gera redirecionamentos.
    expect(array_intersect(batchKeys(), array_keys(InitialPhotos::all())))->toBe([]);
});

test('acrescenta no fim da galeria sem tocar no que já está lá, e rodar de novo não duplica', function (): void {
    $this->seed(ContentPagesSeeder::class);
    $originals = array_keys(InitialPhotos::all());
    app(ImportInitialPhotos::class)->handle($originals);

    // A equipe, pelo painel: corrige o texto de uma foto, tira outra do bazar e exclui uma da biblioteca.
    $placa = Media::query()->where('origin_key', 'bazar-placa')->firstOrFail();
    $placa->alt = 'Texto corrigido pela equipe';
    $placa->save();
    PageImage::query()->where('media_id', Media::query()->where('origin_key', 'bazar-moveis')->value('id'))->delete();
    Media::query()->where('origin_key', 'fachada-antes')->delete();

    $before = bazarGallery();
    expect($before)->toBe([['bazar-placa', 0], ['bazar-entrada', 1], ['bazar-salao', 3]]);

    batchMigration()->up();

    $after = bazarGallery();

    // O que já estava continua igual e na mesma posição; as 7 vêm depois, em ordem.
    expect(array_slice($after, 0, 3))->toBe($before)
        ->and(array_column(array_slice($after, 3), 0))->toBe(batchKeys())
        ->and(array_column(array_slice($after, 3), 1))->toBe([4, 5, 6, 7, 8, 9, 10]);

    // Nada que a equipe fez foi desfeito: o texto, a foto tirada do bazar e a excluída.
    expect($placa->fresh()?->alt)->toBe('Texto corrigido pela equipe')
        ->and(Media::query()->where('origin_key', 'bazar-moveis')->exists())->toBeTrue()
        ->and(Media::query()->where('origin_key', 'fachada-antes')->exists())->toBeFalse()
        ->and(Media::query()->whereIn('origin_key', batchKeys())->where('depicts_assisted_minor', true)->count())->toBe(0);

    $total = Media::query()->count();

    batchMigration()->up();

    expect(Media::query()->count())->toBe($total)
        ->and(bazarGallery())->toBe($after);
});

test('sem a página do bazar não grava nada e não falha', function (): void {
    batchMigration()->up();

    expect(Media::query()->count())->toBe(0);
});

test('é reversível: down tira só o lote, com os arquivos', function (): void {
    $this->seed(ContentPagesSeeder::class);
    app(ImportInitialPhotos::class)->handle(['bazar-placa']);
    batchMigration()->up();

    $one = Media::query()->where('origin_key', batchKeys()[0])->firstOrFail();
    expect(Storage::disk('local')->directoryExists('media/'.$one->uuid))->toBeTrue();

    batchMigration()->down();

    expect(Media::query()->whereIn('origin_key', batchKeys())->count())->toBe(0)
        ->and(Storage::disk('local')->directoryExists('media/'.$one->uuid))->toBeFalse()
        ->and(bazarGallery())->toBe([['bazar-placa', 0]]);
});
