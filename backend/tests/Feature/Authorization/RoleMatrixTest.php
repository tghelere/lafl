<?php

declare(strict_types=1);

use App\Enums\PageStatus;
use App\Enums\Role;
use App\Models\Page;

/**
 * Matriz de papéis × recurso administrativo (ver docs/dominio.md, seção "Papéis") — um
 * `viewAny` por recurso, por papel. `direcao` acessa tudo; cada papel de área soma por cima
 * dela (ver App\Policies\FormSubmissionPolicy e subclasses, App\Policies\PagePolicy,
 * App\Policies\TransparencyDocumentPolicy). `super_admin` nunca aparece nas Policies — passa
 * por bypass total via Gate::before em AppServiceProvider — por isso entra aqui como "sempre
 * permitido" em vez de ler a lista `allowed` de cada recurso.
 *
 * @return array<string, array{endpoint: string, allowed: list<Role>}>
 */
function roleMatrixResources(): array
{
    return [
        'program-applications' => [
            'endpoint' => '/api/v1/program-applications',
            'allowed' => [Role::Direcao, Role::Contraturno],
        ],
        'pickup-requests' => [
            'endpoint' => '/api/v1/pickup-requests',
            'allowed' => [Role::Direcao, Role::Bazar],
        ],
        'volunteer-applications' => [
            'endpoint' => '/api/v1/volunteer-applications',
            'allowed' => [Role::Direcao, Role::Atendimento],
        ],
        'partnership-inquiries' => [
            'endpoint' => '/api/v1/partnership-inquiries',
            'allowed' => [Role::Direcao, Role::Contraturno],
        ],
        'contact-messages' => [
            'endpoint' => '/api/v1/contact-messages',
            'allowed' => [Role::Direcao, Role::Atendimento],
        ],
        'transparency-documents' => [
            'endpoint' => '/api/v1/transparency-documents',
            'allowed' => [Role::Direcao, Role::Financeiro],
        ],
        'pages' => [
            'endpoint' => '/api/v1/pages',
            'allowed' => [Role::Direcao, Role::Comunicacao],
        ],
        // Único recurso sem nenhum papel de área — nem direcao (ver App\Policies\
        // UserPolicy). `allowed` vazio: toda linha deste recurso nega, exceto super_admin.
        'users' => [
            'endpoint' => '/api/v1/users',
            'allowed' => [],
        ],
    ];
}

dataset('role matrix', function (): Generator {
    $resources = roleMatrixResources();

    $rolesToTest = [
        Role::SuperAdmin,
        Role::Direcao,
        Role::Financeiro,
        Role::Contraturno,
        Role::Bazar,
        Role::Atendimento,
        Role::Comunicacao,
    ];

    foreach ($resources as $resourceKey => $config) {
        foreach ($rolesToTest as $role) {
            $shouldAllow = $role === Role::SuperAdmin || in_array($role, $config['allowed'], true);
            $verdict = $shouldAllow ? 'permite' : 'nega';

            yield "{$resourceKey} · {$role->value} · {$verdict}" => [$resourceKey, $role, $shouldAllow];
        }
    }
});

test('matriz de acesso por papel e recurso', function (string $resourceKey, Role $role, bool $shouldAllow): void {
    $endpoint = roleMatrixResources()[$resourceKey]['endpoint'];
    $user = userWithRole($role->value);

    $response = $this->actingAs($user)->getJson($endpoint);

    if ($shouldAllow) {
        $response->assertOk();
    } else {
        $response->assertForbidden();
    }
})->with('role matrix');

/**
 * A matriz acima cobre só `viewAny` (GET de listagem) por recurso — não distingue quem lê de
 * quem escreve. `pages` é o único recurso hoje em que isso importa: `comunicacao` lê e edita
 * página existente igual a `direcao`, mas não cria nem exclui (ver App\Policies\PagePolicy).
 * Testado à parte, por ability, com um papel de cada situação: acesso total (direcao), acesso
 * parcial (comunicacao) e nenhum acesso (bazar).
 *
 * @return array<string, mixed>
 */
function pagesPayload(): array
{
    return [
        'slug' => 'quem-somos',
        'title' => 'Quem Somos',
        'content' => '<p>Conteúdo institucional de teste.</p>',
        'status' => PageStatus::Draft->value,
    ];
}

dataset('pages create/update/delete por papel', [
    'direcao · create · permite' => [Role::Direcao, 'create', true],
    'direcao · update · permite' => [Role::Direcao, 'update', true],
    'direcao · delete · permite' => [Role::Direcao, 'delete', true],
    'comunicacao · create · nega' => [Role::Comunicacao, 'create', false],
    'comunicacao · update · permite' => [Role::Comunicacao, 'update', true],
    'comunicacao · delete · nega' => [Role::Comunicacao, 'delete', false],
    'bazar · create · nega' => [Role::Bazar, 'create', false],
    'bazar · update · nega' => [Role::Bazar, 'update', false],
    'bazar · delete · nega' => [Role::Bazar, 'delete', false],
]);

test('pages: create/update/delete por papel', function (Role $role, string $ability, bool $shouldAllow): void {
    $user = userWithRole($role->value);

    $response = match ($ability) {
        'create' => $this->actingAs($user)->postJson('/api/v1/pages', pagesPayload()),
        'update' => $this->actingAs($user)->putJson(
            '/api/v1/pages/'.Page::factory()->create()->uuid,
            pagesPayload(),
        ),
        'delete' => $this->actingAs($user)->deleteJson(
            '/api/v1/pages/'.Page::factory()->create()->uuid,
        ),
    };

    if ($shouldAllow) {
        $response->assertSuccessful();
    } else {
        $response->assertForbidden();
    }
})->with('pages create/update/delete por papel');

test('usuário com dois papéis soma os acessos de cada um', function (): void {
    $user = userWithRole(Role::Financeiro->value);
    $user->assignRole(Role::Contraturno->value);

    // Acessos ganhos por financeiro.
    $this->actingAs($user)->getJson('/api/v1/transparency-documents')->assertOk();

    // Acessos ganhos por contraturno.
    $this->actingAs($user)->getJson('/api/v1/program-applications')->assertOk();
    $this->actingAs($user)->getJson('/api/v1/partnership-inquiries')->assertOk();

    // Nenhum dos dois papéis dá acesso a isto.
    $this->actingAs($user)->getJson('/api/v1/pickup-requests')->assertForbidden();
    $this->actingAs($user)->getJson('/api/v1/contact-messages')->assertForbidden();
    $this->actingAs($user)->getJson('/api/v1/volunteer-applications')->assertForbidden();
    $this->actingAs($user)->getJson('/api/v1/pages')->assertForbidden();
});
