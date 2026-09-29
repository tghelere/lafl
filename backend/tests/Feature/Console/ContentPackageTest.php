<?php

declare(strict_types=1);

use App\Models\Page;
use App\Models\PageSlugHistory;
use App\Models\TransparencyDocument;
use App\Support\Cache\PublicPageCache;
use Database\Seeders\ContentPagesSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

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
        ])->all(),
        'documents' => TransparencyDocument::query()->orderBy('slug')->get()->map(fn (TransparencyDocument $d): array => [
            ...$d->only(['uuid', 'slug', 'title', 'year', 'file_path', 'file_size']),
            'type' => $d->type->value,
            'published_at' => $d->published_at?->toIso8601String(),
            'created_at' => $d->created_at?->toIso8601String(),
            'updated_at' => $d->updated_at?->toIso8601String(),
            'sha256' => hash('sha256', Storage::disk('local')->get($d->file_path)),
        ])->all(),
    ];
}

function wipeContent(): void
{
    Page::query()->withTrashed()->forceDelete();
    TransparencyDocument::query()->withTrashed()->forceDelete();
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

    expect(scandir($package))->toEqualCanonicalizing(['.', '..', 'manifest.json', 'pages.json', 'documents.json', 'files']);

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

    $manifest['format_version'] = 1;
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
