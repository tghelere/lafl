<?php

declare(strict_types=1);

use App\Models\Media;
use App\Models\Page;
use App\Models\User;
use App\Support\Html\ContentSanitizer;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\Images;

beforeEach(function (): void {
    Storage::fake('local');
});

function libraryImage(User $user, int $width = 1600, int $height = 1000): Media
{
    $response = test()->actingAs($user)->post('/api/v1/media', [
        'file' => Images::jpeg($width, $height),
        'alt' => 'Fachada da sede',
        'depicts_assisted_minor' => '0',
    ], ['Accept' => 'application/json']);
    $response->assertCreated();

    return Media::query()->where('uuid', $response->json('data.id'))->firstOrFail();
}

function figureFor(Media $media, string $alt = 'Fachada da sede', string $caption = 'A sede em 2026'): string
{
    return '<figure><img src="/midia/'.$media->uuid.'" alt="'.$alt.'"><figcaption>'.$caption.'</figcaption></figure>';
}

/**
 * @param  array<string, mixed>  $overrides
 */
function savePage(User $user, string $content, ?Page $page = null, array $overrides = []): TestResponse
{
    $payload = [
        'slug' => $page->slug ?? 'nossa-sede',
        'title' => $page->title ?? 'Nossa sede',
        'content' => $content,
        'meta_title' => null,
        'meta_description' => null,
        'status' => 'published',
        ...$overrides,
    ];

    return $page === null
        ? test()->actingAs($user)->postJson('/api/v1/pages', $payload)
        : test()->actingAs($user)->putJson("/api/v1/pages/{$page->uuid}", $payload);
}

describe('sanitizador', function (): void {
    test('aceita figure, img e figcaption só com src canônico da biblioteca', function (): void {
        $uuid = '0a1b2c3d-0000-4000-8000-000000000001';
        $html = app(ContentSanitizer::class)->sanitize(
            '<figure><img src="/midia/'.$uuid.'" alt="Fachada" width="10" onerror="x()" class="y"><figcaption>Legenda</figcaption></figure>',
        );

        expect($html)->toBe('<figure><img src="/midia/'.$uuid.'" alt="Fachada" /><figcaption>Legenda</figcaption></figure>');
    });

    test('derruba imagem de endereço externo, data:, protocolo relativo ou forma não canônica', function (string $src): void {
        $html = app(ContentSanitizer::class)->sanitize('<figure><img src="'.$src.'" alt="x"><figcaption>Legenda</figcaption></figure>');

        expect($html)->not->toContain('<img')->toContain('Legenda');
    })->with([
        'https' => 'https://exemplo.org/foto.jpg',
        'http' => 'http://exemplo.org/foto.jpg',
        'protocolo relativo' => '//exemplo.org/midia/0a1b2c3d-0000-4000-8000-000000000001',
        'data' => 'data:image/png;base64,iVBORw0KGgo=',
        'largura' => '/midia/0a1b2c3d-0000-4000-8000-000000000001/960.webp',
        'query' => '/midia/0a1b2c3d-0000-4000-8000-000000000001?x=1',
        'outro caminho' => '/fotos/home/fachada-sede-960.webp',
        'javascript' => 'javascript:alert(1)',
    ]);
});

