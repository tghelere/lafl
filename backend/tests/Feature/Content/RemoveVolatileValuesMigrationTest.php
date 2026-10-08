<?php

declare(strict_types=1);

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;

function volatileValuesMigration(): Migration
{
    return require database_path('migrations/2026_10_08_090000_remove_volatile_values_from_site_pages.php');
}

function originalTransparencyContent(): string
{
    return '<p>O Lar Anália Franco mantém um acervo de prestação de contas — balanços, atas, editais e relatórios — hoje com {{documentos_transparencia}}.</p><p>Reunimos esses documentos nesta página para que qualquer pessoa consiga localizar o que precisa.</p><p>O convênio com a Secretaria Municipal de Educação de Londrina, que sustenta o Centro de Educação Infantil Anália Franco, também exige prestação de contas periódica. O repasse municipal previsto para 2026 é de R$ 2.819.892,84 (Termo de Colaboração 06/2022).</p><p>Acesse o <a href="/transparencia/documentos">acervo de documentos</a>, com filtro por ano e por tipo.</p>';
}

test('troca o texto original e é idempotente', function (): void {
    $page = Page::factory()->published()->create(['slug' => 'transparencia', 'content' => originalTransparencyContent()]);

    volatileValuesMigration()->up();
    $depois = $page->fresh()->content;
    volatileValuesMigration()->up();

    expect($depois)->not->toContain('2.819.892,84')->not->toContain('06/2022')
        ->and($depois)->toContain('também exige prestação de contas periódica.</p>')
        ->and($page->fresh()->content)->toBe($depois);
});

test('não toca em bloco que o Lar já editou', function (): void {
    $editado = '<p>Texto escrito pelo Lar, com R$ 1,00.</p>';
    $page = Page::factory()->published()->create(['slug' => 'transparencia', 'content' => $editado]);

    volatileValuesMigration()->up();

    expect($page->fresh()->content)->toBe($editado);
});

test('down devolve o texto original', function (): void {
    $page = Page::factory()->published()->create(['slug' => 'transparencia', 'content' => originalTransparencyContent()]);

    volatileValuesMigration()->up();
    volatileValuesMigration()->down();

    expect($page->fresh()->content)->toBe(originalTransparencyContent());
});
