<?php

declare(strict_types=1);

namespace App\Support\Audit;

use App\Enums\MediaAuditEvent;
use Spatie\Activitylog\Models\Activity;

/**
 * Como uma entrada de mídia do `activity_log` aparece na aba "Imagens" da Auditoria: o evento
 * (com a marcação antiga reconhecida) e uma linha de detalhe em português, montada das
 * `properties` que cada Action grava.
 *
 * Nada de dado pessoal: a biblioteca guarda imagem institucional, e o que aparece aqui é o
 * que já está no painel (texto alternativo, página, dimensões). A foto de assistido nem chega
 * a ser cadastrada (StoreMedia).
 */
final class MediaAuditDescription
{
    private const FIELD_LABELS = [
        'alt' => 'texto alternativo',
        'caption' => 'legenda',
        'credit' => 'crédito',
        'depicts_assisted_minor' => 'declaração',
    ];

    public static function event(Activity $entry): ?MediaAuditEvent
    {
        $event = MediaAuditEvent::tryFrom((string) $entry->event);

        if ($event === MediaAuditEvent::Updated && (self::properties($entry)['attributes']['depicts_assisted_minor'] ?? false) === true) {
            return MediaAuditEvent::Marked;
        }

        return $event;
    }

    public static function detail(Activity $entry): ?string
    {
        $p = self::properties($entry);

        return match (self::event($entry)) {
            MediaAuditEvent::Uploaded => self::size($p),
            MediaAuditEvent::Imported => isset($p['origin_key']) ? "Catálogo inicial: {$p['origin_key']}" : null,
            MediaAuditEvent::Replaced => isset($p['attributes']['width'], $p['old']['width'])
                ? "De {$p['old']['width']} × {$p['old']['height']} px para {$p['attributes']['width']} × {$p['attributes']['height']} px"
                : null,
            MediaAuditEvent::Updated, MediaAuditEvent::Marked => self::changedFields($p),
            MediaAuditEvent::Placed, MediaAuditEvent::RemovedFromPage => self::place($p),
            MediaAuditEvent::Moved => isset($p['page'], $p['from'], $p['to'])
                ? "Galeria de /{$p['page']}: da posição ".($p['from'] + 1).' para a '.($p['to'] + 1)
                : null,
            MediaAuditEvent::Deleted, null => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function properties(Activity $entry): array
    {
        return $entry->properties?->toArray() ?? [];
    }

    /**
     * @param  array<string, mixed>  $p
     */
    private static function size(array $p): ?string
    {
        return isset($p['width'], $p['height']) ? "{$p['width']} × {$p['height']} px" : null;
    }

    /**
     * @param  array<string, mixed>  $p
     */
    private static function place(array $p): ?string
    {
        if (! isset($p['page'])) {
            return null;
        }

        return (($p['role'] ?? 'gallery') === 'cover' ? 'Capa' : 'Galeria')." de /{$p['page']}";
    }

    /**
     * Quais campos mudaram, sem os valores: a tela de Auditoria diz QUE houve alteração (o mesmo
     * critério de App\Http\Resources\FormAuditEntryResource).
     *
     * @param  array<string, mixed>  $p
     */
    private static function changedFields(array $p): ?string
    {
        $fields = array_keys(is_array($p['attributes'] ?? null) ? $p['attributes'] : []);
        $labels = array_values(array_filter(array_map(fn (string $field): ?string => self::FIELD_LABELS[$field] ?? null, $fields)));

        return $labels === [] ? null : 'Alterado: '.implode(', ', $labels);
    }
}
