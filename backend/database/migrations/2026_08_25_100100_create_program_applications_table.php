<?php

declare(strict_types=1);

use App\Support\Migrations\FormSubmissionColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Inscrição na Escola de Contraturno — titular é o responsável (adulto), nunca o
     * adolescente (ver ADR 0007 e docs/estrutura-site.md §2.1).
     */
    public function up(): void
    {
        Schema::create('program_applications', function (Blueprint $table): void {
            FormSubmissionColumns::addCommon($table);

            $table->text('guardian_name')->comment('Cifrado — nome do responsável.');
            $table->text('phone')->comment('Cifrado.');
            $table->text('email')->comment('Cifrado.');
            // Idade em número inteiro, nunca data de nascimento (ver docs/dominio.md) —
            // não identifica o dia exato de nascimento do adolescente.
            $table->unsignedTinyInteger('teen_age');
            // Rótulo e obrigatoriedade [VALIDAR] com a instituição (ver docs/roadmap.md).
            $table->text('school')->nullable()->comment('Cifrado.');
            $table->text('message')->nullable()->comment('Cifrado — por precaução, ver docs/dominio.md.');
        });

        FormSubmissionColumns::addStatusCheckConstraint('program_applications');
    }

    public function down(): void
    {
        Schema::dropIfExists('program_applications');
    }
};
