<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

/**
 * PDF mínimo, de uma página em branco — só para o arquivo abrir de verdade num leitor de PDF;
 * o conteúdo em si é irrelevante, é dado sintético (ver docs/protecao-de-dados.md, e CLAUDE.md
 * regra 10: nenhum arquivo real da instituição entra no repositório).
 *
 * Compartilhado entre TransparencyDocumentsSeeder e E2eSeeder — os dois precisam do mesmo
 * arquivo de mentira, e duas cópias divergiriam na primeira vez que uma delas mudasse.
 */
final class PlaceholderPdf
{
    public static function bytes(): string
    {
        return <<<'PDF'
            %PDF-1.4
            1 0 obj
            << /Type /Catalog /Pages 2 0 R >>
            endobj
            2 0 obj
            << /Type /Pages /Kids [3 0 R] /Count 1 >>
            endobj
            3 0 obj
            << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << >> >>
            endobj
            trailer
            << /Size 4 /Root 1 0 R >>
            %%EOF
            PDF;
    }
}
