<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Aposenta o status `new` e renomeia `discarded` para `archived` nas cinco tabelas de
 * formulário recebido.
 *
 * `new` significava "ninguém mexeu nisto ainda" — informação de leitura, não de atendimento —,
 * e leitura passou a ter colunas próprias na migration anterior. Ver
 * docs/decisoes/0021-leitura-separada-do-status-de-atendimento.md e App\Enums\FormSubmissionStatus.
 *
 * As listas de valores estão escritas à mão aqui, e não lidas do enum PHP: uma migration
 * descreve o banco no momento em que foi escrita. Ler o enum faria esta migration mudar de
 * comportamento na próxima vez que alguém acrescentasse um status, e um `migrate:fresh` de hoje
 * deixaria de reproduzir o banco de amanhã.
 *
 * Reversível, mas não sem perda: `new` e `in_progress` viram ambos `in_progress`, e o `down()`
 * devolve todos como `new`. A fusão é o objetivo da mudança, não um efeito colateral — a
 * distinção que se perde é justamente a que passou a viver em `read_at`.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $tables = [
        'program_applications',
        'pickup_requests',
        'volunteer_applications',
        'partnership_inquiries',
        'contact_messages',
    ];

    private const AFTER = ['in_progress', 'done', 'archived'];

    private const BEFORE = ['new', 'in_progress', 'done', 'discarded'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            // O CHECK sai primeiro: ele ainda proíbe 'archived', então qualquer UPDATE para o
            // valor novo falharia antes de o constraint ser recriado.
            $this->dropStatusCheck($table);

            DB::table($table)->where('status', 'new')->update(['status' => 'in_progress']);
            DB::table($table)->where('status', 'discarded')->update(['status' => 'archived']);

            DB::statement("ALTER TABLE {$table} ALTER COLUMN status SET DEFAULT 'in_progress'");

            $this->addStatusCheck($table, self::AFTER);
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            $this->dropStatusCheck($table);

            DB::table($table)->where('status', 'archived')->update(['status' => 'discarded']);
            DB::table($table)->where('status', 'in_progress')->update(['status' => 'new']);

            DB::statement("ALTER TABLE {$table} ALTER COLUMN status SET DEFAULT 'new'");

            $this->addStatusCheck($table, self::BEFORE);
        }
    }

    private function dropStatusCheck(string $table): void
    {
        DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$table}_status_check");
    }

    /**
     * @param  list<string>  $values
     */
    private function addStatusCheck(string $table, array $values): void
    {
        $list = implode(',', array_map(fn (string $value): string => "'{$value}'", $values));

        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_status_check CHECK (status IN ({$list}))");
    }
};
