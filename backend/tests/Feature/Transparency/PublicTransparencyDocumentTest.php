<?php

declare(strict_types=1);

use App\Enums\TransparencyDocumentType;
use App\Models\TransparencyDocument;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** User-Agent de gente, para os casos em que a contagem de download precisa somar. */
const UA_NAVEGADOR = 'Mozilla/5.0 (X11; Linux x86_64; rv:141.0) Gecko/20100101 Firefox/141.0';

beforeEach(function (): void {
    Storage::fake('local');
});

/**
 * Documento publicado com arquivo de verdade no disco falso — a rota do PDF só serve o que
 * existe nos dois lugares (linha no banco e arquivo no disco).
 *
 * @param  array<string, mixed>  $attributes
 */
function documentoComArquivo(array $attributes = []): TransparencyDocument
{
    $path = 'transparency-documents/'.Str::uuid().'.pdf';
    Storage::disk('local')->put($path, '%PDF-1.4 conteudo de teste');

    return TransparencyDocument::factory()->published()->create([...$attributes, 'file_path' => $path]);
}

test('endpoint público lista só documentos publicados', function (): void {
    TransparencyDocument::factory()->published()->create(['title' => 'Publicado']);
    TransparencyDocument::factory()->create(['title' => 'Rascunho']);

    $response = $this->getJson('/api/v1/public/transparency-documents');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.title'))->toBe('Publicado');
});

test('endpoint público filtra por ano', function (): void {
    TransparencyDocument::factory()->published()->create(['title' => '2023', 'year' => 2023]);
    TransparencyDocument::factory()->published()->create(['title' => '2024', 'year' => 2024]);

    $response = $this->getJson('/api/v1/public/transparency-documents?year=2024');

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.title'))->toBe('2024');
});

test('endpoint público filtra por tipo', function (): void {
    TransparencyDocument::factory()->published()->create(['title' => 'Balanço', 'type' => TransparencyDocumentType::Balance]);
    TransparencyDocument::factory()->published()->create(['title' => 'Estatuto', 'type' => TransparencyDocumentType::Bylaws]);

    $response = $this->getJson('/api/v1/public/transparency-documents?type=bylaws');

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.title'))->toBe('Estatuto');
});

test('tipo desconhecido no filtro é ignorado, não gera erro', function (): void {
    TransparencyDocument::factory()->published()->create();

    $this->getJson('/api/v1/public/transparency-documents?type=nao-existe')
        ->assertOk();
});

test('resource público nunca expõe file_path', function (): void {
    TransparencyDocument::factory()->published()->create();

    $this->getJson('/api/v1/public/transparency-documents')
        ->assertOk()
        ->assertJsonMissingPath('data.0.file_path');
});

test('resource público entrega o caminho legível do PDF, montado pela API', function (): void {
    TransparencyDocument::factory()->published()->create([
        'title' => 'Balanço patrimonial 2024',
        'slug' => 'balanco-patrimonial-2024',
        'year' => 2024,
    ]);

    $this->getJson('/api/v1/public/transparency-documents')
        ->assertOk()
        ->assertJsonPath('data.0.path', '/transparencia/documentos/2024/balanco-patrimonial-2024.pdf');
});

test('a URL legível serve o PDF inline, com nome de arquivo descritivo', function (): void {
    documentoComArquivo(['slug' => 'balanco-patrimonial-2024', 'year' => 2024]);

    $response = $this->get('/api/v1/public/transparency-documents/2024/balanco-patrimonial-2024.pdf', [
        'User-Agent' => UA_NAVEGADOR,
    ]);

    $response->assertOk();
    expect($response->headers->get('content-type'))->toBe('application/pdf');
    expect($response->headers->get('content-disposition'))
        ->toStartWith('inline')
        ->toContain('balanco-patrimonial-2024.pdf');
    expect($response->headers->get('cache-control'))->toContain('max-age=3600');
});

test('a URL legível soma a contagem de download', function (): void {
    $document = documentoComArquivo(['slug' => 'estatuto-social', 'year' => 2022, 'download_count' => 0]);

    $this->get('/api/v1/public/transparency-documents/2022/estatuto-social.pdf', ['User-Agent' => UA_NAVEGADOR])
        ->assertOk();

    expect($document->fresh()->download_count)->toBe(1);
});

