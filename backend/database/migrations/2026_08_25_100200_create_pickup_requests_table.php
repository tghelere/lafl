<?php

declare(strict_types=1);

use App\Support\Migrations\FormSubmissionColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agendamento de coleta do Bazar Beneficente. Titular é o doador (adulto) — dado de
     * regime comum, mas o endereço residencial é o campo mais sensível desta fase inteira
     * (ver docs/dominio.md): purgado assim que a coleta é concluída, não ao fim da retenção
     * geral (ver App\Jobs\PurgeCompletedPickupRequestAddresses).
     */
    public function up(): void
    {
        Schema::create('pickup_requests', function (Blueprint $table): void {
            FormSubmissionColumns::addCommon($table);

            $table->text('donor_name')->comment('Cifrado — nome do doador.');
            $table->text('phone')->comment('Cifrado.');
            // Nullable de propósito: fica null depois que o expurgo de conclusão de coleta
            // roda (ver App\Jobs\PurgeCompletedPickupRequestAddresses), mesmo com o resto do
            // registro ainda vivo até o expurgo geral por expires_at.
            $table->text('address')->nullable()
                ->comment('Cifrado — dado mais sensível desta fase; expurgo próprio na conclusão da coleta.');
            $table->text('items_description');
            $table->string('availability_window');
            // Preenchido pela equipe do bazar ao agendar de fato — nunca vem do formulário
            // público (ver App\Http\Requests\Forms\StorePickupRequestRequest).
            $table->timestamp('scheduled_for')->nullable();
            // Fotos opcionais do item (ver docs/estrutura-site.md §2.2) ficam de fora nesta
            // sessão — dependem da entidade `media`, fora de escopo (ver docs/roadmap.md).
        });

        FormSubmissionColumns::addStatusCheckConstraint('pickup_requests');
    }

    public function down(): void
    {
        Schema::dropIfExists('pickup_requests');
    }
};
