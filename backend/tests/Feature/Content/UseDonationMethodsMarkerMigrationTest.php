<?php

declare(strict_types=1);

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

function doarMarkerMigration(): Migration
{
    return require database_path('migrations/2026_10_10_180000_use_donation_methods_marker_in_doar_page.php');
}

function previousDoarContent(): string
{
    return '<p>A doação vai direto do seu banco para a conta do Lar Anália Franco: o site não recebe nem guarda dados de pagamento. Doe por Pix, no quadro acima, ou por transferência bancária:</p><ul><li><p><strong>Transferência:</strong> Banco do Brasil (001), agência 2755-3, conta corrente 4963-8</p></li></ul><p>Doações de itens para o Bazar Beneficente também sustentam diretamente a instituição.</p>';
}

test('troca o texto da migração anterior pelo marcador dos cartões e é idempotente', function (): void {
    $page = Page::factory()->published()->create(['slug' => 'como-ajudar/doar', 'content' => previousDoarContent()]);

    doarMarkerMigration()->up();
    $depois = $page->fresh()->content;
    doarMarkerMigration()->up();

    expect($depois)->toContain('<p>{{formas_de_doacao}}</p>')
        ->toStartWith('<p>O Lar Anália Franco ainda não processa doações')
        ->toEndWith('sustentam diretamente a instituição.</p>')
        ->not->toContain('<ul>')
        ->and($page->fresh()->content)->toBe($depois);
});

test('não toca em texto editado pelo Lar e registra warning', function (): void {
    Log::spy();
    $page = Page::factory()->published()->create(['slug' => 'como-ajudar/doar', 'content' => '<p>Texto do Lar.</p>']);

    doarMarkerMigration()->up();

    expect($page->fresh()->content)->toBe('<p>Texto do Lar.</p>');
    Log::shouldHaveReceived('warning')->once();
});

test('down devolve o texto da migração anterior', function (): void {
    $page = Page::factory()->published()->create(['slug' => 'como-ajudar/doar', 'content' => previousDoarContent()]);

    doarMarkerMigration()->up();
    doarMarkerMigration()->down();

    expect($page->fresh()->content)->toBe(previousDoarContent());
});
