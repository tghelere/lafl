<?php

declare(strict_types=1);

use App\Enums\PageStatus;
use App\Models\Page;
use App\Support\Cache\PublicPageCache;
use App\Support\Content\InitialPages;
use Illuminate\Support\Facades\Cache;

test('cria todas as páginas do conteúdo inicial num banco vazio', function (): void {
    expect(Page::query()->count())->toBe(0);

    $this->artisan('conteudo:importar-inicial')->assertSuccessful();

    $slugs = Page::query()->pluck('slug')->all();

    foreach (InitialPages::all() as $data) {
        expect($slugs)->toContain($data['slug']);
    }

    expect(Page::query()->count())->toBe(count(InitialPages::all()));
});

test('respeita o status de cada página do conteúdo inicial', function (): void {
    $this->artisan('conteudo:importar-inicial')->assertSuccessful();

    foreach (InitialPages::all() as $data) {
        $page = Page::query()->where('slug', $data['slug'])->firstOrFail();
        $expected = $data['status'] ?? PageStatus::Published;

        expect($page->status)->toBe($expected);

        if ($expected === PageStatus::Published) {
            expect($page->published_at)->not->toBeNull();
        } else {
            expect($page->published_at)->toBeNull();
        }
    }
});

test('rodar duas vezes não cria nada na segunda', function (): void {
    $this->artisan('conteudo:importar-inicial')->assertSuccessful();

    $total = Page::query()->count();
    $updatedAt = Page::query()->where('slug', 'quem-somos')->firstOrFail()->updated_at;

    $this->artisan('conteudo:importar-inicial')->assertSuccessful();

    expect(Page::query()->count())->toBe($total)
        ->and(Page::query()->where('slug', 'quem-somos')->firstOrFail()->updated_at->equalTo($updatedAt))->toBeTrue();
});

test('nunca sobrescreve o conteúdo de uma página que já existe', function (): void {
    $page = Page::factory()->create([
        'slug' => 'quem-somos',
        'title' => 'Título escrito pela instituição',
        'content' => '<p>Texto escrito pelo painel.</p>',
        'meta_description' => 'Descrição do painel.',
        'status' => PageStatus::Draft,
        'published_at' => null,
    ]);

    $this->artisan('conteudo:importar-inicial')->assertSuccessful();

    $page->refresh();

    expect($page->title)->toBe('Título escrito pela instituição')
        ->and($page->content)->toBe('<p>Texto escrito pelo painel.</p>')
        ->and($page->meta_description)->toBe('Descrição do painel.')
        ->and($page->status)->toBe(PageStatus::Draft)
        ->and($page->published_at)->toBeNull();
});

test('página na lixeira não é recriada nem ressuscitada', function (): void {
    $page = Page::factory()->create(['slug' => 'quem-somos']);
    $page->delete();

    $this->artisan('conteudo:importar-inicial')
        ->expectsOutputToContain('na lixeira')
        ->assertSuccessful();

    expect(Page::query()->where('slug', 'quem-somos')->exists())->toBeFalse()
        ->and(Page::query()->withTrashed()->where('slug', 'quem-somos')->count())->toBe(1);
});

test('funciona em staging e production, ao contrário do seeder', function (string $environment): void {
    app()['env'] = $environment;

    try {
        $this->artisan('conteudo:importar-inicial')->assertSuccessful();
    } finally {
        app()['env'] = 'testing';
    }

    expect(Page::query()->count())->toBe(count(InitialPages::all()));
})->with(['staging', 'production']);

test('esquece o cache negativo da página recém-criada', function (): void {
    // Site pediu /quem-somos antes de a página existir: o null fica cacheado por 10 minutos.
    Cache::put(PublicPageCache::key('quem-somos'), null, now()->addMinutes(10));

    $this->artisan('conteudo:importar-inicial')->assertSuccessful();

    expect(Cache::has(PublicPageCache::key('quem-somos')))->toBeFalse();
});

test('o resumo informa quantas páginas foram criadas e quantas foram puladas', function (): void {
    Page::factory()->create(['slug' => 'quem-somos']);

    $total = count(InitialPages::all());

    $this->artisan('conteudo:importar-inicial')
        ->expectsOutputToContain(($total - 1).' criada(s), 1 já existente(s), 0 na lixeira.')
        ->assertSuccessful();
});
