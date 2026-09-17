<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Users\CreateUser;
use App\Actions\Users\GeneratePasswordLink;
use App\Enums\Role as RoleEnum;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

/**
 * Cria a PRIMEIRA conta capaz de entrar no painel de um ambiente novo (ver docs/deploy.md).
 * O `DevSuperAdminSeeder` só roda em local/testing e usa uma senha conhecida — nada disso
 * serve para staging/production, onde ninguém teria como entrar sem este comando.
 *
 * Senha nunca entra aqui: não há argumento, opção nem pergunta de senha. A conta nasce com
 * uma senha aleatória inutilizável (ver App\Actions\Users\CreateUser) e o que o comando
 * imprime é o link de definição de senha — o mesmo mecanismo que o painel já usa
 * (App\Actions\Users\GeneratePasswordLink). Assim nada sensível passa por `ps`, pelo
 * histórico do shell, por log de deploy ou por variável de ambiente.
 *
 * A recusa quando já existe super_admin ativo é a proteção contra o uso errado do comando:
 * depois do primeiro deploy, conta nova se cria pelo painel, com quem criou registrado na
 * auditoria. `--forcar` existe para o caso de perda de acesso — e aparece na auditoria como
 * criação pelo console, sem causador, que é exatamente o que ela é.
 */
final class CreateSuperAdmin extends Command
{
    protected $signature = 'usuarios:criar-super-admin {--forcar : Cria mesmo já existindo super administrador ativo}';

    protected $description = 'Cria a primeira conta de super administrador e imprime o link de definição de senha.';

    public function handle(CreateUser $createUser, GeneratePasswordLink $generateLink): int
    {
        if (! Role::query()->where('name', RoleEnum::SuperAdmin->value)->exists()) {
            $this->components->error('O papel super_admin não existe neste banco.');
            $this->line('  Rode antes: php artisan db:seed --class=Database\\\\Seeders\\\\RoleSeeder --force');

            return self::FAILURE;
        }

        if (! $this->option('forcar') && $this->activeSuperAdminExists()) {
            $this->components->error('Já existe super administrador ativo neste ambiente.');
            $this->line('  Conta nova se cria pelo painel, com quem criou registrado na auditoria.');
            $this->line('  Perdeu o acesso? Repita com --forcar.');

            return self::FAILURE;
        }

        $name = $this->askName();

        if ($name === null) {
            return self::FAILURE;
        }

        $email = $this->askEmail();

        if ($email === null) {
            return self::FAILURE;
        }

        // Criação e link na mesma transação: um link gerado para uma conta que não chegou a
        // existir seria pior que nenhum link.
        $url = DB::transaction(function () use ($createUser, $generateLink, $name, $email): string {
            $user = $createUser->handle($name, $email, [RoleEnum::SuperAdmin->value]);

            // Criação e papel já entram na auditoria pelo próprio CreateUser. Este evento
            // extra registra o que aquele não tem como registrar: a conta nasceu pelo
            // console, sem causador autenticado (ver CLAUDE.md, regra 8 — nenhum valor
            // sensível aqui, só o fato).
            activity('users')
                ->performedOn($user)
                ->event('super_admin_bootstrapped')
                ->log('Super administrador criado pelo console');

            return $generateLink->handle($user);
        });

        $this->newLine();
        $this->components->info("Conta criada: {$name} <{$email}>");
        $this->components->twoColumnDetail('Papel', RoleEnum::SuperAdmin->value);
        $this->newLine();
        $this->line('  Link de definição de senha (válido por 24 horas, de uso único):');
        $this->newLine();
        $this->line('  '.$url);
        $this->newLine();
        $this->components->warn(
            'Entregue o link por canal privado e não o deixe em log, chat de equipe nem arquivo do repositório. '.
            'Perdido ou expirado, gere outro pelo painel (a geração invalida o anterior).',
        );

        return self::SUCCESS;
    }

    private function activeSuperAdminExists(): bool
    {
        return User::query()->role(RoleEnum::SuperAdmin->value)->active()->exists();
    }

    private function askName(): ?string
    {
        $name = trim((string) $this->ask('Nome completo'));

        if ($name === '' || mb_strlen($name) > 255) {
            $this->components->error('Informe um nome com até 255 caracteres.');

            return null;
        }

        return $name;
    }

    private function askEmail(): ?string
    {
        $email = trim((string) $this->ask('E-mail'));

        $validator = Validator::make(
            ['email' => $email],
            ['email' => ['required', 'email', 'max:255', 'unique:users,email']],
            [
                'email.required' => 'Informe o e-mail.',
                'email.email' => 'Informe um e-mail válido.',
                'email.max' => 'E-mail longo demais.',
                'email.unique' => 'Já existe um usuário com este e-mail.',
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return null;
        }

        return $email;
    }
}
