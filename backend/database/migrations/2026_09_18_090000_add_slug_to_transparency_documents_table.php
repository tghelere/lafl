<?php

declare(strict_types=1);

use App\Support\Transparency\DocumentSlug;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Slug legível e estável de cada documento — é ele que dá ao PDF uma URL indexável
     * (/transparencia/documentos/{ano}/{slug}.pdf) no lugar da rota por uuid, que era o pior
     * formato possível para busca (ver docs/tarefas/06-seo-e-pdfs-da-transparencia.md).
     *
     * A coluna nasce NOT NULL: documento sem slug não tem URL pública, e deixar a coluna
     * aceitar null só adiantaria o problema para o dia em que alguém criasse um registro por
     * fora de App\Actions\Transparency\SaveTransparencyDocument.
     *
     * O preenchimento dos documentos que já existem (a homologação tem acervo cadastrado pela
     * instituição) usa App\Support\Transparency\DocumentSlug, a MESMA classe que a Action usa
     * — não uma cópia da regra aqui dentro, que é o que o CLAUDE.md proíbe. Num banco novo o
     * laço não roda nenhuma vez (a tabela acabou de ser criada e está vazia), então
     * `migrate:fresh` não depende da classe existir.
     */
    public function up(): void
    {
        Schema::table('transparency_documents', function (Blueprint $table): void {
            $table->string('slug')->nullable()->after('title');
        });

        DB::table('transparency_documents')
            ->select(['id', 'title'])
            ->orderBy('id')
            ->each(function (object $row): void {
                DB::table('transparency_documents')
                    ->where('id', $row->id)
                    ->update(['slug' => DocumentSlug::unique((string) $row->title)]);
            });

        Schema::table('transparency_documents', function (Blueprint $table): void {
            $table->string('slug')->nullable(false)->change();
            $table->unique('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transparency_documents', function (Blueprint $table): void {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
