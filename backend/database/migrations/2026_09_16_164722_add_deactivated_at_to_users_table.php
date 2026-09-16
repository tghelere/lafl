<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // null = ativo. Desativar nunca apaga a conta — só bloqueia login e derruba
            // sessão aberta (ver App\Http\Middleware\EnsureUserIsActive). Sem coluna
            // "is_active" booleana: guardar o instante também serve de auditoria mínima sem
            // depender só do activity log.
            $table->timestamp('deactivated_at')->nullable()->after('remember_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('deactivated_at');
        });
    }
};
