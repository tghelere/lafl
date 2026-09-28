<?php

declare(strict_types=1);

namespace App\Support\Migrations;

use App\Enums\FormSubmissionStatus;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

/**
 * Colunas comuns às migrations de formulário recebido (ver docs/dominio.md, "Formulários
 * recebidos"). Cada migration chama `addCommon()` e `addReadState()` antes de suas colunas
 * específicas, e `addStatusCheckConstraint()` depois de `Schema::create` (mesma disciplina de
 * `pages` e `transparency_documents`).
 *
 * `addReadState()` está separado porque as cinco tabelas atuais nasceram sem ele — as colunas
 * de leitura chegaram na sessão 25, por migration própria, que é quem o chama para elas. Uma
 * sexta tabela de formulário chama os dois.
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

        $table->string('status')->default(FormSubmissionStatus::initial()->value)
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

        $table->timestamps();
    }

    /**
     * Leitura compartilhada pela equipe — ver
     * docs/decisoes/0021-leitura-separada-do-status-de-atendimento.md. `read_at` NULL é "não
     * lido"; `read_by` é quem abriu primeiro.
     */
    public static function addReadState(Blueprint $table): void
    {
        $table->timestamp('read_at')->nullable()
            ->comment('Primeira abertura do detalhe, por qualquer pessoa da equipe. NULL = não lido.');

        // Índice explícito na FK — o Postgres não cria automaticamente (ver CLAUDE.md).
        $table->foreignId('read_by')->nullable()->constrained('users')->nullOnDelete();

        // O filtro "não lidos" e os contadores do menu e da tela Início consultam exatamente
        // isto, em toda navegação do painel.
        $table->index('read_at');
    }

    /**
     * CHECK espelhando o enum PHP no banco (ver docs/convencoes.md). Sem guarda de driver:
     * PostgreSQL é o único banco suportado, em desenvolvimento, teste e produção (ver
     * CLAUDE.md, "Armadilhas conhecidas").
     */
    public static function addStatusCheckConstraint(string $table): void
    {
        $values = implode(',', array_map(
            fn (FormSubmissionStatus $status): string => "'{$status->value}'",
            FormSubmissionStatus::cases(),
        ));

        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_status_check CHECK (status IN ({$values}))");
    }
}
