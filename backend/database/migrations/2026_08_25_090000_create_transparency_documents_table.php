<?php

declare(strict_types=1);

use App\Enums\TransparencyDocumentType;
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
        Schema::create('transparency_documents', function (Blueprint $table): void {
            $table->id();
            // Identificador público — rotas e payloads usam apenas o uuid (ver CLAUDE.md).
            $table->uuid('uuid')->unique();

            $table->string('title');
            // Indexados: é por eles que a listagem pública é filtrada (ver
            // docs/estrutura-site.md §3.1, "Filtro por ano e tipo").
            $table->unsignedSmallInteger('year')->index();
            $table->string('type')->index()
                ->comment('Enum App\Enums\TransparencyDocumentType — espelhado em CHECK no Postgres.');

            // Caminho relativo no disco "local" (fora do webroot, ver
            // docs/protecao-de-dados.md, "Uploads de imagem" — mesmo princípio vale aqui:
            // nenhum documento tem URL pública direta, só a rota de download, que soma a
            // contagem antes de servir o arquivo).
            $table->string('file_path');
            $table->unsignedBigInteger('file_size')->comment('Bytes.');
            $table->unsignedBigInteger('download_count')->default(0);

            $table->timestamp('published_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        // CHECK espelhando o enum PHP (ver docs/convencoes.md). Sem guarda de driver:
        // PostgreSQL é o único banco suportado, em desenvolvimento, teste e produção (ver
        // CLAUDE.md, "Armadilhas conhecidas").
        $values = implode(',', array_map(
            fn (TransparencyDocumentType $type): string => "'{$type->value}'",
            TransparencyDocumentType::cases(),
        ));

        DB::statement("ALTER TABLE transparency_documents ADD CONSTRAINT transparency_documents_type_check CHECK (type IN ({$values}))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transparency_documents');
    }
};
