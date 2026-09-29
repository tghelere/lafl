<?php

declare(strict_types=1);

use App\Actions\Content\PageImages\MoveGalleryImage;
use App\Actions\Content\PageImages\PlaceImageOnPage;
use App\Actions\Media\Data\MediaDetailsData;
use App\Actions\Media\StoreMedia;
use App\Enums\PageImageRole;
use App\Models\Media;
use App\Models\Page;
use App\Models\PageImage;
use App\Models\PageSlugHistory;
use App\Models\TransparencyDocument;
use App\Support\Cache\PublicPageCache;
use App\Support\Media\MediaPaths;
use Database\Seeders\ContentPagesSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Images;

beforeEach(function (): void {
    Storage::fake('local');
    $this->packagesDir = sys_get_temp_dir().'/laf-pacote-'.bin2hex(random_bytes(6));
});

afterEach(function (): void {
    File::deleteDirectory($this->packagesDir);
});

/**
 * Cria o conteúdo "editado pelo painel": as páginas do seeder (publicadas e rascunhos), uma
 * arquivada, uma com histórico de slug, e documentos publicado e não publicado com PDF real
 * no disco. Devolve o que precisa ser comparado depois, sem os ids sequenciais.
 */
function seedEditedContent(): void
{
    test()->seed(ContentPagesSeeder::class);

    Page::factory()->create(['slug' => 'rascunho-da-diretoria', 'title' => 'Rascunho', 'content' => '<p>Ainda não vai ao ar.</p>']);
    Page::factory()->archived()->create(['slug' => 'campanha-antiga', 'content' => '<p>Encerrada.</p>']);

    $renamed = Page::factory()->published()->create(['slug' => 'nome-novo', 'content' => '<h2>Olá</h2><p>Ação — ç</p>']);
    PageSlugHistory::query()->create(['page_id' => $renamed->id, 'slug' => 'nome-velho']);

    // Uma imagem de verdade (original + derivadas no disco), usada numa página publicada, e
    // uma marcada como de assistido — que não pode viajar.
    $store = app(StoreMedia::class);
    $photo = $store->handle(Images::jpeg(1000, 600)->getRealPath(), new MediaDetailsData('Fachada', 'A sede', false), null);
    $flagged = $store->handle(Images::jpeg(500, 400)->getRealPath(), new MediaDetailsData('Pátio', null, false), null);
    $flagged->forceFill(['depicts_assisted_minor' => true])->save();
    $withPhoto = Page::factory()->published()->create([
        'slug' => 'com-foto',
        'content' => '<p>a</p><figure><img src="/midia/'.$photo->uuid.'" alt="Fachada" /><figcaption>A sede</figcaption></figure>',
    ]);

    // Capa e galeria (formato 3), com uma foto vinda do catálogo inicial (origin_key). A ordem
    // da galeria é o contrário da ordem de criação, para o teste pegar quem ordenasse por id.
    $garden = $store->handle(Images::jpeg(800, 600)->getRealPath(), new MediaDetailsData('Horta', 'A horta', false), null);
    $garden->forceFill(['origin_key' => 'horta-kids'])->save();
    $place = app(PlaceImageOnPage::class);
    $place->handle($withPhoto, $garden, PageImageRole::Gallery);
    $place->handle($withPhoto, $photo, PageImageRole::Gallery);
    $place->handle($withPhoto, $garden, PageImageRole::Cover);

    foreach ([['prestacao-2025', true], ['ata-em-revisao', false]] as [$slug, $published]) {
        $path = "transparency-documents/{$slug}.pdf";
        Storage::disk('local')->put($path, "%PDF-1.4 conteudo de {$slug} ".random_bytes(64));

        TransparencyDocument::factory()->create([
            'title' => "Documento {$slug}",
            'slug' => $slug,
            'file_path' => $path,
            'file_size' => Storage::disk('local')->size($path),
            'published_at' => $published ? now()->subDays(3) : null,
            'download_count' => 42,
        ]);
    }
}

