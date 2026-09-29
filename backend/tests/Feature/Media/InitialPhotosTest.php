<?php

declare(strict_types=1);

use App\Enums\PageImageRole;
use App\Models\Media;
use App\Models\Page;
use App\Models\PageImage;
use App\Support\Media\InitialPhotos;
use App\Support\Media\MediaPaths;
use Database\Seeders\ContentPagesSeeder;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

/**
 * `midia:importar-fotos-iniciais` com as 24 fotos de verdade de resources/initial-photos. É
 * o mais lento da suíte de mídia (processa todas), então cada teste importa uma vez só.
 */
beforeEach(function (): void {
    Storage::fake('local');
});

/**
 * @return list<string>
 */
function galleryAlts(string $slug): array
{
    $page = Page::query()->where('slug', $slug)->firstOrFail();

    return array_values($page->images()->with('media')->get()
        ->filter(fn (PageImage $image): bool => $image->role === PageImageRole::Gallery)
        ->map(fn (PageImage $image): string => $image->media->alt)
        ->all());
}

test('todo arquivo do catálogo existe, e toda foto do catálogo tem arquivo', function (): void {
    $files = array_map(
        static fn (string $path): string => basename($path, '.jpg'),
        glob(InitialPhotos::directory().'/*.jpg') ?: [],
    );

    expect($files)->toEqualCanonicalizing(array_keys(InitialPhotos::all()));
});

test('importa as fotos, põe cada uma no lugar e, rodando de novo, não duplica nada', function (): void {
    $this->seed(ContentPagesSeeder::class);

    $this->artisan('midia:importar-fotos-iniciais')
        ->expectsOutputToContain('24 importada(s), 0 já existente(s), 0 sem página.')
        ->assertSuccessful();

    expect(Media::query()->count())->toBe(24)
        ->and(Media::query()->where('depicts_assisted_minor', true)->count())->toBe(0);

    // A galeria na ordem do catálogo, que é a ordem que o site mostrava.
    expect(galleryAlts('bazar'))->toBe([
        'Placa externa do Bazar Beneficente do Lar Anália Franco, com telefone e endereço',
        'Entrada do Bazar Beneficente, com toldo azul e carrinhos de compra ao lado',
        'Setor de móveis do bazar, com sofás, poltronas e utensílios à venda',
        'Salão do bazar com araras de roupas, manequins e chapéus expostos',
    ]);

    // A capa do bazar é a entrada (o cartão de "O que fazemos"), não a primeira da galeria.
    $cover = fn (string $slug): ?string => PageImage::query()
        ->whereHas('page', fn ($page) => $page->where('slug', $slug))
        ->where('role', PageImageRole::Cover)
        ->first()?->media->origin_key;

    expect($cover('bazar'))->toBe('bazar-entrada')
        ->and($cover('educacao-infantil'))->toBe('horta-kids')
        ->and($cover('quem-somos'))->toBe('fachada-sede');

    // As legendas que o site já mostrava vieram junto.
    expect(Media::query()->where('origin_key', 'fachada-antes')->value('caption'))->toBe('Fachada — antes');

    // As sete que nenhuma página mostrava estão só na biblioteca.
    expect(Media::query()->doesntHave('pageImages')->pluck('origin_key')->sort()->values()->all())->toBe([
        'bazar-deposito', 'bazar-leitura', 'fachada-sede-atual', 'horta-kids-vista', 'recanto-da-amizade', 'recanto-vista', 'recepcao',
    ]);

    // As larguras de antes: a fachada tinha 1920 a 400, e a placa, 640 e 400.
    expect(Media::query()->where('origin_key', 'fachada-sede')->firstOrFail()->widths)->toBe([400, 640, 960, 1280, 1920])
        ->and(Media::query()->where('origin_key', 'placa-inauguracao')->firstOrFail()->widths)->toBe([400, 640]);

    $placa = Media::query()->where('origin_key', 'placa-inauguracao')->firstOrFail();
    expect(Storage::disk('local')->exists(MediaPaths::derivative($placa, 640)))->toBeTrue();

    expect(Activity::query()->where('log_name', 'media')->where('event', 'imported')->count())->toBe(24);

    $before = PageImage::query()->count();

    $this->artisan('midia:importar-fotos-iniciais')
        ->expectsOutputToContain('0 importada(s), 24 já existente(s)')
        ->assertSuccessful();

    expect(Media::query()->count())->toBe(24)
        ->and(PageImage::query()->count())->toBe($before);
});

test('não pisa no que a equipe mudou depois', function (): void {
    $this->seed(ContentPagesSeeder::class);
    $this->artisan('midia:importar-fotos-iniciais')->assertSuccessful();

    $placa = Media::query()->where('origin_key', 'bazar-placa')->firstOrFail();
    $placa->alt = 'Texto corrigido pela equipe';
    $placa->save();
    PageImage::query()->where('media_id', $placa->id)->delete();

    $this->artisan('midia:importar-fotos-iniciais')->assertSuccessful();

    expect($placa->fresh()?->alt)->toBe('Texto corrigido pela equipe')
        ->and(PageImage::query()->where('media_id', $placa->id)->exists())->toBeFalse();
});

test('sem a página de destino, a foto não entra, e o comando diz o que fazer', function (): void {
    $this->seed(ContentPagesSeeder::class);
    Page::query()->where('slug', 'bazar')->firstOrFail()->delete();

    $this->artisan('midia:importar-fotos-iniciais')
        ->expectsOutputToContain('a página /bazar não existe')
        ->expectsOutputToContain('conteudo:importar-inicial')
        ->assertFailed();

    // As quatro do bazar ficaram de fora inteiras, e as outras entraram.
    expect(Media::query()->where('origin_key', 'like', 'bazar-%')->pluck('origin_key')->sort()->values()->all())
        ->toBe(['bazar-deposito', 'bazar-leitura'])
        ->and(Media::query()->count())->toBe(20);
});

test('capa já ocupada é mantida', function (): void {
    $this->seed(ContentPagesSeeder::class);
    $page = Page::query()->where('slug', 'quem-somos')->firstOrFail();
    $mine = Media::factory()->create(['alt' => 'Capa escolhida pela equipe']);

    $image = new PageImage;
    $image->page_id = $page->id;
    $image->media_id = $mine->id;
    $image->role = PageImageRole::Cover;
    $image->position = 0;
    $image->save();

    $this->artisan('midia:importar-fotos-iniciais')
        ->expectsOutputToContain('capa de /quem-somos já ocupada')
        ->assertSuccessful();

    expect(PageImage::query()->where('page_id', $page->id)->where('role', PageImageRole::Cover)->firstOrFail()->media_id)->toBe($mine->id)
        ->and(Media::query()->where('origin_key', 'fachada-sede')->exists())->toBeTrue();
});