test('acesso de robô conhecido não soma na contagem de download', function (string $userAgent): void {
    $document = documentoComArquivo(['slug' => 'estatuto-social', 'year' => 2022, 'download_count' => 0]);

    $this->get('/api/v1/public/transparency-documents/2022/estatuto-social.pdf', ['User-Agent' => $userAgent])
        ->assertOk();

    expect($document->fresh()->download_count)->toBe(0);
})->with([
    'Googlebot' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
    'Bingbot' => 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)',
    'WhatsApp' => 'WhatsApp/2.23.20.0',
    'GPTBot' => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; GPTBot/1.1; +https://openai.com/gptbot',
    'rastreador genérico' => 'MeuCrawler/1.0',
]);

// Navegador sempre manda User-Agent; o que chega sem ele é script, e conta como robô (ver
// App\Support\Http\KnownBots). O cliente HTTP do Pest sempre põe um User-Agent próprio, então
// o cabeçalho vazio é a forma de reproduzir aqui a ausência que só acontece em produção — os
// dois casos (null e string vazia) caem no mesmo ramo da classe.
test('requisição sem User-Agent não soma na contagem de download', function (): void {
    $document = documentoComArquivo(['slug' => 'estatuto-social', 'year' => 2022, 'download_count' => 0]);

    $this->get('/api/v1/public/transparency-documents/2022/estatuto-social.pdf', ['User-Agent' => ''])
        ->assertOk();

    expect($document->fresh()->download_count)->toBe(0);
});

test('ano diferente do atual responde 301 para a URL canônica', function (): void {
    documentoComArquivo(['slug' => 'estatuto-social', 'year' => 2022]);

    $this->get('/api/v1/public/transparency-documents/2019/estatuto-social.pdf', ['User-Agent' => UA_NAVEGADOR])
        ->assertMovedPermanently()
        ->assertRedirect(config('forms.site_base_url').'/transparencia/documentos/2022/estatuto-social.pdf');
});

test('301 de ano trocado não soma na contagem de download', function (): void {
    $document = documentoComArquivo(['slug' => 'estatuto-social', 'year' => 2022, 'download_count' => 0]);

    $this->get('/api/v1/public/transparency-documents/2019/estatuto-social.pdf', ['User-Agent' => UA_NAVEGADOR]);

    expect($document->fresh()->download_count)->toBe(0);
});

test('PDF de documento não publicado devolve 404', function (): void {
    $path = 'transparency-documents/rascunho.pdf';
    Storage::disk('local')->put($path, '%PDF-1.4');
    TransparencyDocument::factory()->create(['slug' => 'rascunho', 'year' => 2024, 'file_path' => $path]);

    $this->get('/api/v1/public/transparency-documents/2024/rascunho.pdf')->assertNotFound();
});

test('slug inexistente devolve 404', function (): void {
    $this->get('/api/v1/public/transparency-documents/2024/nao-existe.pdf')->assertNotFound();
});

test('documento cuja linha existe mas o arquivo sumiu do disco devolve 404, não erro', function (): void {
    TransparencyDocument::factory()->published()->create([
        'slug' => 'sem-arquivo',
        'year' => 2024,
        'file_path' => 'transparency-documents/apagado.pdf',
    ]);

    $this->get('/api/v1/public/transparency-documents/2024/sem-arquivo.pdf')->assertNotFound();
});

test('a URL antiga por uuid responde 301 para a URL legível', function (): void {
    $document = documentoComArquivo(['slug' => 'balanco-patrimonial-2024', 'year' => 2024]);

    $this->get("/api/v1/public/transparency-documents/{$document->uuid}/download")
        ->assertMovedPermanently()
        ->assertRedirect(config('forms.site_base_url').'/transparencia/documentos/2024/balanco-patrimonial-2024.pdf');
});

test('a URL antiga por uuid de documento não publicado devolve 404', function (): void {
    $document = TransparencyDocument::factory()->create();

    $this->get("/api/v1/public/transparency-documents/{$document->uuid}/download")
        ->assertNotFound();
});

test('a URL antiga por uuid inexistente devolve 404', function (): void {
    $this->get('/api/v1/public/transparency-documents/'.Str::uuid().'/download')
        ->assertNotFound();
});
