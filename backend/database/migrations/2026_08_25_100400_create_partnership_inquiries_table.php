<?php

declare(strict_types=1);

use App\Support\Migrations\FormSubmissionColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Proposta de parceria empresarial — titular é o contato PJ (adulto). CNPJ com blind
     * index para checagem de duplicidade sem descriptografar (ver docs/protecao-de-dados.md).
     */
    public function up(): void
    {
        Schema::create('partnership_inquiries', function (Blueprint $table): void {
            FormSubmissionColumns::addCommon($table);

            // Razão social não é dado pessoal de pessoa física — texto puro (ver
            // docs/dominio.md, que só marca tax_id/contact_name/phone/email como cifrados).
            $table->string('company_name');
            $table->text('tax_id')->comment('Cifrado — CNPJ.');
            $table->char('tax_id_hash', 64)->index()->comment('HMAC do CNPJ normalizado, para checagem de duplicidade.');
            $table->text('contact_name')->comment('Cifrado.');
            $table->text('phone')->comment('Cifrado.');
            $table->text('email')->comment('Cifrado.');
            $table->string('support_type')->comment('Enum App\Enums\PartnershipSupportType.');
            // Sem cifra de propósito: diferente de enrollment/program, não há criança
            // envolvida nesta ponta (contato é sempre PJ/adulto) — ver docs/dominio.md.
            $table->text('message')->nullable();
        });

        FormSubmissionColumns::addStatusCheckConstraint('partnership_inquiries');
    }

    public function down(): void
    {
        Schema::dropIfExists('partnership_inquiries');
    }
};
