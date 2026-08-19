<?php

declare(strict_types=1);

use App\Casts\FieldEncrypted;
use App\Models\Concerns\HasBlindIndex;
use App\Services\BlindIndexService;
use App\Support\StringNormalizer;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Model de exemplo, só para este teste — a infraestrutura de proteção de dados ainda não se
 * aplica a nenhuma entidade real de domínio (ver plano de bootstrap, etapa 4). A tabela é
 * criada e destruída aqui mesmo, sem migration em database/migrations/.
 */
function makeExampleEncryptedRecordModel(): Model
{
    return new class extends Model
    {
        protected $table = 'example_encrypted_records';

        protected $guarded = [];

        public $timestamps = false;

        use HasBlindIndex;

        protected array $blindIndexes = ['name' => 'name_hash'];

        protected function casts(): array
        {
            return [
                'name' => FieldEncrypted::class,
            ];
        }
    };
}

beforeEach(function (): void {
    Schema::dropIfExists('example_encrypted_records');
    Schema::create('example_encrypted_records', function ($table): void {
        $table->id();
        $table->text('name')->nullable();
        $table->char('name_hash', 64)->nullable();
        $table->text('notes')->nullable();
    });
});

afterEach(function (): void {
    Schema::dropIfExists('example_encrypted_records');
});

test('FieldEncrypted cifra no banco e decifra de volta ao ler o model', function (): void {
    $model = makeExampleEncryptedRecordModel();
    $model->name = 'Maria da Silva';
    $model->save();

    $raw = DB::table('example_encrypted_records')->value('name');

    expect($raw)->not->toBe('Maria da Silva')
        ->and($raw)->not->toContain('Maria');

    $fresh = makeExampleEncryptedRecordModel()->newQuery()->find($model->id);
    expect($fresh->name)->toBe('Maria da Silva');
});

test('FieldEncrypted usa uma chave diferente do APP_KEY', function (): void {
    $model = makeExampleEncryptedRecordModel();
    $model->name = 'Maria da Silva';
    $model->save();

    $raw = DB::table('example_encrypted_records')->value('name');

    expect(fn () => Crypt::decryptString($raw))->toThrow(DecryptException::class);
});

test('HasBlindIndex sincroniza o hash automaticamente ao criar e atualizar', function (): void {
    $model = makeExampleEncryptedRecordModel();
    $model->name = 'Maria da Silva';
    $model->save();

    $expectedHash = app(BlindIndexService::class)->hash(StringNormalizer::normalize('Maria da Silva'));

    expect($model->name_hash)->toBe($expectedHash);

    $model->name = 'Maria de Souza';
    $model->save();

    $expectedHashAfterUpdate = app(BlindIndexService::class)->hash(StringNormalizer::normalize('Maria de Souza'));

    expect($model->name_hash)
        ->toBe($expectedHashAfterUpdate)
        ->not->toBe($expectedHash);
});

test('HasBlindIndex mantém o hash nulo quando o campo é nulo', function (): void {
    $model = makeExampleEncryptedRecordModel();
    $model->name = null;
    $model->save();

    expect($model->name_hash)->toBeNull();
});

test('HasBlindIndex não corrompe o hash quando o model foi carregado com select() parcial sem o campo cifrado', function (): void {
    $model = makeExampleEncryptedRecordModel();
    $model->name = 'Maria da Silva';
    $model->save();

    $originalHash = DB::table('example_encrypted_records')->where('id', $model->id)->value('name_hash');

    $partial = makeExampleEncryptedRecordModel()->newQuery()
        ->select(['id', 'notes'])
        ->find($model->id);

    $partial->notes = 'observação qualquer';
    $partial->save();

    $hashAfterSave = DB::table('example_encrypted_records')->where('id', $model->id)->value('name_hash');

    expect($hashAfterSave)->toBe($originalHash)->not->toBeNull();
});

test('HasBlindIndex recalcula o hash normalmente quando o campo é alterado num model completo', function (): void {
    $model = makeExampleEncryptedRecordModel();
    $model->name = 'Maria da Silva';
    $model->save();

    $firstHash = $model->name_hash;

    $model->name = 'Maria de Souza';
    $model->save();

    $expectedHash = app(BlindIndexService::class)->hash(StringNormalizer::normalize('Maria de Souza'));

    expect($model->name_hash)
        ->toBe($expectedHash)
        ->not->toBe($firstHash);
});

test('HasBlindIndex zera o hash quando o campo é definido explicitamente como null', function (): void {
    $model = makeExampleEncryptedRecordModel();
    $model->name = 'Maria da Silva';
    $model->save();

    $model->name = null;
    $model->save();

    expect($model->name_hash)->toBeNull();
});
