<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * De qual foto do catálogo inicial (App\Support\Media\InitialPhotos) a imagem veio. Nulo
     * para tudo o que foi enviado pelo painel. Existe só para `midia:importar-fotos-iniciais`
     * reconhecer o que já importou e poder rodar de novo sem duplicar.
     */
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->string('origin_key', 100)->nullable()->unique()->after('uuid');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->dropUnique(['origin_key']);
            $table->dropColumn('origin_key');
        });
    }
};