describe('ao salvar a página', function (): void {
    test('grava a forma canônica, sem srcset nem dimensões', function (): void {
        $user = userWithRole('direcao');
        $media = libraryImage($user);

        $response = savePage($user, '<p>Texto</p>'.figureFor($media));

        $response->assertCreated();
        expect($response->json('data.content'))
            ->toBe('<p>Texto</p><figure><img src="/midia/'.$media->uuid.'" alt="Fachada da sede" /><figcaption>A sede em 2026</figcaption></figure>');
    });

    test('recusa imagem que não existe na biblioteca', function (): void {
        $content = '<figure><img src="/midia/0a1b2c3d-0000-4000-8000-000000000001" alt="x"></figure>';

        savePage(userWithRole('direcao'), $content)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['content' => 'O conteúdo usa uma imagem que não existe mais na biblioteca. Remova-a e insira outra.']);
    });

    test('recusa imagem marcada como de assistido — a trava é na API', function (): void {
        $user = userWithRole('direcao');
        $media = Media::factory()->depictingAssistedMinor()->create(['alt' => 'Pátio']);

        savePage($user, figureFor($media))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['content' => 'A imagem "Pátio" foi marcada como foto de criança ou adolescente atendido e não pode ir para o site. Remova-a do conteúdo.']);
    });

    test('recusa imagem sem texto alternativo', function (): void {
        $user = userWithRole('direcao');
        $media = libraryImage($user);

        savePage($user, '<figure><img src="/midia/'.$media->uuid.'" alt=" "></figure>')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['content']);
        savePage($user, '<figure><img src="/midia/'.$media->uuid.'"></figure>')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['content']);
    });

    test('o endpoint administrativo devolve a forma crua', function (): void {
        $user = userWithRole('direcao');
        $media = libraryImage($user);
        $uuid = savePage($user, figureFor($media))->json('data.id');

        expect(test()->actingAs($user)->getJson("/api/v1/pages/{$uuid}")->json('data.content'))->not->toContain('srcset');
    });
});

describe('na leitura pública', function (): void {
    test('a imagem sai com srcset das derivadas, dimensões e carregamento preguiçoso', function (): void {
        $user = userWithRole('direcao');
        $media = libraryImage($user, 1600, 1000);
        savePage($user, figureFor($media))->assertCreated();

        $content = test()->getJson('/api/v1/public/pages/nossa-sede')->assertOk()->json('data.content');
        $u = $media->uuid;

        expect($content)->toBe(
            '<figure><img src="/midia/'.$u.'/960.webp" '.
            'srcset="/midia/'.$u.'/400.webp 400w, /midia/'.$u.'/640.webp 640w, /midia/'.$u.'/960.webp 960w, /midia/'.$u.'/1280.webp 1280w" '.
            'sizes="(min-width: 48rem) 42rem, calc(100vw - 2rem)" width="1600" height="1000" alt="Fachada da sede" loading="lazy" decoding="async" />'.
            '<figcaption>A sede em 2026</figcaption></figure>',
        );
    });

    test('substituir o arquivo muda o srcset do site sem editar a página', function (): void {
        $user = userWithRole('direcao');
        $media = libraryImage($user, 1600, 1000);
        savePage($user, figureFor($media))->assertCreated();

        // Aquece o cache público da página.
        expect(test()->getJson('/api/v1/public/pages/nossa-sede')->json('data.content'))->toContain('1280w');

        test()->actingAs($user)->post("/api/v1/media/{$media->uuid}/file", ['file' => Images::jpeg(700, 350)], ['Accept' => 'application/json'])->assertOk();

        $content = test()->getJson('/api/v1/public/pages/nossa-sede')->json('data.content');
        expect($content)->not->toContain('1280w')
            ->toContain('/midia/'.$media->uuid.'/640.webp 640w')
            ->toContain('width="700" height="350"');
    });

    test('imagem marcada como de assistido depois de inserida sai do site com a legenda', function (): void {
        $user = userWithRole('direcao');
        $media = libraryImage($user);
        savePage($user, '<p>Antes</p>'.figureFor($media).'<p>Depois</p>')->assertCreated();
        expect(test()->getJson('/api/v1/public/pages/nossa-sede')->json('data.content'))->toContain('<figure>');

        test()->actingAs($user)->putJson("/api/v1/media/{$media->uuid}", ['alt' => 'x', 'depicts_assisted_minor' => true, 'confirm_marking' => true])->assertOk();

        expect(test()->getJson('/api/v1/public/pages/nossa-sede')->json('data.content'))->toBe('<p>Antes</p><p>Depois</p>');
    });

    test('imagem que sumiu da biblioteca não deixa quadro vazio', function (): void {
        $user = userWithRole('direcao');
        $media = libraryImage($user);
        Page::factory()->published()->create(['slug' => 'orfa', 'content' => '<p>a</p>'.figureFor($media)]);
        $media->delete();

        expect(test()->getJson('/api/v1/public/pages/orfa')->json('data.content'))->toBe('<p>a</p>');
    });
});
