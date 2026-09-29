<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Os acontecimentos da biblioteca de mídia que a aba "Imagens" da Auditoria mostra — todo
 * `event` gravado com `log_name = media`:
 *
 * | Caso | Quem grava |
 * |---|---|
 * | `uploaded` | App\Actions\Media\StoreMedia |
 * | `imported` | App\Actions\Media\ImportInitialPhotos |
 * | `replaced` | App\Actions\Media\ReplaceMediaFile |
 * | `updated`, `marked` | App\Actions\Media\UpdateMediaDetails |
 * | `deleted` | App\Actions\Media\DeleteMedia |
 * | `placed` | UploadImageToPage e SetPageCover (App\Actions\Content\PageImages) |
 * | `removed_from_page` | App\Actions\Content\PageImages\RemoveImageFromPage |
 * | `moved` | App\Actions\Content\PageImages\MoveGalleryImage |
 *
 * `marked` existe desde a sessão 29. Antes, a marcação era um `updated` com
 * `depicts_assisted_minor` indo para true, e a leitura (App\Support\Audit\MediaAuditDescription)
 * reconhece esse caso também. `event` é texto livre no banco, então a leitura é por `tryFrom`.
 */
enum MediaAuditEvent: string
{
    case Uploaded = 'uploaded';
    case Imported = 'imported';
    case Replaced = 'replaced';
    case Updated = 'updated';
    case Marked = 'marked';
    case Deleted = 'deleted';
    case Placed = 'placed';
    case RemovedFromPage = 'removed_from_page';
    case Moved = 'moved';

    public function label(): string
    {
        return match ($this) {
            self::Uploaded => 'Imagem enviada',
            self::Imported => 'Foto inicial importada',
            self::Replaced => 'Arquivo substituído',
            self::Updated => 'Texto alterado',
            self::Marked => 'Marcada como foto de criança ou adolescente atendido',
            self::Deleted => 'Imagem excluída',
            self::Placed => 'Posta na página',
            self::RemovedFromPage => 'Tirada da página',
            self::Moved => 'Movida na galeria',
        };
    }
}
