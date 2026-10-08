<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Tira do texto das páginas publicadas os valores que mudam com frequência (repasse da
 * prefeitura, nº do termo, nº de turmas), por pedido do financeiro do Lar.
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
    private const EDUCACAO_INFANTIL_META_OLD = 'CEI Anália Franco: creche e pré-escola do Lar Anália Franco para crianças de 1 a 5 anos, 15 turmas, período integral, conveniada com a Prefeitura de Londrina.';

    private const EDUCACAO_INFANTIL_META_NEW = 'CEI Anália Franco: creche e pré-escola do Lar Anália Franco para crianças de 1 a 5 anos, período integral, conveniada com a Prefeitura de Londrina.';

    private const EDUCACAO_INFANTIL_CONTENT_OLD = '<p>O Centro de Educação Infantil Anália Franco é a creche e pré-escola do Lar Anália Franco, para crianças de 1 a 5 anos, em 15 turmas (C1 a P5, plano de trabalho do Termo de Colaboração 06/2022, exercício 2026). Funciona em período integral, das 7h30 às 17h30, com convênio junto à Secretaria Municipal de Educação de Londrina.</p><p>O CEI Anália Franco foi criado em 2002 e tem autorização de funcionamento renovada até janeiro de 2028. A rotina inclui aulas de inglês, educação física, horta e uso da quadra poliesportiva do terreno.</p><p>As páginas desta seção detalham a proposta pedagógica, a alimentação e a estrutura física do CEI, além de como fazer a <a href="/educacao-infantil/matricula">matrícula</a>.</p>';

    private const EDUCACAO_INFANTIL_CONTENT_NEW = '<p>O Centro de Educação Infantil Anália Franco é a creche e pré-escola do Lar Anália Franco, para crianças de 1 a 5 anos. Funciona em período integral, das 7h30 às 17h30, com convênio junto à Secretaria Municipal de Educação de Londrina.</p><p>O CEI Anália Franco foi criado em 2002 e tem autorização de funcionamento renovada até janeiro de 2028. A rotina inclui aulas de inglês, educação física, horta e uso da quadra poliesportiva do terreno.</p><p>As páginas desta seção detalham a proposta pedagógica, a alimentação e a estrutura física do CEI, além de como fazer a <a href="/educacao-infantil/matricula">matrícula</a>.</p>';

    private const TRANSPARENCIA_CONTENT_OLD = '<p>O Lar Anália Franco mantém um acervo de prestação de contas — balanços, atas, editais e relatórios — hoje com {{documentos_transparencia}}.</p><p>Reunimos esses documentos nesta página para que qualquer pessoa consiga localizar o que precisa.</p><p>O convênio com a Secretaria Municipal de Educação de Londrina, que sustenta o Centro de Educação Infantil Anália Franco, também exige prestação de contas periódica. O repasse municipal previsto para 2026 é de R$ 2.819.892,84 (Termo de Colaboração 06/2022).</p><p>Acesse o <a href="/transparencia/documentos">acervo de documentos</a>, com filtro por ano e por tipo.</p>';

    private const TRANSPARENCIA_CONTENT_NEW = '<p>O Lar Anália Franco mantém um acervo de prestação de contas — balanços, atas, editais e relatórios — hoje com {{documentos_transparencia}}.</p><p>Reunimos esses documentos nesta página para que qualquer pessoa consiga localizar o que precisa.</p><p>O convênio com a Secretaria Municipal de Educação de Londrina, que sustenta o Centro de Educação Infantil Anália Franco, também exige prestação de contas periódica.</p><p>Acesse o <a href="/transparencia/documentos">acervo de documentos</a>, com filtro por ano e por tipo.</p>';

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
            ['slug' => 'educacao-infantil', 'column' => 'meta_description', 'old' => self::EDUCACAO_INFANTIL_META_OLD, 'new' => self::EDUCACAO_INFANTIL_META_NEW],
            ['slug' => 'educacao-infantil', 'column' => 'content', 'old' => self::EDUCACAO_INFANTIL_CONTENT_OLD, 'new' => self::EDUCACAO_INFANTIL_CONTENT_NEW],
            ['slug' => 'transparencia', 'column' => 'content', 'old' => self::TRANSPARENCIA_CONTENT_OLD, 'new' => self::TRANSPARENCIA_CONTENT_NEW],
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
