<?php

declare(strict_types=1);

use App\Enums\ContentMarker;
use App\Enums\Role;
use App\Models\Page;
use App\Models\TransparencyDocument;
use App\Support\Html\ContentSanitizer;
use Carbon\CarbonImmutable;

test('o sanitizador não encosta num marcador', function (): void {
    // A premissa de tudo: `{` e `}` não são sintaxe de HTML, então o marcador atravessa a
    // allowlist do backend — e, pelo mesmo motivo, o editor Tiptap do painel.
    $html = '<p>Hoje com {{documentos_transparencia}}, há {{idade_bazar}} de bazar.</p>';

    expect(app(ContentSanitizer::class)->sanitize($html))->toBe($html);
});

test('o site recebe o marcador já resolvido', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-09-17 12:00:00', 'America/Sao_Paulo'));
    TransparencyDocument::factory()->published()->count(3)->create();

    Page::factory()->published()->create([
        'slug' => 'transparencia',
        'content' => '<p>Acervo com {{documentos_transparencia}}, mantido há {{idade_associacao}}.</p>',
    ]);

    $this->getJson('/api/v1/public/pages/transparencia')
        ->assertOk()
        ->assertJsonPath('data.content', '<p>Acervo com 3 documentos, mantido há 73 anos.</p>');
});

test('o painel recebe o marcador cru, nunca o valor', function (): void {
    // A regressão que mataria o cálculo em silêncio: se o painel recebesse "73 anos", o
    // primeiro salvamento gravaria esse texto e a página diria 73 para sempre.
    TransparencyDocument::factory()->published()->create();

    $user = userWithRole(Role::Comunicacao->value);
    $page = Page::factory()->published()->create([
        'content' => '<p>Acervo com {{documentos_transparencia}}.</p>',
    ]);

    $this->actingAs($user)->getJson("/api/v1/pages/{$page->uuid}")
        ->assertOk()
        ->assertJsonPath('data.content', '<p>Acervo com {{documentos_transparencia}}.</p>');

    $this->actingAs($user)->getJson('/api/v1/pages')
        ->assertOk()
        ->assertJsonPath('data.0.content', '<p>Acervo com {{documentos_transparencia}}.</p>');
});

test('salvar pelo painel devolve o marcador cru e grava cru', function (): void {
    $user = userWithRole(Role::Comunicacao->value);
    $page = Page::factory()->published()->create(['content' => '<p>Sem marcador.</p>']);

    $this->actingAs($user)->putJson("/api/v1/pages/{$page->uuid}", [
        'slug' => $page->slug,
        'title' => $page->title,
        'content' => '<p>Bazar há {{idade_bazar}}.</p>',
        'status' => $page->status->value,
    ])
        ->assertOk()
        ->assertJsonPath('data.content', '<p>Bazar há {{idade_bazar}}.</p>');

    expect($page->fresh()?->content)->toBe('<p>Bazar há {{idade_bazar}}.</p>');
});

