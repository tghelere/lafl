<?php

declare(strict_types=1);
use App\Enums\Role;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;

/**
 * Bootstrap compartilhado pelos scripts desta pasta — ver README.md antes de rodar qualquer
 * coisa aqui. Sobe a aplicação real (não um mock), a partir do backend/.env de quem está
 * rodando, e recusa continuar fora do ambiente local.
 */
$base = dirname(__DIR__, 2);

require $base.'/vendor/autoload.php';

/** @var Application $app */
$app = require $base.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! $app->environment('local')) {
    fwrite(STDERR, 'Recusado: este script só roda com APP_ENV=local (ambiente atual: '.app()->environment().").\n");
    fwrite(STDERR, "Ele cria e desativa usuários reais via as Actions de produção — nunca rodar contra staging ou produção.\n");
    exit(1);
}

const SIGNAL_DIR = __DIR__.'/.signals';
const CORRIDA_EMAIL_DOMAIN = '@corrida.local';

function signal(string $name): void
{
    @mkdir(SIGNAL_DIR, 0777, true);
    file_put_contents(SIGNAL_DIR.'/'.$name, (string) microtime(true));
}

function clearSignals(): void
{
    foreach (glob(SIGNAL_DIR.'/*') ?: [] as $file) {
        @unlink($file);
    }
}

function waitForSignal(string $name, float $timeoutSeconds = 30.0): bool
{
    $deadline = microtime(true) + $timeoutSeconds;

    while (microtime(true) < $deadline) {
        if (file_exists(SIGNAL_DIR.'/'.$name)) {
            return true;
        }
        usleep(50_000);
    }

    return false;
}

function say(string $who, string $message): void
{
    printf("[%s %s] %s\n", date('H:i:s'), $who, $message);
}

/**
 * Conta quantos backends do Postgres estão bloqueados esperando trava de linha — é assim que
 * um processo sabe que o outro já chegou no SELECT ... FOR UPDATE e travou, em vez de chutar
 * um sleep.
 */
function backendsWaitingOnLock(): int
{
    return (int) DB::selectOne(
        "SELECT count(*) AS total FROM pg_stat_activity
         WHERE wait_event_type = 'Lock'
           AND datname = current_database()
           AND pid <> pg_backend_pid()"
    )->total;
}

function activeSuperAdminCount(): int
{
    return User::query()
        ->role(Role::SuperAdmin->value)
        ->active()
        ->count();
}
