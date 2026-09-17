<?php

declare(strict_types=1);
use App\Enums\Role;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;

/**
 * Bootstrap compartilhado pelos scripts desta pasta — ver README.md antes de rodar qualquer
 * coisa aqui. Sobe a aplicação real (não um mock) sempre contra o banco de teste dedicado
 * (lar_analia_franco_test, ver backend/.env.testing) — nunca o banco de desenvolvimento.
 *
 * APP_ENV=testing é forçado aqui, antes de qualquer bootstrap do Laravel, para o script
 * carregar backend/.env.testing mesmo que quem chamar não tenha exportado nada — não dá para
 * confiar em quem roda lembrar de "export APP_ENV=testing" toda vez; o script decide isso
 * sozinho. Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables lê APP_ENV do ambiente do
 * processo ANTES de carregar qualquer arquivo .env, então isto precisa rodar antes de
 * `bootstrap/app.php`.
 */
putenv('APP_ENV=testing');
$_ENV['APP_ENV'] = 'testing';
$_SERVER['APP_ENV'] = 'testing';

$base = dirname(__DIR__, 2);

require $base.'/vendor/autoload.php';

/** @var Application $app */
$app = require $base.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Duas checagens, não uma: a de ambiente cobre o caso comum (script rodado do jeito errado);
// a do nome do banco é quem realmente impede o desastre, porque é ela que barraria mesmo se
// backend/.env.testing um dia for editado para apontar para outro lugar, ou se o `putenv`
// acima deixar de fazer efeito por algum motivo.
const TEST_DATABASE_NAME = 'lar_analia_franco_test';

$resolvedDatabase = DB::connection()->getDatabaseName();

if (! $app->environment('testing') || $resolvedDatabase !== TEST_DATABASE_NAME) {
    fwrite(STDERR, "Recusado: este script só roda contra o banco de teste dedicado.\n");
    fwrite(STDERR, '  ambiente resolvido: '.app()->environment()."\n");
    fwrite(STDERR, "  banco resolvido: {$resolvedDatabase} (esperado: ".TEST_DATABASE_NAME.")\n");
    fwrite(STDERR, "Ele apaga e recria todas as tabelas (migrate:fresh) antes de cada execução — nunca rodar\n");
    fwrite(STDERR, "isto fora do banco de teste, e nunca enquanto a suíte Pest estiver rodando (ver README.md).\n");
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
