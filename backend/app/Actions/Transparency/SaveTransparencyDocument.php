<?php

declare(strict_types=1);

namespace App\Actions\Transparency;

use App\Actions\Transparency\Data\TransparencyDocumentData;
use App\Models\TransparencyDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

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

        return DB::transaction(function () use ($data, $document): TransparencyDocument {
            if ($data->file !== null) {
                $path = $data->file->store('transparency-documents', 'local');

                if ($path === false) {
                    throw new RuntimeException('Falha ao armazenar o arquivo do documento.');
                }

                $document->file_path = $path;
                $document->file_size = $data->file->getSize();
            }

            $document->title = $data->title;
            $document->year = $data->year;
            $document->type = $data->type;

            if ($data->publish) {
                $document->published_at ??= now();
            } else {
                $document->published_at = null;
            }

            $document->save();

            return $document;
        });
    }
}
