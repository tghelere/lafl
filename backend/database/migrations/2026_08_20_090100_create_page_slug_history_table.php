<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('page_slug_history', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('page_id')->constrained('pages')->cascadeOnDelete();

            // Único globalmente: duas páginas não podem ter ambas reivindicado o mesmo
            // slug antigo, ou o redirect 301 fica ambíguo.
            $table->string('slug')->unique();

            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_slug_history');
    }
};
