<?php

declare(strict_types=1);

use App\Actions\Media\ImportInitialPhotos;
use App\Models\Media;
use App\Models\PageImage;
use App\Support\Cache\PublicPageCache;
use App\Support\Media\InitialPhotos;
use App\Support\Media\MediaPaths;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Leva para produção as 7 fotos novas do bazar que o Lar enviou em 9 de outubro de 2026
 * (App\Support\Media\InitialPhotos::additions()), pelo mesmo caminho das fotos atuais: a Action
 * de importação do catálogo, com `origin_key`.
 *
 * Só acrescenta, e só estas 7 chaves — nunca o catálogo inteiro, que traria de volta uma foto
 * original que a equipe tivesse excluído pelo painel:
 * - foto cuja chave já está em `media.origin_key` é pulada; rodar de novo não duplica;
 * - entra no FIM da galeria do bazar; nada que já está lá muda de lugar, de texto ou de arquivo;
 * - sem a página `bazar` (banco novo, antes do conteúdo), nada é gravado e o fato vai para o
 *   log. Nesse caso `midia:importar-fotos-iniciais` (desenvolvimento) ou o pacote de conteúdo
 *   (produção, docs/deploy.md §10.1) trazem as fotos.
 *
 * A regra de importação mora na Action; aqui só se escolhe o lote. A migração roda uma vez: se a
 * equipe excluir uma destas fotos depois, ela não volta.
 */
return new class extends Migration
{
    public function up(): void
    {
        $result = app(ImportInitialPhotos::class)->handle(self::keys());

        foreach ($result['missing_page'] as $key => $slug) {
            Log::info('Foto do lote não importada: a página não existe.', ['photo' => $key, 'slug' => $slug]);
        }

        Log::info('Lote de fotos do bazar.', ['imported' => $result['created'], 'skipped' => $result['skipped']]);
    }

    /**
     * Desfaz só o lote: tira as 7 fotos da galeria e da biblioteca, com os arquivos. Foto do
     * lote que já não existe (excluída pelo painel) é ignorada.
     */
    public function down(): void
    {
        $slugs = [];

        foreach (Media::query()->whereIn('origin_key', self::keys())->get() as $media) {
            $pages = PageImage::query()->where('media_id', $media->id)->with('page:id,slug')->get();

            foreach ($pages as $image) {
                $slugs[] = $image->page->slug;
            }

            DB::transaction(function () use ($media): void {
                PageImage::query()->where('media_id', $media->id)->delete();
                $media->delete();
            });

            Storage::disk('local')->deleteDirectory(MediaPaths::root($media));
        }

        foreach (array_unique($slugs) as $slug) {
            Cache::forget(PublicPageCache::key($slug));
        }
    }

    /**
     * @return list<string>
     */
    private static function keys(): array
    {
        return array_keys(InitialPhotos::additions());
    }
};
