<?php

declare(strict_types=1);

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

function doarPixMigration(): Migration
{
    return require database_path('migrations/2026_10_10_150000_move_pix_out_of_doar_page_content.php');
}

function originalDoarContent(): string
{
    return '<p>O Lar Anália Franco ainda não processa doações em dinheiro diretamente pelo site. Doe por PIX ou transferência bancária:</p><ul><li><p><strong>PIX</strong> (chave CNPJ): 78.614.096/0001-75 — nome exibido: LAR ANALIA FRANCO DE LONDRINA</p></li><li><p><strong>Transferência:</strong> Banco do Brasil (001), agência 2755-3, conta corrente 4963-8</p></li></ul><p>Doações de itens para o Bazar Beneficente também sustentam diretamente a instituição.</p>';
}

test('tira a chave Pix e a frase antiga do corpo de /doar e é idempotente', function (): void {
    $page = Page::factory()->published()->create(['slug' => 'como-ajudar/doar', 'content' => originalDoarContent()]);

    doarPixMigration()->up();
    $depois = $page->fresh()->content;
    doarPixMigration()->up();

    expect($depois)->not->toContain('78.614.096')->not->toContain('não processa')
        ->toContain('não recebe nem guarda dados de pagamento')
        ->toContain('Banco do Brasil (001)')
        ->toContain('Bazar Beneficente')
        ->and($page->fresh()->content)->toBe($depois);
});

test('não toca em texto editado pelo Lar e registra warning', function (): void {
    Log::spy();
    $page = Page::factory()->published()->create(['slug' => 'como-ajudar/doar', 'content' => '<p>Texto do Lar.</p>']);

    doarPixMigration()->up();

    expect($page->fresh()->content)->toBe('<p>Texto do Lar.</p>');
    Log::shouldHaveReceived('warning')->once();
});

test('down devolve o texto original', function (): void {
    $page = Page::factory()->published()->create(['slug' => 'como-ajudar/doar', 'content' => originalDoarContent()]);

    doarPixMigration()->up();
    doarPixMigration()->down();

    expect($page->fresh()->content)->toBe(originalDoarContent());
});
