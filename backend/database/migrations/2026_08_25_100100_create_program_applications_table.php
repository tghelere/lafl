<?php

declare(strict_types=1);

use App\Support\Migrations\FormSubmissionColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aviso de interesse na Escola de Contraturno — titular é o responsável (adulto), nunca a
     * criança ou adolescente (ver ADR 0007 e docs/estrutura-site.md §2.1). O programa ainda
     * não abriu inscrições (ver docs/contexto.md); por isso o formulário só coleta contato
     * para avisar quando abrirem, nenhum dado sobre a criança ou adolescente.
     */
    public function up(): void
    {
        Schema::create('program_applications', function (Blueprint $table): void {
            FormSubmissionColumns::addCommon($table);

            $table->text('guardian_name')->comment('Cifrado — nome do responsável.');
            $table->text('phone')->comment('Cifrado.');
        });

        FormSubmissionColumns::addStatusCheckConstraint('program_applications');
    }

    public function down(): void
    {
        Schema::dropIfExists('program_applications');
    }
};
