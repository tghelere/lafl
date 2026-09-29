<?php

declare(strict_types=1);

use App\Models\Media;
use App\Models\Page;
use App\Models\User;
use App\Support\Media\MediaPaths;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\Images;

beforeEach(function (): void {
    Storage::fake('local');
});

/**
 * @param  array<string, mixed>  $overrides
 * @return TestResponse<Response>
 */
function uploadMedia(User $user, UploadedFile $file, array $overrides = []): TestResponse
{
    return test()->actingAs($user)->post('/api/v1/media', [
        'file' => $file,
        'alt' => 'Fachada da sede vista da rua',
        'caption' => 'Sede, 2026',
        'depicts_assisted_minor' => '0',
        ...$overrides,
    ], ['Accept' => 'application/json']);
}

function uploadedMedia(User $user, ?UploadedFile $file = null): Media
{
    $response = uploadMedia($user, $file ?? Images::jpeg(1600, 1000));
    $response->assertCreated();

    return Media::query()->where('uuid', $response->json('data.id'))->firstOrFail();
}

describe('upload e derivadas', function (): void {
    test('grava a original e as derivadas webp só até a largura da original', function (): void {
        $media = uploadedMedia(userWithRole('comunicacao'));
        $disk = Storage::disk('local');

        expect($media->widths)->toBe([400, 640, 960, 1280])
            ->and($media->width)->toBe(1600)
            ->and($media->height)->toBe(1000)
            ->and($media->mime)->toBe('image/jpeg')
            ->and($disk->exists(MediaPaths::original($media)))->toBeTrue();

        foreach ($media->widths as $width) {
            $path = MediaPaths::derivative($media, $width);
            expect($disk->exists($path))->toBeTrue();

            $info = getimagesizefromstring((string) $disk->get($path));
            expect($info[0])->toBe($width)
                ->and($info['mime'])->toBe('image/webp')
                // Proporção preservada: 1600×1000 é 1,6.
                ->and($info[1])->toBe((int) round($width / 1.6));
        }
    });

    test('imagem mais estreita que a menor largura ganha uma derivada na própria largura', function (): void {
        $media = uploadedMedia(userWithRole('comunicacao'), Images::jpeg(300, 200));

        expect($media->widths)->toBe([300]);
    });

    test('PNG continua PNG na original', function (): void {
        $media = uploadedMedia(userWithRole('comunicacao'), Images::png(700, 500));

        expect($media->mime)->toBe('image/png')
            ->and(Storage::disk('local')->exists(MediaPaths::original($media)))->toBeTrue()
            ->and(MediaPaths::original($media))->toEndWith('/original.png');
    });

    test('o nome original do arquivo não chega ao disco nem ao banco', function (): void {
        $media = uploadedMedia(userWithRole('comunicacao'), Images::jpegWithExif(800, 600));

        $files = Storage::disk('local')->allFiles();
        expect($files)->not->toBeEmpty();

        foreach ($files as $file) {
            expect($file)->toMatch('#^media/'.$media->uuid.'/1/(original\.jpg|\d+\.webp)$#');
        }

        expect(json_encode($media->getAttributes()))->not->toContain('Maria')->not->toContain('IMG_');
    });
});

