<?php

declare(strict_types=1);

use App\Enums\PageStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table): void {
            $table->id();
            // Identificador público — rotas e payloads usam apenas o uuid (ver CLAUDE.md).
            $table->uuid('uuid')->unique();

            // Path completo, com barra para subpáginas (ex.: "quem-somos/nossa-historia").
            // Tabela permanece plana — sem parent_id — por decisão registrada em
            // docs/roadmap.md; SavePage valida em Action que o slug tem no máximo dois
            // níveis e que, havendo dois, o primeiro corresponde ao slug de uma página
            // existente.
            $table->string('slug')->unique();

            $table->string('title');
            // Conteúdo em texto/HTML, sem sanitização nesta fatia — não há editor rico
            // ainda (ver docs/roadmap.md); só usuário autenticado do painel escreve aqui.
            $table->text('content');

            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            // og_image_id fica para uma migration futura, junto da entidade media — ainda
            // não existe e a coluna não teria uso funcional possível (ver docs/roadmap.md).

            $table->string('status')->default(PageStatus::Draft->value)
                ->comment('Enum App\Enums\PageStatus — espelhado em CHECK no Postgres.');
            $table->timestamp('published_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        // CHECK espelhando o enum PHP (ver docs/convencoes.md). Sem guarda de driver:
        // PostgreSQL é o único banco suportado, em desenvolvimento, teste e produção (ver
        // CLAUDE.md, "Armadilhas conhecidas").
        $values = implode(',', array_map(
            fn (PageStatus $status): string => "'{$status->value}'",
            PageStatus::cases(),
        ));

        DB::statement("ALTER TABLE pages ADD CONSTRAINT pages_status_check CHECK (status IN ({$values}))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
