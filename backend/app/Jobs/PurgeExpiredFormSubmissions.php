<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Descarte automatizado por `expires_at` (ver docs/protecao-de-dados.md, "Retenção e
 * eliminação": job de descarte, nunca exclusão manual). Hard delete real — nenhuma das seis
 * entidades usa SoftDeletes, então isso já é eliminação de verdade, não um soft delete.
 *
 * Agendado em routes/console.php. A lista de models vem de `config('forms.submission_models')`
 * para esta classe não precisar mudar conforme cada entidade nasce (ver docs/roadmap.md).
 */
final class PurgeExpiredFormSubmissions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        foreach (config('forms.submission_models', []) as $modelClass) {
            /** @var class-string<Model> $modelClass */
            $modelClass::query()->where('expires_at', '<=', now())->delete();
        }
    }
}
