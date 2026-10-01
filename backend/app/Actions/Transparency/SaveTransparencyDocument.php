<?php

declare(strict_types=1);

namespace App\Actions\Transparency;

use App\Actions\Transparency\Data\TransparencyDocumentData;
use App\Models\TransparencyDocument;
use App\Support\Transparency\DocumentSlug;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class SaveTransparencyDocument
{
    /**
     * Cria ou atualiza um documento de transparência. Arquivo fica no disco "local" (fora do
     * webroot, ver docs/protecao-de-dados.md) — trocar o arquivo numa atualização substitui só
     * a referência, o arquivo antigo permanece no disco (nenhuma exclusão automática de
     * arquivo, para não perder evidência de download já contabilizado).
     */
    public function handle(TransparencyDocumentData $data, ?TransparencyDocument $document = null): TransparencyDocument
    {
        $document ??= new TransparencyDocument;

        if (! $document->exists && $data->file === null) {
            throw ValidationException::withMessages([
                'file' => ['Selecione o arquivo do documento.'],
            ]);
        }

        $storedPath = null;

        try {
            return $this->persist($data, $document, $storedPath);
        } catch (Throwable $e) {
            // Falha depois de gravar o arquivo: o rollback desfaz a linha, não o PDF. Apaga só
            // o caminho que ESTA chamada acabou de criar — nunca um arquivo anterior.
            if ($storedPath !== null) {
                Storage::disk('local')->delete($storedPath);
            }

            throw $e;
        }
    }

    private function persist(TransparencyDocumentData $data, TransparencyDocument $document, ?string &$storedPath): TransparencyDocument
    {
        return DB::transaction(function () use ($data, $document, &$storedPath): TransparencyDocument {
            if ($data->file !== null) {
                $path = $data->file->store('transparency-documents', 'local');

                if ($path === false) {
                    throw new RuntimeException('Falha ao armazenar o arquivo do documento.');
                }

                $storedPath = $path;
                $document->file_path = $path;
                $document->file_size = $data->file->getSize();
            }

            // Slug só na criação, nunca no update: a URL pública do PDF é montada a partir
            // dele (ver App\Support\Transparency\DocumentUrl) e pode já estar indexada pelo
            // Google ou citada num ofício. Corrigir o título depois muda o que se lê na
            // página, não o endereço do arquivo.
            if (! $document->exists) {
                $document->slug = DocumentSlug::unique($data->title);
            }

            $document->title = $data->title;
            $document->year = $data->year;
            $document->type = $data->type;

            // null (só possível numa atualização, ver UpdateTransparencyDocumentRequest::toDto)
            // significa "não mexer no estado de publicação" — editar título/ano/tipo não pode
            // despublicar um documento por efeito colateral.
            if ($data->publish === true) {
                $document->published_at ??= now();
            } elseif ($data->publish === false) {
                $document->published_at = null;
            }

            $document->save();

            return $document;
        });
    }
}
