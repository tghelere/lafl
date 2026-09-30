<?php

declare(strict_types=1);

namespace App\Actions\Media;

use App\Actions\Content\ImportContentPackage;
use App\Enums\PageImageRole;
use App\Models\Media;
use App\Models\Page;
use App\Models\PageImage;
use App\Support\Cache\PublicPageCache;
use App\Support\Content\ContentPackage;
use App\Support\Media\MediaUrl;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

/**
 * Leva de um pacote de `conteudo:exportar` **só as imagens** e a capa e a galeria das páginas,
 * para um ambiente cujas páginas já existem e cujo texto não pode ser tocado (ver
 * docs/deploy.md §10.2). O pacote é o mesmo de `conteudo:importar`; o que muda é o que se lê
 * dele: `pages.json` serve só para saber, pelo slug, onde vai cada imagem.
 *
 * - Página casa pelo **slug**, nunca pelo id nem pelo uuid (o uuid da página difere entre
 *   ambientes criados separadamente).
 * - A imagem mantém o **uuid** da origem. É por ele que o texto cita a foto (`/midia/{uuid}`) e
 *   que o arquivo mora no disco, então nenhuma referência de texto precisa ser remapeada: o
 *   texto das páginas não é lido para escrita nem gravado.
 * - Nunca sobrescreve: imagem que já existe (mesmo uuid) é mantida; página que já tem capa ou
 *   galeria é mantida inteira. Página do pacote sem par aqui é listada, não criada.
 * - Imagem marcada como foto de assistido não está no pacote (o export não a leva) e, se já
 *   existir aqui marcada, não é tocada.
 *
 * Tudo é conferido antes da primeira escrita; o banco é gravado numa transação e os arquivos
 * gravados por esta execução são removidos se ela falhar.
 */
final class ImportPageMediaPackage
{
    public function __construct(
        private readonly ImportContentPackage $package,
    ) {}

    /**
     * @return array{
     *     media: array{created: list<string>, skipped: list<string>},
     *     pages: array{linked: array<string, int>, kept: list<string>, missing: list<string>},
     *     broken_text_refs: array<string, list<string>>,
     *     dry_run: bool
     * }
     *
     * @throws InvalidArgumentException pacote inválido ou em conflito — nada foi escrito
     */
    public function handle(string $packageDir, bool $dryRun = false): array
    {
        $packageDir = rtrim($packageDir, '/');

        [$pages, , $media] = $this->package->readAndVerify($packageDir);

        $result = [
            'media' => ['created' => [], 'skipped' => []],
            'pages' => ['linked' => [], 'kept' => [], 'missing' => []],
            'broken_text_refs' => [],
            'dry_run' => $dryRun,
        ];

        $mediaPlan = [];
        foreach ($media as $data) {
            $label = "{$data['uuid']} ({$data['alt']})";

            if (Media::query()->where('uuid', $data['uuid'])->exists()) {
                $result['media']['skipped'][] = $label;

                continue;
            }

            $originKey = $data['origin_key'] ?? null;
            if (is_string($originKey) && Media::query()->where('origin_key', $originKey)->exists()) {
                throw new InvalidArgumentException("A foto inicial \"{$originKey}\" já existe neste ambiente com outro uuid (midia:importar-fotos-iniciais rodou aqui?). Nada foi escrito.");
            }

            $mediaPlan[] = $data;
            $result['media']['created'][] = $label;
        }

        $arriving = array_column($mediaPlan, 'uuid');

        $linkPlan = [];
        foreach ($pages as $data) {
            if ($data['images'] === []) {
                continue;
            }

            $target = Page::query()->where('slug', $data['slug'])->first();

            if ($target === null) {
                $result['pages']['missing'][] = $data['slug'];

                continue;
            }

            if (PageImage::query()->where('page_id', $target->id)->exists()) {
                $result['pages']['kept'][] = $data['slug'];

                continue;
            }

            foreach ($data['images'] as $entry) {
                if (! in_array($entry['media_uuid'], $arriving, true)
                    && Media::query()->where('uuid', $entry['media_uuid'])->where('depicts_assisted_minor', false)->doesntExist()) {
                    throw new InvalidArgumentException("A página {$data['slug']} usaria a imagem {$entry['media_uuid']}, que não está no pacote nem pode ser usada neste ambiente. Nada foi escrito.");
                }
            }

            $linkPlan[] = [$target, $data['images']];
            $result['pages']['linked'][$data['slug']] = count($data['images']);
        }

        // O texto daqui não muda; só se aponta a foto citada nele que continuaria sem arquivo.
        foreach (Page::query()->get(['id', 'slug', 'content']) as $page) {
            preg_match_all(MediaUrl::CANONICAL_IN_HTML_PATTERN, (string) $page->content, $matches);

            $broken = array_values(array_filter(
                array_unique($matches[1]),
                fn (string $uuid): bool => ! in_array($uuid, $arriving, true) && Media::query()->where('uuid', $uuid)->doesntExist(),
            ));

            if ($broken !== []) {
                $result['broken_text_refs'][$page->slug] = $broken;
            }
        }

        if ($dryRun) {
            return $result;
        }

        $disk = Storage::disk('local');
        $written = [];

        try {
            foreach ($mediaPlan as $data) {
                foreach ($data['files'] as $file) {
                    if ($disk->exists($file['path']) && hash('sha256', (string) $disk->get($file['path'])) === $file['sha256']) {
                        continue;
                    }

                    $disk->put($file['path'], (string) file_get_contents($packageDir.'/'.ContentPackage::FILES_DIR.'/'.$file['path']));
                    $written[] = $file['path'];
                }
            }

            DB::transaction(function () use ($mediaPlan, $linkPlan): void {
                foreach ($mediaPlan as $data) {
                    $this->package->writeMedia($data, null);
                }

                foreach ($linkPlan as [$page, $images]) {
                    foreach ($images as $entry) {
                        $image = new PageImage;
                        $image->page_id = $page->id;
                        $image->media_id = Media::query()->where('uuid', $entry['media_uuid'])->valueOrFail('id');
                        $image->role = PageImageRole::from($entry['role']);
                        $image->position = $entry['position'];
                        $image->save();
                    }
                }
            });
        } catch (Throwable $e) {
            foreach ($written as $path) {
                $disk->delete($path);
            }

            throw $e;
        }

        foreach ($linkPlan as [$page]) {
            Cache::forget(PublicPageCache::key($page->slug));
        }

        return $result;
    }

