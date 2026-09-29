<?php

declare(strict_types=1);

use App\Models\Media;
use Database\Seeders\ContentPagesSeeder;
use Illuminate\Support\Facades\Storage;

/**
 * Os endereços antigos das fotos do site (`/fotos/{secao}/{chave}-{largura}.{webp,jpg}`),
 * que existiam até a sessão 28, respondem 301 para o endereço atual em /midia/.
 *
 * A lista em tests/Fixtures/legacy-photo-paths.txt é a de `git ls-tree` do commit 071b95a
 * (o último com os arquivos em frontend-site/public/fotos/), sem o mapa de /contato, que
 * continua lá. Todos os 144 são conferidos.
 */
beforeEach(function (): void {
    Storage::fake('local');
    config(['forms.site_base_url' => 'https://site.exemplo']);
});

/**
 * @return list<string>
 */
function legacyPhotoPaths(): array
{
    return array_values(array_filter(array_map('trim', file(base_path('tests/Fixtures/legacy-photo-paths.txt')) ?: [])));
}

test('cada um dos 144 endereços antigos leva, com 301, à mesma foto na mesma largura', function (): void {
    $this->seed(ContentPagesSeeder::class);
    $this->artisan('midia:importar-fotos-iniciais')->assertSuccessful();

    $paths = legacyPhotoPaths();
    expect($paths)->toHaveCount(144);

    foreach ($paths as $path) {
        preg_match('#^/fotos/([^/]+)/(.+)-(\d+)\.(webp|jpg)$#', $path, $m);
        [, $section, $key, $width] = $m;
        $media = Media::query()->where('origin_key', $key)->firstOrFail();

        // A importação preservou as larguras, então a largura antiga existe tal e qual.
        expect($media->widths)->toContain((int) $width);

        $this->get("/api/v1/public/legacy-photos/{$section}/{$key}-{$width}.{$m[4]}")
            ->assertStatus(301)
            ->assertRedirect("https://site.exemplo/midia/{$media->uuid}/{$width}.webp");
    }
});

test('endereço que nunca existiu, seção trocada ou foto fora do ar dão 404', function (): void {
    $this->seed(ContentPagesSeeder::class);
    $this->artisan('midia:importar-fotos-iniciais')->assertSuccessful();

    $this->get('/api/v1/public/legacy-photos/bazar/nao-existe-640.webp')->assertNotFound();
    // A placa morava em historia/, não em bazar/.
    $this->get('/api/v1/public/legacy-photos/bazar/placa-inauguracao-640.webp')->assertNotFound();
    $this->get('/api/v1/public/legacy-photos/historia/placa-inauguracao-640.png')->assertNotFound();

    Media::query()->where('origin_key', 'placa-inauguracao')->update(['depicts_assisted_minor' => true]);
    $this->get('/api/v1/public/legacy-photos/historia/placa-inauguracao-640.webp')->assertNotFound();
});

test('foto trocada por uma menor continua respondendo, com a maior largura que houver', function (): void {
    $this->seed(ContentPagesSeeder::class);
    $this->artisan('midia:importar-fotos-iniciais')->assertSuccessful();
    $media = Media::query()->where('origin_key', 'fachada-sede')->firstOrFail();
    $media->widths = [400, 640];
    $media->save();

    $this->get('/api/v1/public/legacy-photos/home/fachada-sede-1920.jpg')
        ->assertRedirect("https://site.exemplo/midia/{$media->uuid}/640.webp");
});
