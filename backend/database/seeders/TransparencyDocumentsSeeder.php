<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\TransparencyDocumentType;
use App\Models\TransparencyDocument;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TransparencyDocumentsSeeder extends Seeder
{
    /**
     * Documentos de exemplo para o acervo de transparência — só roda em local/testing.
     * Nenhum arquivo real da instituição: cada PDF é um placeholder mínimo gerado aqui mesmo,
     * nunca commitado no repositório (ver CLAUDE.md, regra 10).
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        foreach ($this->documents() as $data) {
            $path = 'transparency-documents/'.Str::uuid().'.pdf';
            $bytes = $this->placeholderPdf();

            Storage::disk('local')->put($path, $bytes);

            TransparencyDocument::query()->create([
                'title' => $data['title'],
                'year' => $data['year'],
                'type' => $data['type'],
                'file_path' => $path,
                'file_size' => strlen($bytes),
                'published_at' => now(),
            ]);
        }
    }

    /**
     * @return list<array{title: string, year: int, type: TransparencyDocumentType}>
     */
    private function documents(): array
    {
        return [
            ['title' => 'Balanço patrimonial 2024', 'year' => 2024, 'type' => TransparencyDocumentType::Balance],
            ['title' => 'Balanço patrimonial 2023', 'year' => 2023, 'type' => TransparencyDocumentType::Balance],
            ['title' => 'Balanço patrimonial 2022', 'year' => 2022, 'type' => TransparencyDocumentType::Balance],
            ['title' => 'Estatuto social', 'year' => 2022, 'type' => TransparencyDocumentType::Bylaws],
            ['title' => 'Ata de assembleia geral — eleição de diretoria 2022', 'year' => 2022, 'type' => TransparencyDocumentType::Minutes],
            ['title' => 'Ata de assembleia geral ordinária 2024', 'year' => 2024, 'type' => TransparencyDocumentType::Minutes],
            ['title' => 'Certidão de regularidade — CEBAS', 'year' => 2024, 'type' => TransparencyDocumentType::Certificate],
            ['title' => 'Prestação de contas do convênio — CEI Tio Pedro 2024', 'year' => 2024, 'type' => TransparencyDocumentType::AgreementAccounting],
            ['title' => 'Prestação de contas do convênio — CEI Tio Pedro 2023', 'year' => 2023, 'type' => TransparencyDocumentType::AgreementAccounting],
            ['title' => 'Edital de chamamento público 2024', 'year' => 2024, 'type' => TransparencyDocumentType::Notice],
            ['title' => 'Relatório anual de atividades 2024', 'year' => 2024, 'type' => TransparencyDocumentType::AnnualReport],
            ['title' => 'Relatório anual de atividades 2023', 'year' => 2023, 'type' => TransparencyDocumentType::AnnualReport],
        ];
    }

    /**
     * PDF mínimo, de uma página em branco — só para o arquivo abrir de verdade num leitor de
     * PDF; o conteúdo em si é irrelevante, é dado sintético (ver docs/protecao-de-dados.md).
     */
    private function placeholderPdf(): string
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
