<?php

declare(strict_types=1);

use App\Actions\Content\PageImages\PlaceImageOnPage;
use App\Actions\Media\Data\MediaDetailsData;
use App\Actions\Media\StoreMedia;
use App\Enums\PageImageRole;
use App\Models\Media;
use App\Models\Page;
use App\Models\PageImage;
use App\Support\Cache\PublicPageCache;
use App\Support\Media\MediaPaths;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Images;

beforeEach(function (): void {
    Storage::fake('local');
    $this->packagesDir = sys_get_temp_dir().'/laf-pacote-midia-'.bin2hex(random_bytes(6));
});

afterEach(function (): void {
    File::deleteDirectory($this->packagesDir);
});

/**
 * Origem: duas páginas com capa e galeria, uma foto citada no texto e uma foto marcada como de
 * assistido fora de qualquer página. Exporta, e depois deixa o banco como o de um ambiente
 * cujas páginas têm os MESMOS slugs mas outro uuid, outro id e outro texto, e nenhuma imagem.
 *
 * @return array{package: string, photo: string, garden: string, flagged: string}
 */
function exportMediaAndPrepareTarget(): array
{
    $store = app(StoreMedia::class);
    $place = app(PlaceImageOnPage::class);

    $photo = $store->handle(Images::jpeg(1000, 600)->getRealPath(), new MediaDetailsData('Fachada', 'A sede', false), null);
    $garden = $store->handle(Images::jpeg(800, 600)->getRealPath(), new MediaDetailsData('Horta', null, false), null);
    $garden->forceFill(['origin_key' => 'horta-kids'])->save();
    $flagged = $store->handle(Images::jpeg(500, 400)->getRealPath(), new MediaDetailsData('Pátio', null, false), null);
    $flagged->forceFill(['depicts_assisted_minor' => true])->save();

    $about = Page::factory()->published()->create([
        'slug' => 'quem-somos',
        'content' => '<p>a</p><figure><img src="/midia/'.$photo->uuid.'" alt="Fachada" /></figure>',
    ]);
    $place->handle($about, $photo, PageImageRole::Cover);
    $place->handle($about, $garden, PageImageRole::Gallery);
    $place->handle($about, $photo, PageImageRole::Gallery);

    $school = Page::factory()->published()->create(['slug' => 'educacao-infantil']);
    $place->handle($school, $garden, PageImageRole::Gallery);

    Page::factory()->published()->create(['slug' => 'so-na-origem']);
    $place->handle(Page::query()->where('slug', 'so-na-origem')->firstOrFail(), $garden, PageImageRole::Cover);

    test()->artisan('conteudo:exportar', ['destino' => test()->packagesDir])->assertSuccessful();
    $package = glob(test()->packagesDir.'/conteudo-*')[0];

    $uuids = ['package' => $package, 'photo' => $photo->uuid, 'garden' => $garden->uuid, 'flagged' => $flagged->uuid];

    Page::query()->withTrashed()->forceDelete();
    Media::query()->delete();
    Storage::fake('local');

    Page::factory()->published()->create([
        'slug' => 'quem-somos',
        'content' => '<p>Texto de homologação</p><figure><img src="/midia/'.$photo->uuid.'" alt="Fachada" /></figure>',
    ]);
    Page::factory()->published()->create(['slug' => 'educacao-infantil', 'content' => '<p>Outro texto</p>']);

    return $uuids;
}

/** @return array<string, list<array{0: string, 1: string, 2: int}>> */
function pageImagesBySlug(): array
{
    return Page::query()->orderBy('slug')->get()->mapWithKeys(fn (Page $p): array => [
        $p->slug => $p->images()->with('media')->get()
            ->map(fn (PageImage $i): array => [$i->media->uuid, $i->role->value, $i->position])->all(),
    ])->all();
}

test('leva imagens e vínculos pelo slug, sem tocar no texto nem no uuid das páginas', function (): void {
    $src = exportMediaAndPrepareTarget();
    $pagesBefore = Page::query()->orderBy('slug')->get(['id', 'uuid', 'slug', 'content', 'updated_at'])->toArray();

    $this->artisan('midia:importar', ['pacote' => $src['package']])->assertSuccessful();

    expect(Page::query()->orderBy('slug')->get(['id', 'uuid', 'slug', 'content', 'updated_at'])->toArray())->toBe($pagesBefore)
        ->and(Media::query()->pluck('uuid')->sort()->values()->all())
        ->toBe(collect([$src['photo'], $src['garden']])->sort()->values()->all())
        ->and(pageImagesBySlug())->toBe([
            'educacao-infantil' => [[$src['garden'], 'gallery', 0]],
            'quem-somos' => [[$src['photo'], 'cover', 0], [$src['garden'], 'gallery', 0], [$src['photo'], 'gallery', 1]],
        ]);

    $photo = Media::query()->where('uuid', $src['photo'])->firstOrFail();
    expect(Storage::disk('local')->exists(MediaPaths::original($photo)))->toBeTrue();
    foreach ($photo->widths as $width) {
        expect(Storage::disk('local')->exists(MediaPaths::derivative($photo, $width)))->toBeTrue();
    }

    $images = $this->getJson('/api/v1/public/pages/quem-somos')->assertOk()->json('data.images');
    expect(array_column($images['gallery'], 'alt'))->toBe(['Horta', 'Fachada'])
        ->and($images['cover']['alt'])->toBe('Fachada');
    $this->get("/api/v1/public/media/{$src['photo']}/640.webp")->assertOk()->assertHeader('Content-Type', 'image/webp');
    expect($this->getJson('/api/v1/public/pages/quem-somos')->json('data.content'))->toContain('Texto de homologação');
});

