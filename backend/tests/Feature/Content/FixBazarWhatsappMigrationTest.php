<?php

declare(strict_types=1);

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

function bazarWhatsappMigration(): Migration
{
    return require database_path('migrations/2026_10_10_090000_fix_bazar_whatsapp_and_accepted_items.php');
}

function originalVisiteContent(): string
{
    return '<p>O Bazar Beneficente funciona em endereço próprio, separado da sede do Lar Anália Franco: Rua Rosa Siqueira, 152, Jd. Aeroporto, Londrina/PR. Telefone (43) 3322-2373 ou <a target="_blank" href="https://wa.me/5543999500183?text&#61;Ol%C3%A1!%20Gostaria%20de%20agendar%20uma%20coleta%20de%20doa%C3%A7%C3%A3o%20para%20o%20Bazar%20Beneficente." rel="noopener noreferrer">WhatsApp (43) 99950-0183</a>.</p>';
}

test('troca o número do WhatsApp e é idempotente', function (): void {
    $page = Page::factory()->published()->create(['slug' => 'bazar/visite-a-loja', 'content' => originalVisiteContent()]);

    bazarWhatsappMigration()->up();
    $depois = $page->fresh()->content;
    bazarWhatsappMigration()->up();

    expect($depois)->toContain('wa.me/5543999950183')->toContain('(43) 99995-0183')
        ->not->toContain('99950-0183')->not->toContain('5543999500183')
        ->and($depois)->toContain('(43) 3322-2373')
        ->and($page->fresh()->content)->toBe($depois);
});

test('não toca em bloco editado pelo Lar e registra warning', function (): void {
    Log::spy();
    $editado = '<p>Texto escrito pelo Lar.</p>';
    $page = Page::factory()->published()->create(['slug' => 'bazar/visite-a-loja', 'content' => $editado]);

    bazarWhatsappMigration()->up();

    expect($page->fresh()->content)->toBe($editado);
    Log::shouldHaveReceived('warning')->once();
});

test('down devolve o texto original', function (): void {
    $page = Page::factory()->published()->create(['slug' => 'bazar/visite-a-loja', 'content' => originalVisiteContent()]);

    bazarWhatsappMigration()->up();
    bazarWhatsappMigration()->down();

    expect($page->fresh()->content)->toBe(originalVisiteContent());
});
