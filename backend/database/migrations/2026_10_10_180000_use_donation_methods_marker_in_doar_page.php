<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * O corpo da página "Doar" passa a ser: frase de abertura, marcador {{formas_de_doacao}} (os dois
 * cartões — Pix e transferência —, renderizados pelo site a partir de
 * frontend-site/app/config/institution.ts) e a frase do bazar. Some a lista que repetia a chave
 * Pix e os dados bancários.
 *
 * Idempotente e conservadora: só troca se o conteúdo atual for EXATAMENTE o texto original do
 * conteúdo inicial ou o da migração anterior (2026_10_10_150000). Se o Lar já editou a página,
 * nada é tocado e o fato vai para o log com warning. Rodar de novo não faz nada. O `down`
 * devolve o texto da migração anterior, e também só se o atual for o desta. Os textos são
 * copiados literalmente aqui, e não lidos de InitialPages, porque InitialPages muda com o tempo.
 */
return new class extends Migration
{
    private const SLUG = 'como-ajudar/doar';

    private const ORIGINAL = '<p>O Lar Anália Franco ainda não processa doações em dinheiro diretamente pelo site. Doe por PIX ou transferência bancária:</p><ul><li><p><strong>PIX</strong> (chave CNPJ): 78.614.096/0001-75 — nome exibido: LAR ANALIA FRANCO DE LONDRINA</p></li><li><p><strong>Transferência:</strong> Banco do Brasil (001), agência 2755-3, conta corrente 4963-8</p></li></ul><p>Doações de itens para o Bazar Beneficente também sustentam diretamente a instituição.</p>';

    private const PREVIOUS = '<p>A doação vai direto do seu banco para a conta do Lar Anália Franco: o site não recebe nem guarda dados de pagamento. Doe por Pix, no quadro acima, ou por transferência bancária:</p><ul><li><p><strong>Transferência:</strong> Banco do Brasil (001), agência 2755-3, conta corrente 4963-8</p></li></ul><p>Doações de itens para o Bazar Beneficente também sustentam diretamente a instituição.</p>';

    private const CURRENT = '<p>O Lar Anália Franco ainda não processa doações em dinheiro diretamente pelo site. Doe por PIX ou transferência bancária:</p><p>{{formas_de_doacao}}</p><p>Doações de itens para o Bazar Beneficente também sustentam diretamente a instituição.</p>';

    public function up(): void
    {
        $this->swap([self::ORIGINAL, self::PREVIOUS], self::CURRENT);
    }

    public function down(): void
    {
        $this->swap([self::CURRENT], self::PREVIOUS);
    }

    /**
     * @param  list<string>  $from
     */
    private function swap(array $from, string $to): void
    {
        $page = DB::table('pages')->where('slug', self::SLUG)->first(['id', 'content']);

        if ($page === null) {
            Log::info('Página ausente, nada a trocar.', ['slug' => self::SLUG]);

            return;
        }

        if ($page->content === $to) {
            return;
        }

        if (! in_array($page->content, $from, true)) {
            Log::warning('Texto editado pelo Lar; migração não tocou.', ['slug' => self::SLUG]);

            return;
        }

        DB::table('pages')->where('id', $page->id)->update(['content' => $to]);
        Log::info('Texto trocado.', ['slug' => self::SLUG]);
    }
};
