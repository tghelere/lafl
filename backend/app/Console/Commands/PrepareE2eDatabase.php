<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Seeders\E2eSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use PDOException;

/**
 * Prepara o banco da bateria de ponta a ponta do zero: cria o banco se ainda não existir,
 * roda `migrate:fresh`, o RoleSeeder e o E2eSeeder. Chamado pelo globalSetup do Playwright
 * (ver e2e/global-setup.ts) — nunca à mão no dia a dia.
 *
 * Este comando APAGA TODAS AS TABELAS do banco em que roda. Por isso a guarda dupla abaixo,
 * no mesmo espírito de backend/scripts/concorrencia/bootstrap.php: a checagem de ambiente
 * cobre o engano comum (rodar sem APP_ENV=e2e, caindo no banco de desenvolvimento), e a
 * checagem do nome do banco é quem realmente impede o desastre — ela barraria mesmo se
 * backend/.env.e2e um dia for editado para apontar para outro lugar.
 */
final class PrepareE2eDatabase extends Command
{
    protected $signature = 'e2e:prepare';

    protected $description = 'Recria do zero o banco da bateria de ponta a ponta (lar_analia_franco_e2e).';

    public const DATABASE_NAME = 'lar_analia_franco_e2e';

    public function handle(): int
    {
        $resolvedDatabase = (string) config('database.connections.'.config('database.default').'.database');

        if (! app()->environment('e2e') || $resolvedDatabase !== self::DATABASE_NAME) {
            $this->components->error('Recusado: este comando só roda contra o banco dedicado de e2e.');
            $this->line('  ambiente resolvido: '.app()->environment());
            $this->line('  banco resolvido: '.($resolvedDatabase !== '' ? $resolvedDatabase : '(vazio)').' (esperado: '.self::DATABASE_NAME.')');
            $this->line('  Ele apaga todas as tabelas (migrate:fresh) antes de cada execução — nunca rodar fora do banco de e2e.');
            $this->line('  Use: APP_ENV=e2e php artisan e2e:prepare (ver e2e/README.md).');

            return self::FAILURE;
        }

        $this->ensureDatabaseExists();

        $this->call('migrate:fresh', ['--force' => true]);
        $this->call('db:seed', ['--class' => RoleSeeder::class, '--force' => true]);
        $this->call('db:seed', ['--class' => E2eSeeder::class, '--force' => true]);

        // O limitador de taxa de login e o cache de página pública vivem no Redis (índice
        // próprio, ver backend/.env.e2e). Sem limpar, duas execuções seguidas dentro do mesmo
        // minuto somariam tentativas de login no mesmo balde e a segunda bateria começaria
        // esbarrando no throttle — falha intermitente que não tem nada a ver com o que se
        // está testando.
        $this->call('cache:clear');

        $this->components->info('Banco de e2e pronto: '.self::DATABASE_NAME);

        return self::SUCCESS;
    }

    /**
     * Conecta-se ao banco administrativo `postgres` do mesmo servidor só para criar o banco
     * de e2e quando ele ainda não existe — é o caso de qualquer volume de dados criado antes
     * de docker/postgres/init-e2e-db.sql existir, e do primeiro `npm run test:e2e` de quem
     * clona o repositório.
     */
    private function ensureDatabaseExists(): void
    {
        $connection = (string) config('database.default');
        $config = config('database.connections.'.$connection);

        Config::set('database.connections.e2e_bootstrap', array_merge($config, ['database' => 'postgres']));

        try {
            $exists = DB::connection('e2e_bootstrap')
                ->table('pg_database')
                ->where('datname', self::DATABASE_NAME)
                ->exists();

            if (! $exists) {
                DB::connection('e2e_bootstrap')->statement('CREATE DATABASE "'.self::DATABASE_NAME.'"');
                $this->components->info('Banco '.self::DATABASE_NAME.' criado.');
            }
        } catch (PDOException $exception) {
            // Sem permissão para criar banco (ou sem acesso ao `postgres`), o caminho é criar
            // à mão — ver e2e/README.md. Não é motivo para abortar aqui: se o banco já existe,
            // o migrate:fresh a seguir funciona do mesmo jeito.
            $this->components->warn('Não foi possível verificar/criar o banco automaticamente: '.$exception->getMessage());
        } finally {
            DB::purge('e2e_bootstrap');
        }
    }
}
