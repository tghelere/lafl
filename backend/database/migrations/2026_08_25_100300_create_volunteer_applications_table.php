<?php

declare(strict_types=1);

use App\Support\Migrations\FormSubmissionColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Candidatura de voluntariado — titular é o próprio voluntário (adulto).
     */
    public function up(): void
    {
        Schema::create('volunteer_applications', function (Blueprint $table): void {
            FormSubmissionColumns::addCommon($table);

            $table->text('name')->comment('Cifrado.');
            $table->text('phone')->comment('Cifrado.');
            $table->text('email')->comment('Cifrado.');
            $table->string('availability');
            $table->string('interest_area');
            $table->text('message')->nullable()->comment('Cifrado.');
        });

        FormSubmissionColumns::addStatusCheckConstraint('volunteer_applications');
    }

    public function down(): void
    {
        Schema::dropIfExists('volunteer_applications');
    }
};
