<?php

declare(strict_types=1);

use App\Support\Migrations\FormSubmissionColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Formulário de contato geral — titular é o próprio visitante (adulto).
     */
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table): void {
            FormSubmissionColumns::addCommon($table);

            $table->text('name')->comment('Cifrado.');
            $table->text('email')->comment('Cifrado.');
            $table->string('subject');
            $table->text('message')->comment('Cifrado.');
        });

        FormSubmissionColumns::addStatusCheckConstraint('contact_messages');
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
