<?php

declare(strict_types=1);

use App\Actions\Content\PageImages\PlaceImageOnPage;
use App\Actions\Media\Data\MediaDetailsData;
use App\Actions\Media\StoreMedia;
use App\Enums\PageImageRole;
use App\Models\Media;
use App\Models\Page;
use App\Models\PageImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\Images;

/**
 * Capa e galeria das páginas (App\Enums\PageImageRole) no site público e nas regras da
 * biblioteca: "onde é usada", exclusão bloqueada e cache esquecido quando a imagem muda.
 */
beforeEach(function (): void {
    Storage::fake('local');
});

function storedImage(string $alt, int $width = 1000, int $height = 600, ?string $caption = null): Media
{
    return app(StoreMedia::class)->handle(
        Images::jpeg($width, $height)->getRealPath(),
        new MediaDetailsData($alt, $caption, false),
        null,
    );
}

function pageWithImages(string $slug = 'bazar'): Page
{
    $page = Page::factory()->published()->create(['slug' => $slug, 'title' => 'Bazar Beneficente', 'content' => '<p>Texto.</p>']);
    $place = app(PlaceImageOnPage::class);

    $place->handle($page, storedImage('Placa do bazar', 640, 1134), PageImageRole::Gallery);
    $entrance = storedImage('Entrada do bazar', 640, 1134, 'A entrada');
    $place->handle($page, $entrance, PageImageRole::Gallery);
    $place->handle($page, $entrance, PageImageRole::Cover);

    return $page;
}

describe('site público', function (): void {
    test('a página traz capa e galeria em ordem, com as derivadas de agora', function (): void {
        pageWithImages();
        $entrance = Media::query()->where('alt', 'Entrada do bazar')->firstOrFail();

        $images = $this->getJson('/api/v1/public/pages/bazar')->assertOk()->json('data.images');

        expect(array_column($images['gallery'], 'alt'))->toBe(['Placa do bazar', 'Entrada do bazar'])
            ->and($images['cover'])->toBe([
                'src' => "/midia/{$entrance->uuid}/640.webp",
                'srcset' => "/midia/{$entrance->uuid}/400.webp 400w, /midia/{$entrance->uuid}/640.webp 640w",
                'width' => 640,
                'height' => 1134,
                'alt' => 'Entrada do bazar',
                'caption' => 'A entrada',
            ]);
    });

    test('página sem capa nem galeria traz as duas vazias', function (): void {
        Page::factory()->published()->create(['slug' => 'sem-fotos']);

        expect($this->getJson('/api/v1/public/pages/sem-fotos')->json('data.images'))->toBe(['cover' => null, 'gallery' => []]);
    });

    test('imagem marcada como de assistido sai da capa e da galeria na hora, sem esperar o cache', function (): void {
        pageWithImages();
        $this->getJson('/api/v1/public/pages/bazar')->assertOk();
        $entrance = Media::query()->where('alt', 'Entrada do bazar')->firstOrFail();

        $this->actingAs(userWithRole('comunicacao'))->putJson("/api/v1/media/{$entrance->uuid}", [
            'alt' => $entrance->alt,
            'depicts_assisted_minor' => true,
            'confirm_marking' => true,
        ])->assertOk();

        $images = $this->getJson('/api/v1/public/pages/bazar')->json('data.images');
        expect($images['cover'])->toBeNull()
            ->and(array_column($images['gallery'], 'alt'))->toBe(['Placa do bazar']);
    });

    test('trocar o arquivo muda o srcset e o ETag da página, sem editar a página', function (): void {
        pageWithImages();
        $first = $this->getJson('/api/v1/public/pages/bazar');
        $placa = Media::query()->where('alt', 'Placa do bazar')->firstOrFail();

        $this->actingAs(userWithRole('comunicacao'))->post("/api/v1/media/{$placa->uuid}/file", [
            'file' => Images::jpeg(1400, 900),
        ], ['Accept' => 'application/json'])->assertOk();

        $second = $this->getJson('/api/v1/public/pages/bazar');

        expect($second->json('data.images.gallery.0.srcset'))->toContain('1280.webp 1280w')
            ->and($second->headers->get('ETag'))->not->toBe($first->headers->get('ETag'));
    });
});

describe('na biblioteca', function (): void {
    test('"onde é usada" mostra a página e se é capa, galeria ou texto', function (): void {
        $page = pageWithImages();
        $entrance = Media::query()->where('alt', 'Entrada do bazar')->firstOrFail();
        $page->content = '<figure><img src="/midia/'.$entrance->uuid.'" alt="Entrada" /></figure>';
        $page->save();

        $usages = $this->actingAs(userWithRole('comunicacao'))
            ->getJson("/api/v1/media/{$entrance->uuid}")
            ->assertOk()
            ->json('data.usages');

        expect($usages)->toHaveCount(1)
            ->and($usages[0]['slug'])->toBe('bazar')
            ->and($usages[0]['places'])->toBe(['content', 'cover', 'gallery']);
    });

    test('imagem na galeria não pode ser excluída, e a recusa diz a página', function (): void {
        pageWithImages();
        $placa = Media::query()->where('alt', 'Placa do bazar')->firstOrFail();

        $this->actingAs(userWithRole('direcao'))
            ->deleteJson("/api/v1/media/{$placa->uuid}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['media' => 'Esta imagem está em uso na página Bazar Beneficente (/bazar). Tire-a da página antes de excluir.']);
    });

    test('página na lixeira não bloqueia a exclusão, e a ligação vai junto', function (): void {
        $page = pageWithImages();
        $placa = Media::query()->where('alt', 'Placa do bazar')->firstOrFail();
        $page->delete();

        $this->actingAs(userWithRole('direcao'))->deleteJson("/api/v1/media/{$placa->uuid}")->assertNoContent();

        expect(PageImage::query()->where('media_id', $placa->id)->exists())->toBeFalse();
    });

    test('imagem marcada como de assistido não entra na galeria', function (): void {
        $page = Page::factory()->published()->create();
        $marked = Media::factory()->depictingAssistedMinor()->create();

        expect(fn () => app(PlaceImageOnPage::class)->handle($page, $marked, PageImageRole::Gallery))
            ->toThrow(ValidationException::class);
    });
});
