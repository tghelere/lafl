<?php

declare(strict_types=1);

namespace App\Actions\Partners;

use App\Actions\Media\Data\MediaDetailsData;
use App\Actions\Media\ReplaceMediaFile;
use App\Actions\Media\StoreMedia;
use App\Actions\Partners\Data\PartnerData;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cria ou atualiza um parceiro.
 *
 * A logo entra pela biblioteca de imagens (StoreMedia), com o nome do parceiro como texto
 * alternativo e sem a marca de assistido — é logotipo de empresa, não foto de pessoa. Trocar a
 * logo de um parceiro existente substitui o arquivo da MESMA imagem (ReplaceMediaFile), então
 * a biblioteca não acumula logos órfãs. O texto alternativo acompanha o nome.
 */
final class SavePartner
{
    public function __construct(
        private readonly StoreMedia $storeMedia,
        private readonly ReplaceMediaFile $replaceMedia,
    ) {}

    public function handle(PartnerData $data, ?Partner $partner, ?User $actor): Partner
    {
        if ($partner === null && $data->logoPath === null) {
            throw ValidationException::withMessages(['logo' => ['Envie a logo do parceiro.']]);
        }

        // Fora da transação: processar a imagem é a parte lenta e não toca o banco da linha.
        $media = $partner?->media;

        if ($partner === null) {
            $media = $this->storeMedia->handle(
                $data->logoPath ?? '',
                new MediaDetailsData(alt: $data->name, caption: null, depictsAssistedMinor: false),
                $actor,
            );
        } elseif ($data->logoPath !== null) {
            $media = $this->replaceMedia->handle($partner->media, $data->logoPath, $actor);
        }

        return DB::transaction(function () use ($data, $partner, $media): Partner {
            $partner ??= new Partner;

            $partner->name = $data->name;
            $partner->url = $data->url;
            $partner->is_active = $data->isActive;
            $partner->media()->associate($media);

            if ($data->position !== null) {
                $partner->position = $data->position;
            } elseif (! $partner->exists) {
                $partner->position = ((int) Partner::query()->max('position')) + 1;
            }

            $partner->save();

            // Texto alternativo = nome atual do parceiro.
            if ($media !== null && $media->alt !== $data->name) {
                $media->alt = $data->name;
                $media->save();
            }

            return $partner->load('media');
        });
    }
}
