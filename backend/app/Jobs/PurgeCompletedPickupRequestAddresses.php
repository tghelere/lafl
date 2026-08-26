<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\PickupRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Endereço residencial é o dado mais sensível de `pickup_requests` (ver docs/dominio.md) —
 * purgado assim que a coleta é concluída, não ao fim da retenção geral de 6 meses que
 * App\Jobs\PurgeExpiredFormSubmissions já cobre para o registro inteiro. Agendado com mais
 * frequência que o expurgo geral (ver routes/console.php) porque "assim que" pede menos
 * atraso do que uma rotina diária.
 *
 * Escrita via instância de model (nunca query builder em massa) — App\Casts\FieldEncrypted é
 * um cast comum, não teria o problema do blind index, mas a convenção do projeto (ver
 * docs/protecao-de-dados.md) é a mesma para todo campo cifrado.
 */
final class PurgeCompletedPickupRequestAddresses implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        PickupRequest::query()->completedWithAddress()->each(function (PickupRequest $request): void {
            $request->address = null;
            $request->save();
        });
    }
}
