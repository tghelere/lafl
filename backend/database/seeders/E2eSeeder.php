<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\TransparencyDocumentType;
use App\Models\TransparencyDocument;
use App\Models\User;
use Database\Seeders\Support\PlaceholderPdf;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Estado inicial da bateria de ponta a ponta (ver e2e/README.md). Roda SÓ no ambiente `e2e` —
 * as contas aqui têm senha conhecida e fixa, então existir em qualquer outro ambiente seria
 * uma porta dos fundos. É a mesma disciplina de DevSuperAdminSeeder, com a lista de ambientes
 * ainda mais estreita.
 *
 * Nada aqui é dado real: nomes, e-mails (@e2e.local, domínio reservado) e documentos são
 * sintéticos (CLAUDE.md, regra 10).
 *
 * O que este seeder cria, e por quê:
 * - um usuário por papel, mais um que acumula dois papéis, para os testes de menu e de
 *   acesso por papel;
 * - um segundo super_admin, para que desativar/alterar o primeiro nunca esbarre em
 *   App\Actions\Users\AssertLastActiveSuperAdminSurvives por acidente;
 * - usuários dedicados aos testes de login (senha errada, conta desativada, troca da própria
 *   senha), porque esses testes mexem no estado da conta e não podem compartilhar o
 *   storageState dos usuários de papel;
 * - o conteúdo institucional inteiro (ContentPagesSeeder) e o acervo de transparência
 *   (TransparencyDocumentsSeeder), para os testes rodarem contra volume realista — inclusive
 *   páginas com botão (contraturno), link externo (bazar/visite-a-loja) e lista
 *   (quem-somos/nossa-historia), que o teste de ida e volta do editor usa direto: desde que
 *   ContentPagesSeeder guarda o content em forma canônica do editor (ver o comentário no
 *   topo daquele arquivo), não há mais motivo para manter cópia sintética só para isso;
 * - um documento de transparência propositalmente fora da primeira página da listagem
 *   administrativa.
 */
class E2eSeeder extends Seeder
{
    /**
     * Senha única de todas as contas criadas aqui. Espelhada em e2e/support/users.ts — mudar
     * de um lado exige mudar do outro.
     */
    public const PASSWORD = 'senha-de-teste-e2e';

    /**
     * Ano e título do documento que a bateria usa para provar que o filtro alcança o que a
     * primeira página da listagem não mostra. Ele nasce com `updated_at` cinco anos atrás e a
     * listagem administrativa ordena por `updated_at` decrescente, então ele é sempre o
     * último de todos — com 16 documentos e 15 por página, sempre na segunda.
     */
    public const MARKER_DOCUMENT_YEAR = 2019;

    public const MARKER_DOCUMENT_TITLE = 'Prestação de contas do convênio — CEI Anália Franco 2019';

    public function run(): void
    {
        // Ambiente `e2e` e nada mais: as senhas abaixo são públicas (estão neste arquivo e em
        // e2e/support/users.ts). A guarda de banco correspondente está em
        // App\Console\Commands\PrepareE2eDatabase, que é quem chama este seeder.
        if (! app()->environment('e2e')) {
            return;
        }

        $this->seedUsers();
        $this->seedPages();
        $this->seedTransparencyDocuments();
    }

    /**
     * Um usuário por cenário. `deactivated` e a lista de papéis são o que cada teste precisa;
     * a senha é a mesma para todos (ver PASSWORD).
     */
    private function seedUsers(): void
    {
        foreach ($this->users() as $data) {
            $user = User::query()->firstOrNew(['email' => $data['email']]);

            // forceFill, e não fill/updateOrCreate: `email_verified_at` e `deactivated_at`
            // ficam de fora do #[Fillable] de App\Models\User de propósito (quem desativa uma
            // conta é App\Actions\Users\DeactivateUser, não uma atribuição em massa), e por
            // atribuição em massa os dois seriam descartados em silêncio. O cast `hashed` de
            // `password` continua valendo — forceFill passa por setAttribute como qualquer
            // outra escrita.
            $user->forceFill([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => self::PASSWORD,
                'email_verified_at' => now(),
                'deactivated_at' => ($data['deactivated'] ?? false) ? now() : null,
            ])->save();

            $user->syncRoles($data['roles']);
        }
    }

