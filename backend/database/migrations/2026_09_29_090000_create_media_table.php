<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Biblioteca de imagens do conteúdo do site (ver docs/decisoes/0024-biblioteca-de-midia.md).
     *
     * Os arquivos ficam no disco "local", fora do webroot, em `media/{uuid}/{version}/`: a
     * original (sem metadado) e as derivadas webp. O caminho não é coluna — é derivado de uuid,
     * versão e extensão por App\Support\Media\MediaPaths, para que não exista caminho gravado
     * que possa apontar para fora da pasta da própria imagem.
     */
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table): void {
            $table->id();
            // Identificador público — rotas, payloads e a URL da imagem usam só o uuid.
            $table->uuid('uuid')->unique();

            $table->string('alt');
            $table->string('caption', 500)->nullable();

            // Declaração obrigatória no upload, sem valor padrão de propósito: quem sobe a
            // imagem responde. Enquanto não existe registro de consentimento de imagem, `true`
            // torna a imagem impublicável (ver o ADR acima e docs/protecao-de-dados.md).
            $table->boolean('depicts_assisted_minor');

            // Sobe a cada substituição do arquivo. A pasta dos arquivos é por versão: a
            // substituição grava a nova ao lado, troca o número numa transação e só depois
            // apaga a anterior — nunca há instante sem arquivo servível.
            $table->unsignedInteger('version')->default(1);

            $table->string('mime', 50);
            $table->string('extension', 5);
            $table->unsignedBigInteger('size')->comment('Bytes da original, já sem metadado.');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->jsonb('widths')->comment('Larguras das derivadas webp geradas, em px, crescente.');
            $table->char('sha256', 64)->comment('Da original, já sem metadado — base do ETag.');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