/** @return array<string, mixed> */
function contentSnapshot(): array
{
    return [
        'pages' => Page::query()->orderBy('slug')->get()->map(fn (Page $p): array => [
            ...$p->only(['uuid', 'slug', 'title', 'content', 'meta_title', 'meta_description']),
            'status' => $p->status->value,
            'published_at' => $p->published_at?->toIso8601String(),
            'created_at' => $p->created_at?->toIso8601String(),
            'updated_at' => $p->updated_at?->toIso8601String(),
            'history' => $p->slugHistory()->pluck('slug')->sort()->values()->all(),
            'images' => $p->images()->with('media')->get()
                ->map(fn (PageImage $i): array => [$i->media->uuid, $i->role->value, $i->position])->all(),
        ])->all(),
        'documents' => TransparencyDocument::query()->orderBy('slug')->get()->map(fn (TransparencyDocument $d): array => [
            ...$d->only(['uuid', 'slug', 'title', 'year', 'file_path', 'file_size']),
            'type' => $d->type->value,
            'published_at' => $d->published_at?->toIso8601String(),
            'created_at' => $d->created_at?->toIso8601String(),
            'updated_at' => $d->updated_at?->toIso8601String(),
            'sha256' => hash('sha256', Storage::disk('local')->get($d->file_path)),
        ])->all(),
        'media' => Media::query()->where('depicts_assisted_minor', false)->orderBy('uuid')->get()->map(fn (Media $m): array => [
            ...$m->only(['uuid', 'origin_key', 'alt', 'caption', 'version', 'mime', 'extension', 'size', 'width', 'height', 'widths', 'sha256']),
            'created_at' => $m->created_at?->toIso8601String(),
            'updated_at' => $m->updated_at?->toIso8601String(),
            'files' => collect(Storage::disk('local')->allFiles(MediaPaths::root($m)))
                ->sort()->values()
                ->mapWithKeys(fn (string $path): array => [$path => hash('sha256', (string) Storage::disk('local')->get($path))])
                ->all(),
        ])->all(),
    ];
}

function wipeContent(): void
{
    Page::query()->withTrashed()->forceDelete();
    TransparencyDocument::query()->withTrashed()->forceDelete();
    Media::query()->delete();
    Storage::fake('local');
}

function exportPackage(): string
{
    test()->artisan('conteudo:exportar', ['destino' => test()->packagesDir])->assertSuccessful();

    return glob(test()->packagesDir.'/conteudo-*')[0];
}

test('exportar de um banco semeado e importar num banco vazio entrega tudo idêntico', function (): void {
    seedEditedContent();
    $before = contentSnapshot();

    expect(collect($before['pages'])->pluck('status')->unique()->sort()->values()->all())
        ->toBe(['archived', 'draft', 'published']);

    $package = exportPackage();
    wipeContent();
    expect(Page::query()->withTrashed()->count())->toBe(0)
        ->and(TransparencyDocument::query()->withTrashed()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);

    $this->artisan('conteudo:importar', ['pacote' => $package])->assertSuccessful();

    expect(contentSnapshot())->toEqual($before);
});

test('o pacote não leva usuário, formulário, auditoria, lixeira nem contador de download', function (): void {
    seedEditedContent();
    Page::factory()->published()->create(['slug' => 'apagada'])->delete();

    $package = exportPackage();

    expect(scandir($package))->toEqualCanonicalizing(['.', '..', 'manifest.json', 'pages.json', 'documents.json', 'media.json', 'files']);

    // A imagem marcada como de assistido não viaja — nem registro, nem arquivo.
    $media = collect(json_decode(file_get_contents($package.'/media.json'), true));
    expect($media->pluck('alt')->all())->toBe(['Fachada', 'Horta'])
        ->and(json_encode($media->all()))->not->toContain('depicts_assisted_minor');
    $flagged = Media::query()->where('depicts_assisted_minor', true)->firstOrFail();
    expect(is_dir($package.'/files/media/'.$flagged->uuid))->toBeFalse();

    $slugs = collect(json_decode(file_get_contents($package.'/pages.json'), true))->pluck('slug');
    expect($slugs)->not->toContain('apagada');

    $raw = file_get_contents($package.'/documents.json');
    expect($raw)->not->toContain('download_count');

    wipeContent();
    $this->artisan('conteudo:importar', ['pacote' => $package])->assertSuccessful();
    expect(TransparencyDocument::query()->pluck('download_count')->unique()->all())->toBe([0]);
});

