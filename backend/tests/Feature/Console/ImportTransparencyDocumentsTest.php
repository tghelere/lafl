<?php

declare(strict_types=1);

use App\Enums\TransparencyDocumentType;
use App\Models\TransparencyDocument;
use Database\Seeders\Support\PlaceholderPdf;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    $this->folder = sys_get_temp_dir().'/laf-lote-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($this->folder);
});

afterEach(function (): void {
    File::deleteDirectory($this->folder);
});

/**
 * @param  list<array{arquivo: string, titulo: string, tipo?: string, ano?: int}>  $docs
 * @param  array<string, string>  $contents  conteúdo por arquivo (padrão: PDF mínimo)
 */
function writeBatch(string $folder, array $docs, array $contents = [], ?int $total = null): void
{
    foreach ($docs as $doc) {
        file_put_contents($folder.'/'.$doc['arquivo'], $contents[$doc['arquivo']] ?? PlaceholderPdf::bytes());
    }

    file_put_contents($folder.'/manifesto.json', json_encode([
        'lote' => 'teste',
        'total' => $total ?? count($docs),
        'documentos' => array_map(fn (array $doc): array => $doc + [
            'tipo' => 'ata',
            'ano' => 2024,
            'data_documento' => '2024-03-01',
            'descricao' => 'x',
            'publicar' => true,
        ], $docs),
    ]));
}

function twoDocs(): array
{
    return [
        ['arquivo' => 'a.pdf', 'titulo' => 'Ata A', 'tipo' => 'ata'],
        ['arquivo' => 'b.pdf', 'titulo' => 'Aditivo B', 'tipo' => 'termo_aditivo'],
    ];
}

test('simulação mostra a tabela e não grava nada', function (): void {
    writeBatch($this->folder, twoDocs());

    $this->artisan('transparencia:importar', ['pasta' => $this->folder])
        ->expectsOutputToContain('Simulação')
        ->expectsOutputToContain('Ata A')
        ->assertSuccessful();

    expect(TransparencyDocument::withTrashed()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

test('--executar cria os documentos pelo mesmo caminho do painel', function (): void {
    writeBatch($this->folder, twoDocs());
    $original = file_get_contents($this->folder.'/a.pdf');

    $this->artisan('transparencia:importar', ['pasta' => $this->folder, '--executar' => true])->assertSuccessful();

    $ata = TransparencyDocument::query()->where('title', 'Ata A')->firstOrFail();
    $aditivo = TransparencyDocument::query()->where('title', 'Aditivo B')->firstOrFail();

    expect($ata->type)->toBe(TransparencyDocumentType::Minutes)
        ->and($aditivo->type)->toBe(TransparencyDocumentType::AgreementAccounting)
        ->and($ata->year)->toBe(2024)
        ->and($ata->published_at)->not->toBeNull()
        ->and($ata->slug)->not->toBe('')
        ->and($ata->file_path)->toStartWith('transparency-documents/')
        ->and(Storage::disk('local')->get($ata->file_path))->toBe($original)
        ->and(file_get_contents($this->folder.'/a.pdf'))->toBe($original);
});

test('segunda execução pula tudo e não cria nada', function (): void {
    writeBatch($this->folder, twoDocs());
    $this->artisan('transparencia:importar', ['pasta' => $this->folder, '--executar' => true])->assertSuccessful();
    $files = Storage::disk('local')->allFiles();

    $this->artisan('transparencia:importar', ['pasta' => $this->folder, '--executar' => true])
        ->expectsOutputToContain('já existia')
        ->assertSuccessful();

    expect(TransparencyDocument::count())->toBe(2)
        ->and(Storage::disk('local')->allFiles())->toBe($files);
});

test('arquivo ausente aborta sem gravar nada', function (): void {
    writeBatch($this->folder, twoDocs());
    unlink($this->folder.'/b.pdf');

    $this->artisan('transparencia:importar', ['pasta' => $this->folder, '--executar' => true])
        ->expectsOutputToContain('Arquivo ausente: b.pdf')
        ->assertFailed();

    expect(TransparencyDocument::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

test('total que não confere aborta sem gravar nada', function (): void {
    writeBatch($this->folder, twoDocs(), total: 3);

    $this->artisan('transparencia:importar', ['pasta' => $this->folder, '--executar' => true])->assertFailed();

    expect(TransparencyDocument::count())->toBe(0);
});

test('erro de validação do Request aborta o lote inteiro', function (): void {
    writeBatch($this->folder, twoDocs(), ['b.pdf' => 'isto não é um pdf']);

    $this->artisan('transparencia:importar', ['pasta' => $this->folder, '--executar' => true])
        ->expectsOutputToContain('O documento precisa ser um PDF.')
        ->assertFailed();

    expect(TransparencyDocument::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

test('dois arquivos de conteúdo idêntico com títulos diferentes são ambos criados', function (): void {
    $bytes = PlaceholderPdf::bytes();
    writeBatch($this->folder, [
        ['arquivo' => 'um.pdf', 'titulo' => 'Aditivo um'],
        ['arquivo' => 'dois.pdf', 'titulo' => 'Aditivo dois'],
    ], ['um.pdf' => $bytes, 'dois.pdf' => $bytes]);

    $this->artisan('transparencia:importar', ['pasta' => $this->folder, '--executar' => true])->assertSuccessful();

    expect(TransparencyDocument::count())->toBe(2)
        ->and(TransparencyDocument::query()->distinct()->count('file_path'))->toBe(2);
});

test('documentos já existentes continuam intactos', function (): void {
    $existing = TransparencyDocument::factory()->create(['title' => 'Balanço antigo', 'year' => 2020]);
    Storage::disk('local')->put($existing->file_path, 'conteudo-antigo');
    $before = $existing->fresh()->only(['id', 'uuid', 'title', 'slug', 'year', 'type', 'file_path', 'file_size', 'published_at', 'updated_at']);

    writeBatch($this->folder, twoDocs());
    $this->artisan('transparencia:importar', ['pasta' => $this->folder, '--executar' => true])->assertSuccessful();

    expect(TransparencyDocument::count())->toBe(3)
        ->and($existing->fresh()->only(array_keys($before)))->toEqual($before)
        ->and(Storage::disk('local')->get($existing->file_path))->toBe('conteudo-antigo');
});

test('falha no meio desfaz o lote e apaga só os arquivos desta execução', function (): void {
    $existing = TransparencyDocument::factory()->create();
    Storage::disk('local')->put($existing->file_path, 'conteudo-antigo');

    writeBatch($this->folder, twoDocs());
    // O segundo item falha ao gravar: força colisão de slug fora do caminho normal.
    $calls = 0;
    TransparencyDocument::creating(function () use (&$calls): void {
        if (++$calls === 2) {
            throw new RuntimeException('falha simulada');
        }
    });

    $this->artisan('transparencia:importar', ['pasta' => $this->folder, '--executar' => true])
        ->expectsOutputToContain('falha simulada')
        ->assertFailed();

    expect(TransparencyDocument::count())->toBe(1)
        ->and(Storage::disk('local')->allFiles())->toBe([$existing->file_path]);
});
