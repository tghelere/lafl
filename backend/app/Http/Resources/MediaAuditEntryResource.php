<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Media;
use App\Models\User;
use App\Support\Audit\MediaAuditDescription;
use App\Support\InstitutionalTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Activitylog\Models\Activity;

/**
 * Uma linha da aba "Imagens" da Auditoria. Como em FormAuditEntryResource, o id sequencial de
 * `activity_log` não sai (CLAUDE.md, regra 4), e os valores alterados também não: a linha diz
 * QUE o texto mudou, e quais campos.
 *
 * A imagem excluída não existe mais: o link some, e o texto alternativo vem do que DeleteMedia
 * gravou antes de apagar.
 *
 * @mixin Activity
 */
final class MediaAuditEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $causer = $this->causer;
        $subject = $this->subject;
        $event = MediaAuditDescription::event($this->resource);
        $properties = $this->properties?->toArray() ?? [];

        return [
            'occurred_at' => $this->created_at?->toIso8601String(),
            'occurred_at_label' => InstitutionalTime::label($this->created_at),
            // `null` quando não houve autor autenticado: a importação das fotos iniciais, por comando.
            'user' => $causer instanceof User ? $causer->name : null,
            'event' => $event !== null ? $event->value : $this->event,
            'action_label' => $event?->label() ?? (string) $this->event,
            'detail' => MediaAuditDescription::detail($this->resource),
            'media_uuid' => $subject instanceof Media ? $subject->uuid : null,
            'media_alt' => $subject instanceof Media
                ? $subject->alt
                : ($properties['alt'] ?? $this->getAttribute('deleted_alt')),
        ];
    }
}
