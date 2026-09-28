<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\FormAuditEvent;
use App\Enums\FormSubmissionType;
use App\Models\Contracts\FormSubmission;
use App\Models\User;
use App\Support\InstitutionalTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Activitylog\Models\Activity;

/**
 * Uma linha da tela de Auditoria (ver docs/estrutura-site.md §4.2, Configurações).
 *
 * Duas coisas que NÃO saem daqui, de propósito:
 *
 * - **o id da linha de `activity_log`**, que é sequencial. A tela é só leitura e não tem rota por
 *   entrada, então o id não serve para nada e expô-lo quebraria a regra 4 do CLAUDE.md sem
 *   ganho nenhum;
 * - **qualquer valor de campo pessoal**. `attribute_changes` do spatie fica fora: o log registra o
 *   acesso, nunca o valor descriptografado (ver docs/protecao-de-dados.md, "Auditoria"), e uma
 *   tela que mostrasse o diff viraria a cópia em texto puro que a criptografia existe para
 *   evitar. O que a tela mostra é QUE houve alteração, não o quê.
 *
 * @mixin Activity
 */
final class FormAuditEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // `causer` e `subject` são relações polimórficas: chegam como Model genérico, e é o
        // `instanceof` que diz o que dá para ler de cada um.
        $causer = $this->causer;
        $subject = $this->subject;
        $type = FormSubmissionType::forModel($subject);

        return [
            'occurred_at' => $this->created_at?->toIso8601String(),
            'occurred_at_label' => InstitutionalTime::label($this->created_at),

            // `null` quando o acontecimento não teve autor autenticado — é o caso do `created`,
            // que vem do formulário público do site.
            'user' => $causer instanceof User ? $causer->name : null,

            'event' => $this->event,
            'action_label' => FormAuditEvent::labelFor($this->event),

            'form_type' => $type?->value,
            'form_type_label' => $type?->label(),

            // O par que o painel usa para montar o link para o registro. `null` quando o
            // formulário já foi expurgado por retenção — a entrada do log continua, o registro
            // não.
            'record_uuid' => $subject instanceof FormSubmission ? $subject->publicId() : null,
            'record_resource' => $type?->adminResourceSlug(),

            'ip' => $this->properties['ip'] ?? null,
        ];
    }
}
