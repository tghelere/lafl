<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * O Pix passou a vir de frontend-site/app/config/institution.ts (card em /doar); o corpo da
 * página "Doar" deixa de repetir a chave e de dizer que o site "não processa doações".
 *
 * Idempotente e conservadora: só troca se o conteúdo atual for EXATAMENTE o texto original do
 * conteúdo inicial. Se o Lar já editou a página, nada é tocado e o fato vai para o log. Rodar de
 * novo não faz nada. Os textos são copiados literalmente aqui, e não lidos de InitialPages,
 * porque InitialPages muda com o tempo.
 */
return new class extends Migration
{
    private const SLUG = 'como-ajudar/doar';

    private const OLD = '<p>O Lar Anália Franco ainda não processa doações em dinheiro diretamente pelo site. Doe por PIX ou transferência bancária:</p><ul><li><p><strong>PIX</strong> (chave CNPJ): 78.614.096/0001-75 — nome exibido: LAR ANALIA FRANCO DE LONDRINA</p></li><li><p><strong>Transferência:</strong> Banco do Brasil (001), agência 2755-3, conta corrente 4963-8</p></li></ul><p>Doações de itens para o Bazar Beneficente também sustentam diretamente a instituição.</p>';

    private const NEW = '<p>A doação vai direto do seu banco para a conta do Lar Anália Franco: o site não recebe nem guarda dados de pagamento. Doe por Pix, no quadro acima, ou por transferência bancária:</p><ul><li><p><strong>Transferência:</strong> Banco do Brasil (001), agência 2755-3, conta corrente 4963-8</p></li></ul><p>Doações de itens para o Bazar Beneficente também sustentam diretamente a instituição.</p>';

    public function up(): void
    {
        $this->swap(self::OLD, self::NEW);
    }

    public function down(): void
    {
        $this->swap(self::NEW, self::OLD);
    }

    private function swap(string $from, string $to): void
    {
        $page = DB::table('pages')->where('slug', self::SLUG)->first(['id', 'content']);

        if ($page === null) {
            Log::info('Página ausente, nada a trocar.', ['slug' => self::SLUG]);

            return;
        }

        if ($page->content === $to) {
            return;
        }

        if ($page->content !== $from) {
            Log::warning('Texto editado pelo Lar; migração não tocou.', ['slug' => self::SLUG]);

            return;
        }

        DB::table('pages')->where('id', $page->id)->update(['content' => $to]);
        Log::info('Texto trocado.', ['slug' => self::SLUG]);
    }
};