test('a foto de assistido não viaja, e a página que só existe na origem é listada', function (): void {
    $src = exportMediaAndPrepareTarget();

    $this->artisan('midia:importar', ['pacote' => $src['package']])
        ->expectsOutputToContain('so-na-origem')
        ->assertSuccessful();

    expect(Media::query()->where('uuid', $src['flagged'])->exists())->toBeFalse()
        ->and(Page::query()->where('slug', 'so-na-origem')->exists())->toBeFalse();
});

test('rodar de novo não duplica nem sobrescreve', function (): void {
    $src = exportMediaAndPrepareTarget();

    $this->artisan('midia:importar', ['pacote' => $src['package']])->assertSuccessful();
    $before = pageImagesBySlug();

    $this->artisan('midia:importar', ['pacote' => $src['package']])->assertSuccessful();

    expect(pageImagesBySlug())->toBe($before)
        ->and(Media::query()->count())->toBe(2);
});

test('página que já tem capa ou galeria é mantida inteira', function (): void {
    $src = exportMediaAndPrepareTarget();
    $local = app(StoreMedia::class)->handle(Images::jpeg(600, 400)->getRealPath(), new MediaDetailsData('Local', null, false), null);
    app(PlaceImageOnPage::class)->handle(Page::query()->where('slug', 'educacao-infantil')->firstOrFail(), $local, PageImageRole::Gallery);

    $this->artisan('midia:importar', ['pacote' => $src['package']])->assertSuccessful();

    expect(pageImagesBySlug()['educacao-infantil'])->toBe([[$local->uuid, 'gallery', 0]]);
});

test('--simular não escreve nada', function (): void {
    $src = exportMediaAndPrepareTarget();

    $this->artisan('midia:importar', ['pacote' => $src['package'], '--simular' => true])->assertSuccessful();

    expect(Media::query()->count())->toBe(0)
        ->and(PageImage::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

test('foto inicial que já existe aqui com outro uuid é recusada sem escrever nada', function (): void {
    $src = exportMediaAndPrepareTarget();
    $copy = app(StoreMedia::class)->handle(Images::jpeg(800, 600)->getRealPath(), new MediaDetailsData('Horta', null, false), null);
    $copy->forceFill(['origin_key' => 'horta-kids'])->save();

    $this->artisan('midia:importar', ['pacote' => $src['package']])->assertFailed();

    expect(Media::query()->count())->toBe(1)
        ->and(PageImage::query()->count())->toBe(0);
});

test('o cache público das páginas que ganharam foto é invalidado', function (): void {
    $src = exportMediaAndPrepareTarget();
    Cache::put(PublicPageCache::key('quem-somos'), 'velho', 600);

    $this->artisan('midia:importar', ['pacote' => $src['package']])->assertSuccessful();

    expect(Cache::has(PublicPageCache::key('quem-somos')))->toBeFalse();
});

test('--verificar falha antes de importar, passa depois e pega arquivo que sumiu', function (): void {
    $src = exportMediaAndPrepareTarget();

    $this->artisan('midia:importar', ['pacote' => $src['package'], '--verificar' => true])->assertFailed();

    $this->artisan('midia:importar', ['pacote' => $src['package']])->assertSuccessful();

    // Página com foto na origem e sem par aqui é problema: ela ficaria sem foto.
    $this->artisan('midia:importar', ['pacote' => $src['package'], '--verificar' => true])
        ->expectsOutputToContain('so-na-origem')
        ->assertFailed();

    Page::factory()->published()->create(['slug' => 'so-na-origem']);
    $this->artisan('midia:importar', ['pacote' => $src['package']])->assertSuccessful();
    $this->artisan('midia:importar', ['pacote' => $src['package'], '--verificar' => true])->assertSuccessful();

    $photo = Media::query()->where('uuid', $src['photo'])->firstOrFail();
    Storage::disk('local')->delete(MediaPaths::derivative($photo, $photo->widths[0]));

    $this->artisan('midia:importar', ['pacote' => $src['package'], '--verificar' => true])
        ->expectsOutputToContain('ausente do disco')
        ->assertFailed();
});
