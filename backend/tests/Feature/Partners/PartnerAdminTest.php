<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Media;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\Images;

beforeEach(function (): void {
    Storage::fake('local');
});

/**
 * @param  array<string, mixed>  $overrides
 * @return TestResponse<Response>
 */
function createPartner(User $user, array $overrides = []): TestResponse
{
    return test()->actingAs($user)->post('/api/v1/partners', [
        'name' => 'Padaria Estrela',
        'logo' => Images::jpeg(800, 400),
        ...$overrides,
    ], ['Accept' => 'application/json']);
}

test('não autenticado recebe 401', function (): void {
    $this->getJson('/api/v1/partners')->assertUnauthorized();
    $this->postJson('/api/v1/partners')->assertUnauthorized();
});

describe('criação', function (): void {
    test('cria com link, logo na biblioteca e alt igual ao nome', function (): void {
        $response = createPartner(userWithRole(Role::Comunicacao->value), ['url' => 'https://padaria.example.com.br/']);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Padaria Estrela')
            ->assertJsonPath('data.url', 'https://padaria.example.com.br/')
            ->assertJsonPath('data.is_active', true);

        $partner = Partner::query()->where('uuid', $response->json('data.uuid'))->firstOrFail();
        expect($partner->media->alt)->toBe('Padaria Estrela')
            ->and($partner->media->depicts_assisted_minor)->toBeFalse();
    });

    test('link é opcional, e vazio vira nulo', function (): void {
        $response = createPartner(userWithRole(Role::Direcao->value), ['url' => '']);

        $response->assertCreated()->assertJsonPath('data.url', null);
    });

    test('sem ordem informada, vai para o fim da lista', function (): void {
        Partner::factory()->create(['position' => 7]);

        $response = createPartner(userWithRole(Role::Direcao->value));

        $response->assertJsonPath('data.position', 8);
    });

    test('nome e logo são obrigatórios', function (): void {
        $this->actingAs(userWithRole(Role::Direcao->value))
            ->postJson('/api/v1/partners', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'logo']);
    });

    test('nome só com espaços é recusado', function (): void {
        createPartner(userWithRole(Role::Direcao->value), ['name' => '   '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    });

    test('link precisa ser http ou https válido', function (string $url): void {
        createPartner(userWithRole(Role::Direcao->value), ['url' => $url])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('url');
    })->with(['javascript:alert(1)', 'ftp://exemplo.com', 'sem-esquema.com', 'https://']);

    test('logo precisa ser PNG, JPG ou WebP — SVG e PDF são recusados', function (): void {
        $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"/>');
        $pdf = UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf');

        createPartner(userWithRole(Role::Direcao->value), ['logo' => $svg])->assertUnprocessable()->assertJsonValidationErrors('logo');
        createPartner(userWithRole(Role::Direcao->value), ['logo' => $pdf])->assertUnprocessable()->assertJsonValidationErrors('logo');
    });

    test('aceita PNG e WebP', function (): void {
        $user = userWithRole(Role::Direcao->value);

        createPartner($user, ['logo' => Images::png(600, 300)])->assertCreated();
        createPartner($user, ['name' => 'Outro', 'logo' => Images::webp(600, 300)])->assertCreated();
    });
});

describe('atualização e exclusão', function (): void {
    test('atualiza nome, link, ordem e ativo sem trocar a logo, e o alt acompanha o nome', function (): void {
        $user = userWithRole(Role::Comunicacao->value);
        $uuid = createPartner($user)->json('data.uuid');
        $mediaBefore = Partner::query()->where('uuid', $uuid)->firstOrFail()->media_id;

        $this->actingAs($user)->post("/api/v1/partners/{$uuid}", [
            '_method' => 'PUT',
            'name' => 'Padaria Nova Estrela',
            'url' => 'https://nova.example.com',
            'position' => 3,
            'is_active' => '0',
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Padaria Nova Estrela')
            ->assertJsonPath('data.position', 3)
            ->assertJsonPath('data.is_active', false);

        $partner = Partner::query()->where('uuid', $uuid)->firstOrFail();
        expect($partner->media_id)->toBe($mediaBefore)
            ->and($partner->media->alt)->toBe('Padaria Nova Estrela');
    });

    test('trocar a logo substitui o arquivo da mesma imagem', function (): void {
        $user = userWithRole(Role::Direcao->value);
        $uuid = createPartner($user)->json('data.uuid');

        $this->actingAs($user)->post("/api/v1/partners/{$uuid}", [
            '_method' => 'PUT',
            'name' => 'Padaria Estrela',
            'logo' => Images::png(1000, 500),
        ], ['Accept' => 'application/json'])->assertOk();

        $media = Partner::query()->where('uuid', $uuid)->firstOrFail()->media;
        expect(Media::query()->count())->toBe(1)
            ->and($media->version)->toBe(2)
            ->and($media->width)->toBe(1000);
    });

    test('exclusão é soft delete e a imagem fica na biblioteca', function (): void {
        $user = userWithRole(Role::Direcao->value);
        $partner = Partner::factory()->create();

        $this->actingAs($user)->deleteJson("/api/v1/partners/{$partner->uuid}")->assertNoContent();

        expect(Partner::query()->count())->toBe(0)
            ->and(Partner::withTrashed()->count())->toBe(1)
            ->and(Media::query()->count())->toBe(1);
    });

    test('a imagem da logo de parceiro ativo não pode ser excluída da biblioteca', function (): void {
        $user = userWithRole(Role::Direcao->value);
        $partner = Partner::factory()->create(['name' => 'Padaria Estrela']);

        $this->actingAs($user)->deleteJson("/api/v1/media/{$partner->media->uuid}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('media');

        expect(Media::query()->count())->toBe(1);
    });
});

describe('listagem administrativa', function (): void {
    test('mostra ativos e inativos, paginada e na ordem', function (): void {
        $user = userWithRole(Role::Direcao->value);
        Partner::factory()->create(['name' => 'B', 'position' => 2]);
        Partner::factory()->inactive()->create(['name' => 'A', 'position' => 1]);
        Partner::factory()->create(['name' => 'C', 'position' => 3]);

        $response = $this->actingAs($user)->getJson('/api/v1/partners?per_page=2');

        $response->assertOk();
        expect(collect($response->json('data'))->pluck('name')->all())->toBe(['A', 'B'])
            ->and($response->json('meta.total'))->toBe(3)
            ->and($response->json('data.0'))->not->toHaveKey('id');
    });

    test('o payload usa uuid e nenhum id sequencial', function (): void {
        Partner::factory()->create();

        $row = $this->actingAs(userWithRole(Role::Direcao->value))->getJson('/api/v1/partners')->json('data.0');

        expect($row['uuid'])->toBeString()->and($row)->not->toHaveKeys(['id', 'media_id']);
    });
});

describe('permissões', function (): void {
    test('direcao, comunicacao e super_admin gerenciam', function (string $role): void {
        $user = userWithRole($role);
        $partner = Partner::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/partners')->assertOk();
        $this->actingAs($user)->getJson("/api/v1/partners/{$partner->uuid}")->assertOk();
        createPartner($user)->assertCreated();
        $this->actingAs($user)->deleteJson("/api/v1/partners/{$partner->uuid}")->assertNoContent();
    })->with([Role::Direcao->value, Role::Comunicacao->value, Role::SuperAdmin->value]);

    test('os demais papéis levam 403 em tudo', function (string $role): void {
        $user = userWithRole($role);
        $partner = Partner::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/partners')->assertForbidden();
        $this->actingAs($user)->getJson("/api/v1/partners/{$partner->uuid}")->assertForbidden();
        createPartner($user)->assertForbidden();
        $this->actingAs($user)->post("/api/v1/partners/{$partner->uuid}", ['_method' => 'PUT', 'name' => 'X'], ['Accept' => 'application/json'])->assertForbidden();
        $this->actingAs($user)->deleteJson("/api/v1/partners/{$partner->uuid}")->assertForbidden();

        expect(Partner::query()->count())->toBe(1);
    })->with([Role::Financeiro->value, Role::Contraturno->value, Role::Bazar->value, Role::Atendimento->value]);

    test('o mapa de acesso do usuário inclui partners', function (): void {
        $this->actingAs(userWithRole(Role::Comunicacao->value))->getJson('/api/v1/auth/user')
            ->assertJsonPath('data.access.partners', true);
        $this->actingAs(userWithRole(Role::Financeiro->value))->getJson('/api/v1/auth/user')
            ->assertJsonPath('data.access.partners', false);
    });
});
