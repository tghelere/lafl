<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Corrige o WhatsApp do bazar (era (43) 99950-0183, o certo é (43) 99995-0183) e publica a lista
 * do que o bazar aceita de doação, por pedido do Lar.
 *
 * Idempotente e conservadora: cada coluna só é trocada se o conteúdo atual no banco for
 * EXATAMENTE o texto original do conteúdo inicial. Se o Lar já editou o bloco, nada é tocado e
 * o fato vai para o log. Rodar de novo não faz nada (o texto original já não está lá).
 *
 * Os textos são copiados literalmente aqui, e não lidos de InitialPages, porque InitialPages
 * muda com o tempo e esta migração precisa comparar com o texto de quando foi escrita.
 */
return new class extends Migration
{
    private const VISITE_CONTENT_OLD = '<p>O Bazar Beneficente funciona em endereço próprio, separado da sede do Lar Anália Franco: Rua Rosa Siqueira, 152, Jd. Aeroporto, Londrina/PR. Telefone (43) 3322-2373 ou <a target="_blank" href="https://wa.me/5543999500183?text&#61;Ol%C3%A1!%20Gostaria%20de%20agendar%20uma%20coleta%20de%20doa%C3%A7%C3%A3o%20para%20o%20Bazar%20Beneficente." rel="noopener noreferrer">WhatsApp (43) 99950-0183</a>.</p>';

    private const VISITE_CONTENT_NEW = '<p>O Bazar Beneficente funciona em endereço próprio, separado da sede do Lar Anália Franco: Rua Rosa Siqueira, 152, Jd. Aeroporto, Londrina/PR. Telefone (43) 3322-2373 ou <a target="_blank" href="https://wa.me/5543999950183?text&#61;Ol%C3%A1!%20Gostaria%20de%20agendar%20uma%20coleta%20de%20doa%C3%A7%C3%A3o%20para%20o%20Bazar%20Beneficente." rel="noopener noreferrer">WhatsApp (43) 99995-0183</a>.</p>';

    private const ACEITAMOS_CONTENT_OLD = '<p>O Bazar Beneficente recebe doações de itens para revenda na loja física da instituição.</p><p>A lista detalhada do que é aceito — e do que não é — está em confirmação com a equipe do bazar. Para <a href="/bazar/agendar-coleta">agendar uma coleta</a>, o jeito mais rápido é pelo <a target="_blank" href="https://wa.me/5543999500183?text&#61;Ol%C3%A1!%20Gostaria%20de%20agendar%20uma%20coleta%20de%20doa%C3%A7%C3%A3o%20para%20o%20Bazar%20Beneficente." rel="noopener noreferrer">WhatsApp (43) 99950-0183</a>.</p>';

    private const ACEITAMOS_CONTENT_NEW = '<p>O Bazar Beneficente recebe doações de itens para revenda na loja física da instituição.</p><p>O bazar aceita de doação: móveis (sem a necessidade de reformas), eletros (funcionando ou não), roupas, calçados, brinquedos, livros, utensílios de decoração e cozinha.</p><p>Para <a href="/bazar/agendar-coleta">agendar uma coleta</a>, o jeito mais rápido é pelo <a target="_blank" href="https://wa.me/5543999950183?text&#61;Ol%C3%A1!%20Gostaria%20de%20agendar%20uma%20coleta%20de%20doa%C3%A7%C3%A3o%20para%20o%20Bazar%20Beneficente." rel="noopener noreferrer">WhatsApp (43) 99995-0183</a>.</p>';

    public function up(): void
    {
        $this->swap(self::changes(), reverse: false);
    }

    public function down(): void
    {
        $this->swap(self::changes(), reverse: true);
    }

    /**
     * @return list<array{slug: string, column: string, old: string, new: string}>
     */
    private static function changes(): array
    {
        return [
            ['slug' => 'bazar/visite-a-loja', 'column' => 'content', 'old' => self::VISITE_CONTENT_OLD, 'new' => self::VISITE_CONTENT_NEW],
            ['slug' => 'bazar/o-que-aceitamos', 'column' => 'content', 'old' => self::ACEITAMOS_CONTENT_OLD, 'new' => self::ACEITAMOS_CONTENT_NEW],
        ];
    }

    /**
     * @param  list<array{slug: string, column: string, old: string, new: string}>  $changes
     */
    private function swap(array $changes, bool $reverse): void
    {
        foreach ($changes as $change) {
            $from = $reverse ? $change['new'] : $change['old'];
            $to = $reverse ? $change['old'] : $change['new'];

            $page = DB::table('pages')->where('slug', $change['slug'])->first(['id', $change['column']]);

            if ($page === null) {
                Log::info('Página ausente, nada a trocar.', ['slug' => $change['slug'], 'column' => $change['column']]);

                continue;
            }

            $current = $page->{$change['column']};

            if ($current === $to) {
                continue;
            }

            if ($current !== $from) {
                Log::warning('Texto editado pelo Lar; migração não tocou.', ['slug' => $change['slug'], 'column' => $change['column']]);

                continue;
            }

            DB::table('pages')->where('id', $page->id)->update([$change['column'] => $to]);
            Log::info('Texto trocado.', ['slug' => $change['slug'], 'column' => $change['column']]);
        }
    }
};