test('marcador desconhecido é recusado ao salvar, com a lista dos válidos', function (): void {
    $user = userWithRole(Role::Comunicacao->value);
    $page = Page::factory()->published()->create(['content' => '<p>Original.</p>']);

    $response = $this->actingAs($user)->putJson("/api/v1/pages/{$page->uuid}", [
        'slug' => $page->slug,
        'title' => $page->title,
        'content' => '<p>A casa tem {{idade_da_casa}}.</p>',
        'status' => $page->status->value,
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('content');

    $mensagem = $response->json('errors.content.0');

    expect($mensagem)
        ->toContain('{{idade_da_casa}}')
        ->and($mensagem)->toContain('{{documentos_transparencia}}')
        ->and($mensagem)->toContain('{{idade_associacao}}');

    // E nada foi gravado.
    expect($page->fresh()?->content)->toBe('<p>Original.</p>');
});

test('marcador desconhecido também é recusado ao criar', function (): void {
    $user = userWithRole(Role::Direcao->value);

    $this->actingAs($user)->postJson('/api/v1/pages', [
        'slug' => 'pagina-nova',
        'title' => 'Página nova',
        'content' => '<p>{{coisa}}</p>',
        'status' => 'draft',
    ])->assertUnprocessable()->assertJsonValidationErrors('content');

    expect(Page::where('slug', 'pagina-nova')->exists())->toBeFalse();
});

test('marcador escrito com espaço em volta continua sendo o mesmo marcador', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-09-17 12:00:00', 'America/Sao_Paulo'));

    Page::factory()->published()->create([
        'slug' => 'bazar',
        'content' => '<p>Bazar há {{ idade_bazar }}.</p>',
    ]);

    $this->getJson('/api/v1/public/pages/bazar')
        ->assertOk()
        ->assertJsonPath('data.content', '<p>Bazar há 58 anos.</p>');
});

/**
 * O ponto central da tarefa: a página fica em cache por dez minutos (ver
 * App\Actions\Content\ResolvePublicPageBySlug), e mesmo assim publicar um documento tem de
 * aparecer na contagem já na requisição seguinte. Só passa se a resolução acontecer DEPOIS
 * do cache.
 */
test('publicar, despublicar e excluir documento mudam a contagem na requisição seguinte', function (): void {
    Page::factory()->published()->create([
        'slug' => 'transparencia',
        'content' => '<p>Acervo com {{documentos_transparencia}}.</p>',
    ]);

    $this->getJson('/api/v1/public/pages/transparencia')
        ->assertJsonPath('data.content', '<p>Acervo com 0 documentos.</p>');

    $documento = TransparencyDocument::factory()->published()->create();

    $this->getJson('/api/v1/public/pages/transparencia')
        ->assertJsonPath('data.content', '<p>Acervo com 1 documento.</p>');

    $documento->update(['published_at' => null]);

    $this->getJson('/api/v1/public/pages/transparencia')
        ->assertJsonPath('data.content', '<p>Acervo com 0 documentos.</p>');

    $documento->update(['published_at' => now()]);
    TransparencyDocument::factory()->published()->create();

    $this->getJson('/api/v1/public/pages/transparencia')
        ->assertJsonPath('data.content', '<p>Acervo com 2 documentos.</p>');

    $documento->delete();

    $this->getJson('/api/v1/public/pages/transparencia')
        ->assertJsonPath('data.content', '<p>Acervo com 1 documento.</p>');
});

test('página com marcador revalida a cada requisição; página sem marcador pode ser guardada', function (): void {
    Page::factory()->published()->create([
        'slug' => 'com-marcador',
        'content' => '<p>{{documentos_transparencia}}</p>',
    ]);
    Page::factory()->published()->create([
        'slug' => 'sem-marcador',
        'content' => '<p>Texto fixo.</p>',
    ]);

    $comMarcador = $this->getJson('/api/v1/public/pages/com-marcador');
    $semMarcador = $this->getJson('/api/v1/public/pages/sem-marcador');

    expect($comMarcador->headers->get('cache-control'))->toContain('must-revalidate')
        ->and($comMarcador->headers->get('cache-control'))->toContain('max-age=0')
        ->and($semMarcador->headers->get('cache-control'))->toContain('max-age=300');
});

test('o ETag muda quando o número muda, mesmo sem a página ser editada', function (): void {
    Page::factory()->published()->create([
        'slug' => 'transparencia',
        'content' => '<p>{{documentos_transparencia}}</p>',
    ]);

    $antes = $this->getJson('/api/v1/public/pages/transparencia')->headers->get('etag');

    TransparencyDocument::factory()->published()->create();

    $depois = $this->getJson('/api/v1/public/pages/transparencia')->headers->get('etag');

    expect($antes)->not->toBeNull()->and($depois)->not->toBe($antes);
});

test('marcador desconhecido que já esteja no banco vai cru para o site, não some', function (): void {
    // Não deveria existir — a escrita recusa. Se existir, aparecer é melhor que sumir com o
    // trecho em silêncio.
    Page::factory()->published()->create([
        'slug' => 'legado',
        'content' => '<p>{{coisa_que_nao_existe}}</p>',
    ]);

    $this->getJson('/api/v1/public/pages/legado')
        ->assertJsonPath('data.content', '<p>{{coisa_que_nao_existe}}</p>');
});

test('todo marcador declarado resolve para algo diferente de si mesmo', function (): void {
    $conteudo = implode(' ', ContentMarker::placeholders());

    Page::factory()->published()->create(['slug' => 'todos', 'content' => $conteudo]);

    $resolvido = $this->getJson('/api/v1/public/pages/todos')->json('data.content');

    foreach (ContentMarker::placeholders() as $placeholder) {
        expect($resolvido)->not->toContain($placeholder);
    }
});
