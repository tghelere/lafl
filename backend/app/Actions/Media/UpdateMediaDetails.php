<?php

declare(strict_types=1);

namespace App\Actions\Media;

use App\Actions\Media\Data\MediaDetailsData;
use App\Models\Media;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Texto alternativo, legenda e a declaração de assistido.
 *
 * O texto alternativo e a legenda daqui são o PADRÃO oferecido ao inserir a imagem numa
 * página; o que foi inserido fica gravado no conteúdo da página e não muda sozinho quando o
 * padrão muda — o mesmo retrato pode pedir descrições diferentes em textos diferentes.
 *
 * A declaração de assistido só vai num sentido. Marcar é a correção prevista (alguém percebeu
 * que a foto mostra uma criança atendida): a imagem deixa de ser publicável na hora e sai do
 * site. Desmarcar recolocaria no ar uma foto que alguém da equipe identificou como de
 * assistido — isso só um consentimento registrado poderia autorizar, e ele ainda não existe
 * no sistema (ver docs/decisoes/0024-biblioteca-de-midia.md).
 */
final class UpdateMediaDetails
{
    public function __construct(private readonly ForgetPagesUsingMedia $forgetPages) {}

    public function handle(Media $media, MediaDetailsData $details, ?User $actor): Media
    {
        if ($media->depicts_assisted_minor && ! $details->depictsAssistedMinor) {
            throw ValidationException::withMessages([
                'depicts_assisted_minor' => [
                    'Esta imagem foi marcada como foto de criança ou adolescente atendido e não pode ser '.
                    'desmarcada: só um consentimento de imagem registrado poderia liberá-la.',
                ],
            ]);
        }

        $changed = false;

        DB::transaction(function () use ($media, $details, $actor, &$changed): void {
            $media->alt = $details->alt;
            $media->caption = $details->caption;
            $media->depicts_assisted_minor = $details->depictsAssistedMinor;

            $dirty = $media->getDirty();

            if ($dirty === []) {
                return;
            }

            $old = array_intersect_key($media->getOriginal(), $dirty);
            $media->save();
            $changed = true;

            activity('media')
                ->causedBy($actor)
                ->performedOn($media)
                // A marcação é um ato próprio na Auditoria, não mais uma "alteração de dados":
                // tira a imagem do site e não se desfaz.
                ->event(($dirty['depicts_assisted_minor'] ?? false) === true ? 'marked' : 'updated')
                ->withProperties(['old' => $old, 'attributes' => $dirty])
                ->log(($dirty['depicts_assisted_minor'] ?? false) === true
                    ? 'Imagem marcada como foto de criança ou adolescente atendido'
                    : 'Dados da imagem alterados');
        });

        // Qualquer mudança, não só a marcação: a capa e a galeria mostram o texto alternativo e a
        // legenda DA BIBLIOTECA (ADR 0025), e o site ficaria até dez minutos com o texto velho.
        // A marcação continua sendo o caso que não pode esperar: tira a imagem do ar na hora.
        if ($changed) {
            $this->forgetPages->handle($media);
        }

        return $media;
    }
}