test('sem --substituir, o que já existe não é tocado', function (): void {
    seedEditedContent();
    $package = exportPackage();

    Page::query()->where('slug', 'nome-novo')->update(['title' => 'Editado depois do pacote']);
    $before = contentSnapshot();

    $this->artisan('conteudo:importar', ['pacote' => $package])
        ->expectsOutputToContain('já existia')
        ->assertSuccessful();

    expect(contentSnapshot())->toEqual($before)
        ->and(Page::query()->where('slug', 'nome-novo')->value('title'))->toBe('Editado depois do pacote');
});

test('--substituir sobrescreve só com confirmação, e --force a dispensa', function (): void {
    seedEditedContent();
    $package = exportPackage();
    $original = contentSnapshot();

    Page::query()->where('slug', 'nome-novo')->update(['title' => 'Editado depois do pacote']);
    Page::factory()->published()->create(['slug' => 'so-existe-aqui']);

    $this->artisan('conteudo:importar', ['pacote' => $package, '--substituir' => true])
        ->expectsConfirmation('--substituir vai SOBRESCREVER as páginas e os documentos que já existem com o mesmo slug. Continuar?', 'no')
        ->assertFailed();

    expect(Page::query()->where('slug', 'nome-novo')->value('title'))->toBe('Editado depois do pacote');

    $this->artisan('conteudo:importar', ['pacote' => $package, '--substituir' => true, '--force' => true])->assertSuccessful();

    expect(Page::query()->where('slug', 'nome-novo')->value('title'))->not->toBe('Editado depois do pacote')
        // O que o pacote não traz nunca é apagado, nem com o flag.
        ->and(Page::query()->where('slug', 'so-existe-aqui')->exists())->toBeTrue();

    $restored = contentSnapshot();
    $restored['pages'] = array_values(array_filter($restored['pages'], fn (array $p): bool => $p['slug'] !== 'so-existe-aqui'));
    expect($restored)->toEqual($original);
});

