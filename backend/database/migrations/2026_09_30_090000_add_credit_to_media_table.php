<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crédito da imagem (quem fotografou, ou de onde veio), mostrado na ampliação do site junto
     * da legenda. Opcional: a maior parte das fotos da instituição é da própria equipe.
     */
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->string('credit', 255)->nullable()->after('caption');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->dropColumn('credit');
        });
    }
};
