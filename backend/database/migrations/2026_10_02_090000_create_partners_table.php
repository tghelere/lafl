<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Parceiros exibidos na página pública de parceiros. Tabela nova e aditiva: nada existente
     * é tocado.
     *
     * A logo é uma imagem da biblioteca (`media`), e não um arquivo próprio: o upload já
     * remove EXIF, gera as derivadas webp e passa pela declaração de assistido. A cascata só
     * alcança parceiro na lixeira — a imagem em uso por parceiro ativo nem chega a ser
     * excluída (App\Actions\Media\DeleteMedia recusa antes).
     */
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 150);
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->string('url', 2048)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('media_id');
            // A leitura pública: só ativos, na ordem.
            $table->index(['is_active', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partners');
    }
};