describe('EXIF', function (): void {
    test('é removido da original e das derivadas — inclusive o GPS', function (): void {
        $upload = Images::jpegWithExif(800, 600);
        // O cenário é real: o arquivo enviado TEM o EXIF, com GPS.
        expect((string) file_get_contents($upload->getRealPath()))->toContain('Exif');
        expect(exif_read_data($upload->getRealPath()))->toHaveKeys(['Orientation', 'GPSLatitudeRef']);

        $media = uploadedMedia(userWithRole('comunicacao'), $upload);

        $original = Storage::disk('local')->path(MediaPaths::original($media));
        $exif = @exif_read_data($original);
        expect($exif === false ? [] : $exif)->not->toHaveKey('Orientation')->not->toHaveKey('GPSLatitudeRef');

        foreach (Storage::disk('local')->allFiles('media/'.$media->uuid) as $path) {
            expect((string) Storage::disk('local')->get($path))->not->toContain('Exif');
        }
    });

    test('a orientação da câmera é gravada no pixel antes de o EXIF sair', function (): void {
        // 800×400 deitada, esquerda vermelha e direita azul. Orientation 6 = "girar 90° no
        // sentido horário para exibir": a imagem certa fica em pé, com o vermelho em cima.
        $media = uploadedMedia(userWithRole('comunicacao'), Images::jpegWithExif(800, 400, orientation: 6));

        expect($media->width)->toBe(400)->and($media->height)->toBe(800);

        $image = imagecreatefromstring((string) Storage::disk('local')->get(MediaPaths::original($media)));
        assert($image instanceof GdImage);

        $top = imagecolorsforindex($image, imagecolorat($image, 200, 20));
        $bottom = imagecolorsforindex($image, imagecolorat($image, 200, 780));

        expect($top['red'])->toBeGreaterThan(150)->and($top['blue'])->toBeLessThan(100)
            ->and($bottom['blue'])->toBeGreaterThan(150)->and($bottom['red'])->toBeLessThan(100);
    });
});

