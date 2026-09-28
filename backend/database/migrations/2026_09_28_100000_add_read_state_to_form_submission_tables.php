<?php

declare(strict_types=1);

use App\Support\Migrations\FormSubmissionColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leitura de formulário recebido, compartilhada pela equipe: `read_at` guarda quando o registro
 * foi aberto pela primeira vez e `read_by` quem abriu.
 *
 * Compartilhada, e não por usuário: a caixa de entrada é de uma equipe pequena, e o que
 * interessa a quem chega é "alguém já olhou isto?", não "eu já olhei isto?". Uma tabela de
 * leitura por usuário responderia a segunda pergunta e deixaria a primeira sem resposta —
 * cada pessoa veria a mesma mensagem como nova. Ver
 * docs/decisoes/0021-leitura-separada-do-status-de-atendimento.md.
 *
 * `read_by` nullable e `nullOnDelete` pelo mesmo motivo de `handled_by`: conta de usuário é
 * desativada, não apagada (ver docs/dominio.md, "Contas"), mas se um dia uma linha de `users`
 * sair, o registro do formulário não pode ir junto.
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

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                FormSubmissionColumns::addReadState($blueprint);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropIndex(['read_at']);
                $blueprint->dropConstrainedForeignId('read_by');
                $blueprint->dropColumn('read_at');
            });
        }
    }
};
