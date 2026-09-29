<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Imagem da biblioteca de conteúdo. Sem `LogsActivity`: upload, substituição, edição e
 * remoção são registrados com evento próprio pelas Actions de App\Actions\Media — "o arquivo
 * foi trocado" e "o texto alternativo foi corrigido" são atos diferentes, e o `updated`
 * genérico do pacote não os distinguiria.
 *
 * Sem `SoftDeletes`, de propósito: excluir uma imagem apaga os arquivos. Uma foto que não
 * devia estar no sistema (a correção prevista pela declaração de assistido) tem de poder sair
 * de verdade, e não ficar numa lixeira.
 *
 * Nada é atribuível em massa: toda escrita passa pelas Actions, campo a campo.
 *
 * @property list<int> $widths
 */
class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory;

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'depicts_assisted_minor' => 'boolean',
            'version' => 'integer',
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'widths' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $media): void {
            $media->uuid ??= (string) Str::uuid();
        });
    }

    /**
     * Pode aparecer no site público. Hoje a única coisa que impede é a declaração de que a
     * imagem mostra criança ou adolescente atendido: sem registro de consentimento de imagem no
     * sistema, não há consentimento vigente que libere (CLAUDE.md, regra 6). Quando o registro
     * existir, a condição passa a consultá-lo — e só este método muda.
     */
    public function isPublishable(): bool
    {
        return ! $this->depicts_assisted_minor;
    }
}
