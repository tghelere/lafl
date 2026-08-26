<?php

declare(strict_types=1);

use App\Support\Migrations\FormSubmissionColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Manifestação de interesse na matrícula do CEI Tio Pedro — titular é o responsável
     * (adulto), nunca a criança (ver ADR 0007 e docs/estrutura-site.md §2.1).
     */
    public function up(): void
    {
        Schema::create('enrollment_interests', function (Blueprint $table): void {
            FormSubmissionColumns::addCommon($table);

            $table->text('guardian_name')->comment('Cifrado — nome do responsável.');
            $table->text('phone')->comment('Cifrado.');
            $table->text('email')->comment('Cifrado.');
            $table->string('child_age_range')
                ->comment('Enum App\Enums\ChildAgeRange — faixa etária, nunca data de nascimento.');
            $table->string('desired_period')->comment('Enum App\Enums\DesiredPeriod.');
            // Cifrado por precaução: o rótulo pede para não escrever o nome da criança, mas
            // alguém vai escrever mesmo assim (ver docs/dominio.md).
            $table->text('message')->nullable()->comment('Cifrado.');
        });

        FormSubmissionColumns::addStatusCheckConstraint('enrollment_interests');
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_interests');
    }
};
