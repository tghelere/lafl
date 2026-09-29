<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * As imagens que uma página mostra FORA do texto: a capa e a galeria (ver
     * App\Enums\PageImageRole e docs/decisoes/0025-imagens-da-pagina.md).
     *
     * Só a ligação. Arquivo, texto alternativo e legenda são da imagem, em `media`, e não se
     * repetem aqui: trocar o arquivo ou corrigir o texto alternativo na biblioteca vale para
     * todas as páginas que a usam.
     */
    public function up(): void
    {
        Schema::create('page_images', function (Blueprint $table): void {
            $table->id();
            // Cascata dos dois lados. A página só some do banco na exclusão definitiva. A
            // imagem só pode ser excluída quando nenhuma página no ar a usa (DeleteMedia), e
            // a ligação com página na lixeira não deve impedir isso.
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->string('role', 20);
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            // Também serve de índice de `page_id` (coluna líder), que é por onde a página lê
            // as próprias imagens.
            $table->unique(['page_id', 'role', 'position']);
            $table->unique(['page_id', 'role', 'media_id']);
            $table->index('media_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_images');
    }
};