test('--simular não escreve nada', function (): void {
    seedEditedContent();
    $package = exportPackage();
    wipeContent();

    $this->artisan('conteudo:importar', ['pacote' => $package, '--simular' => true])
        ->expectsOutputToContain('Simulação')
        ->assertSuccessful();

    expect(Page::query()->count())->toBe(0)
        ->and(TransparencyDocument::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

test('pacote adulterado é recusado antes de escrever qualquer coisa', function (): void {
    seedEditedContent();
    $package = exportPackage();
    wipeContent();

    $pdf = glob($package.'/files/transparency-documents/*.pdf')[0];
    file_put_contents($pdf, 'trocado');

    $this->artisan('conteudo:importar', ['pacote' => $package])
        ->expectsOutputToContain('não confere com o SHA-256')
        ->assertFailed();

    expect(Page::query()->count())->toBe(0)
        ->and(TransparencyDocument::query()->count())->toBe(0);
});

test('pacote com json alterado ou de outra versão é recusado', function (): void {
    seedEditedContent();
    $package = exportPackage();
    wipeContent();

    $manifest = json_decode(file_get_contents($package.'/manifest.json'), true);
    $manifest['format_version'] = 99;
    file_put_contents($package.'/manifest.json', json_encode($manifest));
    $this->artisan('conteudo:importar', ['pacote' => $package])->expectsOutputToContain('Versão de formato')->assertFailed();

    // O formato anterior também é recusado: um pacote 2 não traz capa nem galeria, e as páginas
    // de seção chegariam sem as fotos.
    $manifest['format_version'] = 2;
    file_put_contents($package.'/manifest.json', json_encode($manifest));
    $this->artisan('conteudo:importar', ['pacote' => $package])->expectsOutputToContain('Versão de formato')->assertFailed();

    $manifest['format_version'] = 3;
    file_put_contents($package.'/manifest.json', json_encode($manifest));
    file_put_contents($package.'/pages.json', '[]');
    $this->artisan('conteudo:importar', ['pacote' => $package])->expectsOutputToContain('não confere com o manifesto')->assertFailed();

    expect(Page::query()->count())->toBe(0);
});

test('exportar recusa quando o PDF de um documento sumiu do disco', function (): void {
    seedEditedContent();
    Storage::disk('local')->delete('transparency-documents/prestacao-2025.pdf');

    $this->artisan('conteudo:exportar', ['destino' => $this->packagesDir])
        ->expectsOutputToContain('não existe no disco')
        ->assertFailed();
});

test('o HTML da página passa pelo sanitizador na importação', function (): void {
    seedEditedContent();
    $package = exportPackage();
    wipeContent();

    $pages = json_decode(file_get_contents($package.'/pages.json'), true);
    $pages[0]['content'] = '<p>ok</p><script>alert(1)</script>';
    $json = json_encode($pages, JSON_UNESCAPED_UNICODE);
    file_put_contents($package.'/pages.json', $json);

    $manifest = json_decode(file_get_contents($package.'/manifest.json'), true);
    $manifest['checksums']['pages.json'] = hash('sha256', $json);
    file_put_contents($package.'/manifest.json', json_encode($manifest));

    $this->artisan('conteudo:importar', ['pacote' => $package])->assertSuccessful();

    expect(Page::query()->where('slug', $pages[0]['slug'])->value('content'))->not->toContain('<script');
});

test('caminho de arquivo fora de transparency-documents é recusado', function (): void {
    seedEditedContent();
    $package = exportPackage();
    wipeContent();

    $documents = json_decode(file_get_contents($package.'/documents.json'), true);
    $documents[0]['file_path'] = '../../.env';
    $json = json_encode($documents);
    file_put_contents($package.'/documents.json', $json);

    $manifest = json_decode(file_get_contents($package.'/manifest.json'), true);
    $manifest['checksums']['documents.json'] = hash('sha256', $json);
    file_put_contents($package.'/manifest.json', json_encode($manifest));

    $this->artisan('conteudo:importar', ['pacote' => $package])->expectsOutputToContain('Caminho de arquivo inválido')->assertFailed();
});

test('o cache público da página é invalidado na importação', function (): void {
    seedEditedContent();
    $package = exportPackage();
    wipeContent();

    $key = PublicPageCache::key('nome-novo');
    Cache::put($key, 'stale', 600);

    $this->artisan('conteudo:importar', ['pacote' => $package])->assertSuccessful();

    expect(Cache::has($key))->toBeFalse();
});

describe('imagens', function (): void {
    /**
     * Reescreve um dos JSONs do pacote e acerta o checksum no manifesto — o cenário de quem
     * montou ou editou o pacote à mão, que só as conferências de conteúdo podem pegar.
     *
     * @param  callable(array<int, array<string, mixed>>): array<int, array<string, mixed>>  $change
     */
    function rewritePackageJson(string $package, string $name, callable $change): void
    {
        $json = json_encode($change(json_decode(file_get_contents("{$package}/{$name}"), true)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        file_put_contents("{$package}/{$name}", $json);

        $manifest = json_decode(file_get_contents($package.'/manifest.json'), true);
        $manifest['checksums'][$name] = hash('sha256', (string) $json);
        file_put_contents($package.'/manifest.json', json_encode($manifest));
    }

    test('a página chega com a imagem, e o site a serve do outro lado', function (): void {
        seedEditedContent();
        $photo = Media::query()->where('alt', 'Fachada')->firstOrFail();
        $package = exportPackage();
        wipeContent();

        $this->artisan('conteudo:importar', ['pacote' => $package])->assertSuccessful();

        $this->get("/api/v1/public/media/{$photo->uuid}/640.webp")->assertOk()->assertHeader('Content-Type', 'image/webp');
        expect($this->getJson('/api/v1/public/pages/com-foto')->json('data.content'))
            ->toContain('srcset="/midia/'.$photo->uuid.'/400.webp 400w');
    });

    test('capa e galeria chegam na mesma ordem, e o site público as recebe', function (): void {
        seedEditedContent();
        $package = exportPackage();
        wipeContent();

        $this->artisan('conteudo:importar', ['pacote' => $package])->assertSuccessful();

        $images = $this->getJson('/api/v1/public/pages/com-foto')->json('data.images');
        expect(array_column($images['gallery'], 'alt'))->toBe(['Horta', 'Fachada'])
            ->and($images['cover']['alt'])->toBe('Horta');
    });

    test('a ordem mudada pelo painel é a que viaja', function (): void {
        seedEditedContent();
        $page = Page::query()->where('slug', 'com-foto')->firstOrFail();
        $photo = Media::query()->where('alt', 'Fachada')->firstOrFail();
        app(MoveGalleryImage::class)->handle($page, $photo, 'up', null);

        $package = exportPackage();
        wipeContent();
        $this->artisan('conteudo:importar', ['pacote' => $package])->assertSuccessful();

        expect(array_column($this->getJson('/api/v1/public/pages/com-foto')->json('data.images.gallery'), 'alt'))
            ->toBe(['Fachada', 'Horta']);
    });

    test('exportar recusa galeria com imagem marcada como de assistido', function (): void {
        seedEditedContent();
        Media::query()->where('alt', 'Horta')->update(['depicts_assisted_minor' => true]);

        $this->artisan('conteudo:exportar', ['destino' => $this->packagesDir])
            ->expectsOutputToContain('Remova-a da página antes de exportar')
            ->assertFailed();
    });

    test('capa ou galeria mal formada é recusada antes de escrever', function (array $entry): void {
        seedEditedContent();
        $package = exportPackage();
        wipeContent();

        rewritePackageJson($package, 'pages.json', function (array $pages) use ($entry): array {
            $index = array_search('com-foto', array_column($pages, 'slug'), true);
            $pages[$index]['images'][] = $entry;

            return $pages;
        });

        $this->artisan('conteudo:importar', ['pacote' => $package])
            ->expectsOutputToContain('inválida na página com-foto')
            ->assertFailed();

        expect(Page::query()->count())->toBe(0);
    })->with([
        'papel desconhecido' => [['media_uuid' => '0a1b2c3d-0000-4000-8000-000000000001', 'role' => 'hero', 'position' => 0]],
        'uuid mal formado' => [['media_uuid' => '../x', 'role' => 'gallery', 'position' => 9]],
        'posição negativa' => [['media_uuid' => '0a1b2c3d-0000-4000-8000-000000000001', 'role' => 'gallery', 'position' => -1]],
    ]);

    test('foto inicial que já existe aqui com outro uuid é recusada', function (): void {
        seedEditedContent();
        $package = exportPackage();
        wipeContent();

        // O cenário: `midia:importar-fotos-iniciais` rodou neste ambiente antes do pacote.
        Media::factory()->create(['origin_key' => 'horta-kids']);

        $this->artisan('conteudo:importar', ['pacote' => $package])
            ->expectsOutputToContain('já existe neste ambiente com outro uuid')
            ->assertFailed();

        expect(Page::query()->count())->toBe(0);
    });

    test('exportar recusa página que usa imagem marcada como de assistido', function (): void {
        seedEditedContent();
        Media::query()->where('alt', 'Fachada')->update(['depicts_assisted_minor' => true]);

        $this->artisan('conteudo:exportar', ['destino' => $this->packagesDir])
            ->expectsOutputToContain('Remova-a da página antes de exportar')
            ->assertFailed();
    });

    test('importar recusa página cuja imagem não está no pacote nem neste ambiente', function (): void {
        seedEditedContent();
        $package = exportPackage();
        wipeContent();

        rewritePackageJson($package, 'media.json', fn (array $media): array => []);

        $this->artisan('conteudo:importar', ['pacote' => $package])
            ->expectsOutputToContain('não está no pacote nem pode ser usada neste ambiente')
            ->assertFailed();

        expect(Page::query()->count())->toBe(0);
    });

    test('imagem marcada aqui como de assistido não é substituída, nem com --substituir', function (): void {
        seedEditedContent();
        $package = exportPackage();
        Media::query()->where('alt', 'Fachada')->update(['depicts_assisted_minor' => true]);

        $this->artisan('conteudo:importar', ['pacote' => $package, '--substituir' => true, '--force' => true])
            ->expectsOutputToContain('não pode substituí-la')
            ->assertFailed();

        expect(Media::query()->where('alt', 'Fachada')->value('depicts_assisted_minor'))->toBeTrue();
    });

    test('arquivo de imagem apontando para a pasta de outra imagem é recusado', function (): void {
        seedEditedContent();
        $package = exportPackage();
        wipeContent();

        rewritePackageJson($package, 'media.json', function (array $media): array {
            $media[0]['files'][0]['path'] = 'media/00000000-0000-4000-8000-000000000000/1/original.jpg';

            return $media;
        });

        $this->artisan('conteudo:importar', ['pacote' => $package])
            ->expectsOutputToContain('Caminho de arquivo inválido na imagem')
            ->assertFailed();
    });
});
