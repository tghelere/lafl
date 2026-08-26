<?php

declare(strict_types=1);

namespace App\Support\Migrations;

use App\Enums\FormSubmissionStatus;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

/**
 * Colunas comuns às seis migrations de formulário recebido (ver docs/dominio.md,
 * "Formulários recebidos"). Cada migration chama `addCommon()` antes de suas colunas
 * específicas, e `addStatusCheckConstraint()` depois de `Schema::create` (só tem efeito no
 * Postgres — mesma disciplina de `pages` e `transparency_documents`).
 */
final class FormSubmissionColumns
{
    public static function addCommon(Blueprint $table): void
    {
        $table->id();
        // Identificador público — rotas e payloads usam apenas o uuid (ver CLAUDE.md). Não há
        // rota pública de leitura nesta sessão (só POST de criação), mas o uuid já entra
        // preparado para quando a leitura administrativa existir.
        $table->uuid('uuid')->unique();

        $table->string('status')->default(FormSubmissionStatus::New->value)
            ->comment('Enum App\Enums\FormSubmissionStatus — espelhado em CHECK no Postgres.');

        // Consentimento: registro no banco, não pasta de papel (ver docs/protecao-de-dados.md).
        $table->string('consent_terms_version');
        $table->timestamp('consented_at');

        // HMAC do IP de origem — nunca o IP em texto puro (ver App\Services\BlindIndexService).
        $table->char('ip_hash', 64)->index();

        $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamp('handled_at')->nullable();
        $table->text('internal_note')->nullable()->comment('Cifrado — App\Casts\FieldEncrypted.');

        $table->timestamp('expires_at')->index();
    }

    public static function addStatusCheckConstraint(string $table): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            // SQLite (teste local, ver phpunit.xml) não suporta ALTER TABLE ... ADD
            // CONSTRAINT da mesma forma, e o teste local não precisa da trava redundante que
            // o enum PHP já garante na aplicação.
            return;
        }

        $values = implode(',', array_map(
            fn (FormSubmissionStatus $status): string => "'{$status->value}'",
            FormSubmissionStatus::cases(),
        ));

        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_status_check CHECK (status IN ({$values}))");
    }
}