    /**
     * @return list<array{email: string, name: string, roles: list<string>, deactivated?: bool}>
     */
    private function users(): array
    {
        return [
            // Um por papel — base dos testes de menu, dashboard e acesso por URL.
            ['email' => 'super.admin@e2e.local', 'name' => 'Sara Super Admin', 'roles' => [Role::SuperAdmin->value]],
            ['email' => 'direcao@e2e.local', 'name' => 'Dora Direção', 'roles' => [Role::Direcao->value]],
            ['email' => 'financeiro@e2e.local', 'name' => 'Fabio Financeiro', 'roles' => [Role::Financeiro->value]],
            ['email' => 'contraturno@e2e.local', 'name' => 'Clara Contraturno', 'roles' => [Role::Contraturno->value]],
            ['email' => 'bazar@e2e.local', 'name' => 'Beto Bazar', 'roles' => [Role::Bazar->value]],
            ['email' => 'atendimento@e2e.local', 'name' => 'Alice Atendimento', 'roles' => [Role::Atendimento->value]],
            ['email' => 'comunicacao@e2e.local', 'name' => 'Cauê Comunicação', 'roles' => [Role::Comunicacao->value]],

            // Acúmulo de papéis: o menu tem de somar os dois blocos sem nenhum caso especial
            // no painel (ver frontend-admin/src/components/AppSidebar.vue).
            [
                'email' => 'financeiro.contraturno@e2e.local',
                'name' => 'Marta Multipapel',
                'roles' => [Role::Financeiro->value, Role::Contraturno->value],
            ],

            // Segundo super_admin: sem ele, qualquer teste que desative ou mexa nos papéis do
            // primeiro esbarraria na proteção do último super_admin ativo (ver
            // App\Actions\Users\AssertLastActiveSuperAdminSurvives) e falharia por um motivo
            // que não é o que estava sendo testado.
            ['email' => 'super.admin.reserva@e2e.local', 'name' => 'Rui Reserva', 'roles' => [Role::SuperAdmin->value]],

            // Contas dos testes de login e de troca de senha. Ficam de fora do storageState
            // compartilhado de propósito: esses testes alteram o estado da própria conta
            // (senha, sessão), e uma conta compartilhada tornaria a bateria dependente da
            // ordem dos testes.
            ['email' => 'login.valido@e2e.local', 'name' => 'Lina Login', 'roles' => [Role::Atendimento->value]],
            ['email' => 'login.senha-errada@e2e.local', 'name' => 'Sérgio Senha', 'roles' => [Role::Atendimento->value]],
            ['email' => 'login.desativado@e2e.local', 'name' => 'Davi Desativado', 'roles' => [Role::Atendimento->value], 'deactivated' => true],
            ['email' => 'troca.de.senha@e2e.local', 'name' => 'Tereza Troca', 'roles' => [Role::Atendimento->value]],
        ];
    }

    private function seedPages(): void
    {
        // Conteúdo institucional inteiro: dá volume realista à listagem (mais de uma página de
        // paginação), é o que o teste de busca por título usa, e fornece as páginas reais que
        // o teste de ida e volta do editor abre e salva sem alterar (contraturno, com botão;
        // bazar/visite-a-loja, com link externo; quem-somos/nossa-historia, com lista).
        $this->call(ContentPagesSeeder::class);
    }

    private function seedTransparencyDocuments(): void
    {
        // Doze documentos, todos com updated_at = agora.
        $this->call(TransparencyDocumentsSeeder::class);

        // Mais três, só para o acervo passar de 15 (o padrão de itens por página da listagem
        // administrativa) e o documento-marco abaixo cair mesmo na segunda página.
        foreach ([2021, 2020, 2018] as $year) {
            $this->createDocument("Relatório anual de atividades {$year}", $year, TransparencyDocumentType::AnnualReport, now());
        }

        $this->createDocument(
            self::MARKER_DOCUMENT_TITLE,
            self::MARKER_DOCUMENT_YEAR,
            TransparencyDocumentType::AgreementAccounting,
            now()->subYears(5),
        );
    }

    private function createDocument(string $title, int $year, TransparencyDocumentType $type, \DateTimeInterface $updatedAt): void
    {
        $path = 'transparency-documents/'.Str::uuid().'.pdf';
        $bytes = PlaceholderPdf::bytes();

        Storage::disk('local')->put($path, $bytes);

        $document = TransparencyDocument::query()->create([
            'title' => $title,
            'year' => $year,
            'type' => $type,
            'file_path' => $path,
            'file_size' => strlen($bytes),
            'published_at' => now(),
        ]);

        // `updated_at` é gerido pelo Eloquent e seria sobrescrito por qualquer save() —
        // atualizar direto na tabela é o jeito de fixar a posição do documento na ordenação
        // sem desligar os timestamps do model inteiro.
        DB::table('transparency_documents')
            ->where('id', $document->getKey())
            ->update(['updated_at' => $updatedAt]);
    }
}