describe('validação', function (): void {
    test('recusa arquivo que não é imagem, pelo conteúdo e não pela extensão', function (): void {
        $fake = UploadedFile::fake()->createWithContent('foto.jpg', '%PDF-1.4 não sou imagem');

        uploadMedia(userWithRole('comunicacao'), $fake)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file' => 'A imagem precisa ser JPEG, PNG ou WebP.']);

        expect(Media::query()->count())->toBe(0);
    });

    test('recusa imagem acima do teto de pixels sem decodificá-la', function (): void {
        // PNG só com o cabeçalho IHDR declarando 10000×10000 (100 MP): pequeno em bytes,
        // enorme em memória. Se o processador decodificasse antes de conferir, o teste
        // estouraria o memory_limit.
        $ihdr = 'IHDR'.pack('NNCCCCC', 10000, 10000, 8, 2, 0, 0, 0);
        $png = "\x89PNG\r\n\x1a\n".pack('N', 13).$ihdr.pack('N', crc32($ihdr));

        uploadMedia(userWithRole('comunicacao'), UploadedFile::fake()->createWithContent('grande.png', $png))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file' => 'A imagem é grande demais (mais de 30 megapixels). Reduza as dimensões e envie de novo.']);
    });

    test('exige texto alternativo de verdade — espaço não conta', function (): void {
        uploadMedia(userWithRole('comunicacao'), Images::jpeg(500, 400), ['alt' => '   '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['alt']);
    });

    test('exige a declaração de assistido, sem valor padrão', function (): void {
        $user = userWithRole('comunicacao');

        test()->actingAs($user)->post('/api/v1/media', [
            'file' => Images::jpeg(500, 400),
            'alt' => 'Fachada',
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['depicts_assisted_minor' => 'Informe se a imagem mostra alguém que hoje ainda é criança ou adolescente e que é ou foi atendido pela instituição.']);
    });

    test('recusa foto declarada como de criança ou adolescente atendido, sem gravar nada', function (): void {
        $response = uploadMedia(userWithRole('direcao'), Images::jpeg(500, 400), ['depicts_assisted_minor' => '1'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['depicts_assisted_minor']);

        // A recusa explica o critério: quem manda acervo antigo, de pessoas hoje adultas, sabe
        // que a resposta dele é "Não".
        expect($response->json('errors.depicts_assisted_minor.0'))
            ->toContain('hoje ainda é criança ou adolescente')
            ->toContain('já são adultas responde "Não"');

        expect(Media::query()->count())->toBe(0)
            ->and(Storage::disk('local')->allFiles())->toBe([]);
    });
});

describe('permissão por papel', function (): void {
    test('comunicacao e direcao sobem e listam; atendimento e financeiro não', function (string $role, bool $allowed): void {
        $user = userWithRole($role);

        $upload = uploadMedia($user, Images::jpeg(500, 400));
        $list = test()->actingAs($user)->getJson('/api/v1/media');

        if ($allowed) {
            $upload->assertCreated();
            $list->assertOk();
        } else {
            $upload->assertForbidden();
            $list->assertForbidden();
        }
    })->with([
        ['comunicacao', true],
        ['direcao', true],
        ['atendimento', false],
        ['financeiro', false],
    ]);

    test('só direcao exclui', function (): void {
        $media = uploadedMedia(userWithRole('comunicacao'));

        test()->actingAs(userWithRole('comunicacao'))->deleteJson("/api/v1/media/{$media->uuid}")->assertForbidden();
        test()->actingAs(userWithRole('direcao'))->deleteJson("/api/v1/media/{$media->uuid}")->assertNoContent();
    });

    test('sem sessão, nem a listagem nem o arquivo do painel respondem', function (): void {
        $media = Media::factory()->create();

        test()->getJson('/api/v1/media')->assertUnauthorized();
        test()->getJson("/api/v1/media/{$media->uuid}/file/640.webp")->assertUnauthorized();
    });

    test('o mapa de acesso do painel inclui a biblioteca para quem pode', function (): void {
        test()->actingAs(userWithRole('comunicacao'))->getJson('/api/v1/auth/user')
            ->assertJsonPath('data.access.media', true);
        test()->actingAs(userWithRole('bazar'))->getJson('/api/v1/auth/user')
            ->assertJsonPath('data.access.media', false);
    });
});

describe('listagem', function (): void {
    test('é paginada, mais recente primeiro, e busca por texto alternativo ou legenda', function (): void {
        $user = userWithRole('comunicacao');
        Media::factory()->create(['alt' => 'Horta com pneus coloridos']);
        Media::factory()->create(['alt' => 'Bazar', 'caption' => 'Setor de LIVROS']);
        Media::factory()->count(3)->create();

        test()->actingAs($user)->getJson('/api/v1/media?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 5);

        test()->actingAs($user)->getJson('/api/v1/media?search=horta')->assertJsonCount(1, 'data');
        test()->actingAs($user)->getJson('/api/v1/media?search=livros')->assertJsonCount(1, 'data');
    });

    test('publishable=1 deixa de fora a imagem marcada como de assistido', function (): void {
        $user = userWithRole('comunicacao');
        Media::factory()->create();
        Media::factory()->depictingAssistedMinor()->create();

        test()->actingAs($user)->getJson('/api/v1/media?publishable=1')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.publishable', true);
    });
});

describe('substituição', function (): void {
    test('troca o arquivo mantendo o uuid, e a versão anterior sai do disco', function (): void {
        $user = userWithRole('comunicacao');
        $media = uploadedMedia($user, Images::jpeg(1600, 1000));
        $oldSha = $media->sha256;
        $oldDir = MediaPaths::directory($media);

        test()->actingAs($user)->post("/api/v1/media/{$media->uuid}/file", [
            'file' => Images::jpeg(700, 700),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.id', $media->uuid)
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.widths', [400, 640])
            ->assertJsonPath('data.width', 700);

        $media->refresh();
        expect($media->sha256)->not->toBe($oldSha)
            ->and(Storage::disk('local')->exists($oldDir))->toBeFalse()
            ->and(Storage::disk('local')->exists(MediaPaths::derivative($media, 640)))->toBeTrue();
    });

    test('arquivo inválido na substituição não toca na imagem atual', function (): void {
        $user = userWithRole('comunicacao');
        $media = uploadedMedia($user);

        test()->actingAs($user)->post("/api/v1/media/{$media->uuid}/file", [
            'file' => UploadedFile::fake()->createWithContent('x.jpg', 'nada'),
        ], ['Accept' => 'application/json'])->assertUnprocessable();

        expect($media->fresh()?->version)->toBe(1)
            ->and(Storage::disk('local')->exists(MediaPaths::derivative($media, 960)))->toBeTrue();
    });
});

describe('edição dos dados', function (): void {
    test('texto alternativo e legenda', function (): void {
        $user = userWithRole('comunicacao');
        $media = uploadedMedia($user);

        test()->actingAs($user)->putJson("/api/v1/media/{$media->uuid}", [
            'alt' => 'Nova descrição',
            'caption' => null,
            'depicts_assisted_minor' => false,
        ])->assertOk()->assertJsonPath('data.alt', 'Nova descrição')->assertJsonPath('data.caption', null);
    });

    test('marcar como assistido tira do site na hora; desmarcar é recusado', function (): void {
        $user = userWithRole('comunicacao');
        $media = uploadedMedia($user);

        test()->get("/api/v1/public/media/{$media->uuid}/640.webp")->assertOk();

        // Sem a confirmação explícita, nada muda: a marcação não se desfaz.
        test()->actingAs($user)->putJson("/api/v1/media/{$media->uuid}", [
            'alt' => $media->alt, 'depicts_assisted_minor' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'confirm_marking' => 'Confirme que a marcação tira a imagem do site em todas as páginas e não pode ser desfeita.',
        ]);
        test()->get("/api/v1/public/media/{$media->uuid}/640.webp")->assertOk();

        test()->actingAs($user)->putJson("/api/v1/media/{$media->uuid}", [
            'alt' => $media->alt, 'depicts_assisted_minor' => true, 'confirm_marking' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors(['confirm_marking']);

        test()->actingAs($user)->putJson("/api/v1/media/{$media->uuid}", [
            'alt' => $media->alt, 'depicts_assisted_minor' => true, 'confirm_marking' => true,
        ])->assertOk()->assertJsonPath('data.publishable', false)->assertJsonPath('data.src', null);

        test()->get("/api/v1/public/media/{$media->uuid}/640.webp")->assertNotFound();
        // O painel continua vendo — é preciso ver para excluir.
        test()->actingAs($user)->get("/api/v1/media/{$media->uuid}/file/640.webp")->assertOk();

        test()->actingAs($user)->putJson("/api/v1/media/{$media->uuid}", [
            'alt' => $media->alt, 'depicts_assisted_minor' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors(['depicts_assisted_minor']);
    });
});

describe('exclusão', function (): void {
    test('bloqueada enquanto uma página usa a imagem, dizendo qual', function (): void {
        $direcao = userWithRole('direcao');
        $media = uploadedMedia($direcao);
        Page::factory()->published()->create([
            'title' => 'Nossa história',
            'slug' => 'nossa-historia',
            'content' => '<figure><img src="/midia/'.$media->uuid.'" alt="x" /></figure>',
        ]);

        test()->actingAs($direcao)->getJson("/api/v1/media/{$media->uuid}")
            ->assertJsonPath('data.usages.0.title', 'Nossa história');

        test()->actingAs($direcao)->deleteJson("/api/v1/media/{$media->uuid}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['media' => 'Esta imagem está em uso na página Nossa história (/nossa-historia). Tire-a da página antes de excluir.']);

        expect(Media::query()->whereKey($media->id)->exists())->toBeTrue();
    });

    test('página na lixeira não bloqueia', function (): void {
        $direcao = userWithRole('direcao');
        $media = uploadedMedia($direcao);
        Page::factory()->create(['content' => '<figure><img src="/midia/'.$media->uuid.'" alt="x" /></figure>'])->delete();

        test()->actingAs($direcao)->deleteJson("/api/v1/media/{$media->uuid}")->assertNoContent();
    });

    test('sem uso, apaga a linha e todos os arquivos', function (): void {
        $direcao = userWithRole('direcao');
        $media = uploadedMedia($direcao);

        test()->actingAs($direcao)->deleteJson("/api/v1/media/{$media->uuid}")->assertNoContent();

        expect(Media::query()->count())->toBe(0)
            ->and(Storage::disk('local')->allFiles())->toBe([]);
    });
});

describe('auditoria', function (): void {
    test('upload, substituição, edição e exclusão ficam registrados com quem fez', function (): void {
        $direcao = userWithRole('direcao');
        $media = uploadedMedia($direcao);

        test()->actingAs($direcao)->post("/api/v1/media/{$media->uuid}/file", ['file' => Images::jpeg(500, 500)], ['Accept' => 'application/json'])->assertOk();
        test()->actingAs($direcao)->putJson("/api/v1/media/{$media->uuid}", ['alt' => 'Outra', 'depicts_assisted_minor' => false])->assertOk();
        test()->actingAs($direcao)->deleteJson("/api/v1/media/{$media->uuid}")->assertNoContent();

        $log = Activity::query()->where('log_name', 'media')->orderBy('id')->get();

        expect($log->pluck('event')->all())->toBe(['uploaded', 'replaced', 'updated', 'deleted'])
            ->and($log->pluck('causer_id')->unique()->all())->toBe([$direcao->id])
            ->and($log[2]->properties['old'])->toHaveKey('alt')
            ->and($log[3]->properties['uuid'])->toBe($media->uuid);
    });
});

describe('arquivo público', function (): void {
    test('serve a derivada como webp, com cache revalidável e ETag', function (): void {
        $media = uploadedMedia(userWithRole('comunicacao'));

        $response = test()->get("/api/v1/public/media/{$media->uuid}/640.webp");

        $response->assertOk()
            ->assertHeader('Content-Type', 'image/webp')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        expect($response->headers->get('Cache-Control'))->toContain('public')->toContain('max-age=600')->toContain('must-revalidate')
            ->and($response->headers->get('ETag'))->toBe('"'.$media->sha256.'-640"');

        test()->get("/api/v1/public/media/{$media->uuid}/640.webp", ['If-None-Match' => '"'.$media->sha256.'-640"'])
            ->assertStatus(304);
    });

    test('largura inexistente cai na maior que cabe; sem largura, a padrão', function (): void {
        $media = uploadedMedia(userWithRole('comunicacao'), Images::jpeg(1000, 800));

        expect(test()->get("/api/v1/public/media/{$media->uuid}/1920.webp")->headers->get('ETag'))->toBe('"'.$media->sha256.'-960"')
            ->and(test()->get("/api/v1/public/media/{$media->uuid}/100.webp")->headers->get('ETag'))->toBe('"'.$media->sha256.'-400"')
            ->and(test()->get("/api/v1/public/media/{$media->uuid}")->headers->get('ETag'))->toBe('"'.$media->sha256.'-960"');
    });

    test('a original não é pública, e uuid desconhecido é 404', function (): void {
        $media = uploadedMedia(userWithRole('comunicacao'));

        test()->get("/api/v1/public/media/{$media->uuid}/original")->assertNotFound();
        test()->get('/api/v1/public/media/00000000-0000-4000-8000-000000000000/640.webp')->assertNotFound();
    });

    test('depois da substituição, o mesmo endereço entrega o arquivo novo', function (): void {
        $user = userWithRole('comunicacao');
        $media = uploadedMedia($user);
        $before = test()->get("/api/v1/public/media/{$media->uuid}/400.webp")->headers->get('ETag');

        test()->actingAs($user)->post("/api/v1/media/{$media->uuid}/file", ['file' => Images::jpeg(900, 900)], ['Accept' => 'application/json'])->assertOk();

        $after = test()->get("/api/v1/public/media/{$media->uuid}/400.webp");
        $after->assertOk();
        expect($after->headers->get('ETag'))->not->toBe($before);
    });
});