    /**
     * Confere, depois de importar, que o ambiente tem o que o pacote traz: cada imagem no banco
     * com todos os arquivos no disco (SHA-256 igual ao do pacote), e cada página do pacote com
     * foto existindo aqui com exatamente a mesma capa e galeria. Só lê.
     *
     * @return list<string> problemas encontrados; vazio quando está tudo lá
     */
    public function verify(string $packageDir): array
    {
        [$pages, , $media] = $this->package->readAndVerify(rtrim($packageDir, '/'));

        $disk = Storage::disk('local');
        $problems = [];

        foreach ($media as $data) {
            if (Media::query()->where('uuid', $data['uuid'])->where('depicts_assisted_minor', false)->doesntExist()) {
                $problems[] = "Imagem {$data['uuid']} ausente do banco (ou marcada como de assistido).";

                continue;
            }

            foreach ($data['files'] as $file) {
                if (! $disk->exists($file['path']) || hash('sha256', (string) $disk->get($file['path'])) !== $file['sha256']) {
                    $problems[] = "Arquivo {$file['path']} ausente do disco ou diferente do pacote.";
                }
            }
        }

        foreach ($pages as $data) {
            if ($data['images'] === []) {
                continue;
            }

            $page = Page::query()->where('slug', $data['slug'])->first();

            if ($page === null) {
                $problems[] = "Página {$data['slug']} não existe aqui.";

                continue;
            }

            $expected = array_map(fn (array $e): string => "{$e['media_uuid']}:{$e['role']}:{$e['position']}", $data['images']);
            $actual = $page->images()->with('media')->get()
                ->map(fn (PageImage $i): string => "{$i->media->uuid}:{$i->role->value}:{$i->position}")->all();
            sort($expected);
            sort($actual);

            if ($expected !== $actual) {
                $problems[] = sprintf('Página %s com capa/galeria diferente do pacote (%d esperados, %d aqui).', $data['slug'], count($expected), count($actual));
            }
        }

        return $problems;
    }
}
