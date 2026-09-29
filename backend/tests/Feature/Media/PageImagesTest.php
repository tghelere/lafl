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
use Spatie\Activitylog\Models\Activity;
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

    test('corrigir o texto alternativo na biblioteca muda a galeria no site na hora', function (): void {
        pageWithImages();
        $this->getJson('/api/v1/public/pages/bazar')->assertOk();
        $placa = Media::query()->where('alt', 'Placa do bazar')->firstOrFail();

        $this->actingAs(userWithRole('comunicacao'))->putJson("/api/v1/media/{$placa->uuid}", [
            'alt' => 'Placa do bazar, vista da calçada',
            'caption' => 'Nova legenda',
            'depicts_assisted_minor' => false,
        ])->assertOk();

        $first = $this->getJson('/api/v1/public/pages/bazar')->json('data.images.gallery.0');
        expect($first['alt'])->toBe('Placa do bazar, vista da calçada')
            ->and($first['caption'])->toBe('Nova legenda');
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

describe('Imagens desta página (painel)', function (): void {
    test('lista capa, galeria em ordem e as imagens do texto salvo', function (): void {
        $page = pageWithImages();
        $inText = storedImage('Foto no meio do texto');
        $page->content = '<p>a</p><figure><img src="/midia/'.$inText->uuid.'" alt="Outro texto" /></figure>';
        $page->save();

        $data = $this->actingAs(userWithRole('comunicacao'))
            ->getJson("/api/v1/pages/{$page->uuid}/images")
            ->assertOk()
            ->json('data');

        expect($data['cover']['alt'])->toBe('Entrada do bazar')
            ->and(array_column($data['gallery'], 'alt'))->toBe(['Placa do bazar', 'Entrada do bazar'])
            ->and(array_column($data['content'], 'alt'))->toBe(['Foto no meio do texto'])
            ->and($data['gallery'][1]['usages'][0]['places'])->toBe(['cover', 'gallery']);
    });

    test('enviar põe a foto na biblioteca e no fim da galeria, e o site mostra', function (): void {
        $page = pageWithImages();

        $response = $this->actingAs(userWithRole('comunicacao'))->post("/api/v1/pages/{$page->uuid}/images", [
            'file' => Images::jpeg(900, 600),
            'alt' => 'Nova foto da loja',
            'caption' => 'Recém-chegada',
            'depicts_assisted_minor' => '0',
        ], ['Accept' => 'application/json']);

        $response->assertCreated();
        $media = Media::query()->where('uuid', $response->json('data.id'))->firstOrFail();

        expect(PageImage::query()->where('media_id', $media->id)->value('position'))->toBe(2)
            ->and(Activity::query()->where('event', 'uploaded')->where('subject_id', $media->id)->exists())->toBeTrue()
            ->and(Activity::query()->where('event', 'placed')->where('subject_id', $media->id)->exists())->toBeTrue();

        $gallery = $this->getJson('/api/v1/public/pages/bazar')->json('data.images.gallery');
        expect(array_column($gallery, 'alt'))->toBe(['Placa do bazar', 'Entrada do bazar', 'Nova foto da loja'])
            ->and($gallery[2]['caption'])->toBe('Recém-chegada');
    });

    test('enviar recusa foto declarada de criança atendida, e nada fica gravado', function (): void {
        $page = pageWithImages();
        $before = Media::query()->count();

        $this->actingAs(userWithRole('comunicacao'))->post("/api/v1/pages/{$page->uuid}/images", [
            'file' => Images::jpeg(900, 600),
            'alt' => 'Crianças no pátio',
            'depicts_assisted_minor' => '1',
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors(['depicts_assisted_minor']);

        expect(Media::query()->count())->toBe($before)
            ->and(PageImage::query()->where('page_id', $page->id)->count())->toBe(3);
    });

    test('tirar da galeria fecha o buraco e deixa a imagem na biblioteca', function (): void {
        $page = pageWithImages();
        $placa = Media::query()->where('alt', 'Placa do bazar')->firstOrFail();

        $this->actingAs(userWithRole('comunicacao'))
            ->deleteJson("/api/v1/pages/{$page->uuid}/images/{$placa->uuid}?role=gallery")
            ->assertNoContent();

        $gallery = PageImage::query()->where('page_id', $page->id)->where('role', 'gallery')->get();
        expect($gallery->pluck('position')->all())->toBe([0])
            ->and(Media::query()->whereKey($placa->id)->exists())->toBeTrue()
            ->and(array_column($this->getJson('/api/v1/public/pages/bazar')->json('data.images.gallery'), 'alt'))->toBe(['Entrada do bazar']);
    });

    test('tirar da capa não mexe na galeria', function (): void {
        $page = pageWithImages();
        $entrance = Media::query()->where('alt', 'Entrada do bazar')->firstOrFail();

        $this->actingAs(userWithRole('comunicacao'))
            ->deleteJson("/api/v1/pages/{$page->uuid}/images/{$entrance->uuid}?role=cover")
            ->assertNoContent();

        $images = $this->getJson('/api/v1/public/pages/bazar')->json('data.images');
        expect($images['cover'])->toBeNull()->and($images['gallery'])->toHaveCount(2);
    });

    test('tirar imagem que não está lá é recusado com mensagem', function (): void {
        $page = pageWithImages();
        $other = storedImage('Outra');

        $this->actingAs(userWithRole('comunicacao'))
            ->deleteJson("/api/v1/pages/{$page->uuid}/images/{$other->uuid}?role=gallery")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['media' => 'Esta imagem não está na galeria desta página.']);
    });

    test('quem não edita páginas não vê, não envia e não tira', function (string $role): void {
        $page = pageWithImages();
        $placa = Media::query()->where('alt', 'Placa do bazar')->firstOrFail();
        $user = userWithRole($role);

        $this->actingAs($user)->getJson("/api/v1/pages/{$page->uuid}/images")->assertForbidden();
        $this->actingAs($user)->post("/api/v1/pages/{$page->uuid}/images", [
            'file' => Images::jpeg(300, 200), 'alt' => 'x', 'depicts_assisted_minor' => '0',
        ], ['Accept' => 'application/json'])->assertForbidden();
        $this->actingAs($user)->deleteJson("/api/v1/pages/{$page->uuid}/images/{$placa->uuid}?role=gallery")->assertForbidden();
    })->with(['financeiro', 'bazar', 'atendimento']);
});

describe('escolher a capa', function (): void {
    test('troca a capa por imagem da biblioteca; a anterior continua na galeria e na biblioteca', function (): void {
        $page = pageWithImages();
        $new = storedImage('Fachada do bazar à tarde', 900, 600);
        $entrance = Media::query()->where('alt', 'Entrada do bazar')->firstOrFail();

        $this->actingAs(userWithRole('comunicacao'))
            ->putJson("/api/v1/pages/{$page->uuid}/images/cover", ['media' => $new->uuid])
            ->assertNoContent();

        $images = $this->getJson('/api/v1/public/pages/bazar')->json('data.images');
        expect($images['cover']['alt'])->toBe('Fachada do bazar à tarde')
            ->and(array_column($images['gallery'], 'alt'))->toBe(['Placa do bazar', 'Entrada do bazar']);

        $log = Activity::query()->where('event', 'placed')->where('subject_id', $new->id)->firstOrFail();
        expect($log->properties['role'])->toBe('cover')
            ->and($log->properties['replaced'])->toBe($entrance->uuid);
    });

    test('capa de imagem marcada como de assistido é recusada', function (): void {
        $page = pageWithImages();
        $marked = Media::factory()->depictingAssistedMinor()->create();

        $this->actingAs(userWithRole('comunicacao'))
            ->putJson("/api/v1/pages/{$page->uuid}/images/cover", ['media' => $marked->uuid])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['media']);
    });

    test('imagem inexistente é recusada com mensagem', function (): void {
        $page = pageWithImages();

        $this->actingAs(userWithRole('comunicacao'))
            ->putJson("/api/v1/pages/{$page->uuid}/images/cover", ['media' => '0a1b2c3d-0000-4000-8000-000000000009'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['media' => 'Esta imagem não existe mais na biblioteca.']);
    });

    test('enviar com role=cover põe a foto nova na capa, não na galeria', function (): void {
        $page = pageWithImages();

        $response = $this->actingAs(userWithRole('comunicacao'))->post("/api/v1/pages/{$page->uuid}/images", [
            'file' => Images::jpeg(900, 600),
            'alt' => 'Capa nova do bazar',
            'depicts_assisted_minor' => '0',
            'role' => 'cover',
        ], ['Accept' => 'application/json']);
        $response->assertCreated();

        $images = $this->getJson('/api/v1/public/pages/bazar')->json('data.images');
        expect($images['cover']['alt'])->toBe('Capa nova do bazar')
            ->and($images['gallery'])->toHaveCount(2);
    });

    test('a lista do painel diz onde o site mostra a capa', function (string $slug, array $expected): void {
        $page = pageWithImages($slug);

        expect($this->actingAs(userWithRole('comunicacao'))
            ->getJson("/api/v1/pages/{$page->uuid}/images")
            ->json('data.cover_shown_on'))->toBe($expected);
    })->with([
        'quem somos' => ['quem-somos', ['no destaque da página inicial']],
        'bazar' => ['bazar', ['no cartão de Bazar beneficente, em "O que fazemos"']],
        'página sem uso da capa' => ['governanca', []],
    ]);

    test('quem não edita a página não troca a capa', function (): void {
        $page = pageWithImages();
        $new = storedImage('Outra');

        $this->actingAs(userWithRole('financeiro'))
            ->putJson("/api/v1/pages/{$page->uuid}/images/cover", ['media' => $new->uuid])
            ->assertForbidden();
    });
});

describe('reordenar a galeria', function (): void {
    function galleryOrder(): array
    {
        return array_column(test()->getJson('/api/v1/public/pages/bazar')->json('data.images.gallery'), 'alt');
    }

    test('mover para baixo e para cima troca com a vizinha, e o site acompanha', function (): void {
        $page = pageWithImages();
        app(PlaceImageOnPage::class)->handle($page, storedImage('Salão do bazar'), PageImageRole::Gallery);
        $placa = Media::query()->where('alt', 'Placa do bazar')->firstOrFail();
        $salao = Media::query()->where('alt', 'Salão do bazar')->firstOrFail();
        $user = userWithRole('comunicacao');

        expect(galleryOrder())->toBe(['Placa do bazar', 'Entrada do bazar', 'Salão do bazar']);

        $this->actingAs($user)
            ->postJson("/api/v1/pages/{$page->uuid}/images/{$placa->uuid}/move", ['direction' => 'down'])
            ->assertOk()
            ->assertJsonPath('data', ['position' => 2, 'count' => 3]);
        expect(galleryOrder())->toBe(['Entrada do bazar', 'Placa do bazar', 'Salão do bazar']);

        $this->actingAs($user)
            ->postJson("/api/v1/pages/{$page->uuid}/images/{$salao->uuid}/move", ['direction' => 'up'])
            ->assertOk()
            ->assertJsonPath('data.position', 2);
        expect(galleryOrder())->toBe(['Entrada do bazar', 'Salão do bazar', 'Placa do bazar'])
            ->and(PageImage::query()->where('page_id', $page->id)->where('role', 'gallery')->orderBy('position')->pluck('position')->all())
            ->toBe([0, 1, 2])
            ->and(Activity::query()->where('event', 'moved')->count())->toBe(2);
    });

    test('a primeira não sobe e a última não desce, com a mensagem da API', function (): void {
        $page = pageWithImages();
        $placa = Media::query()->where('alt', 'Placa do bazar')->firstOrFail();
        $entrance = Media::query()->where('alt', 'Entrada do bazar')->firstOrFail();
        $user = userWithRole('comunicacao');

        $this->actingAs($user)->postJson("/api/v1/pages/{$page->uuid}/images/{$placa->uuid}/move", ['direction' => 'up'])
            ->assertUnprocessable()->assertJsonValidationErrors(['direction' => 'Esta imagem já é a primeira da galeria.']);
        $this->actingAs($user)->postJson("/api/v1/pages/{$page->uuid}/images/{$entrance->uuid}/move", ['direction' => 'down'])
            ->assertUnprocessable()->assertJsonValidationErrors(['direction' => 'Esta imagem já é a última da galeria.']);
    });

    test('a capa não se move, e direção inválida é recusada', function (): void {
        $page = pageWithImages();
        $other = storedImage('Fora da galeria');
        $placa = Media::query()->where('alt', 'Placa do bazar')->firstOrFail();
        $user = userWithRole('comunicacao');

        $this->actingAs($user)->postJson("/api/v1/pages/{$page->uuid}/images/{$other->uuid}/move", ['direction' => 'up'])
            ->assertUnprocessable()->assertJsonValidationErrors(['media']);
        $this->actingAs($user)->postJson("/api/v1/pages/{$page->uuid}/images/{$placa->uuid}/move", ['direction' => 'left'])
            ->assertUnprocessable()->assertJsonValidationErrors(['direction']);
        $this->actingAs(userWithRole('financeiro'))->postJson("/api/v1/pages/{$page->uuid}/images/{$placa->uuid}/move", ['direction' => 'down'])
            ->assertForbidden();
    });
});
