<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ContentMarker;
use App\Models\TransparencyDocument;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use RuntimeException;

/**
 * Fonte única dos números institucionais que envelhecem: idade de cada marco de
 * `config/institution.php` e a quantidade de documentos de transparência publicados.
 * Nenhum desses números é digitado em texto de página, em .vue ou em seeder — ver
 * docs/tarefas/02-numeros-calculados.md.
 *
 * A saída vem JÁ FORMATADA em português, com o substantivo e o plural corretos
 * ("1 ano"/"73 anos", "1 documento"/"71 documentos", milhar com ponto). É o que impede que
 * um texto do site diga "71 documento" ou "1 documentos": quem consome nunca monta a frase.
 *
 * Cada valor é calculado uma vez por instância — a contagem é uma consulta ao banco, e uma
 * página com dois marcadores não precisa de duas. A memória para aí, de propósito: NÃO
 * registrar esta classe como singleton no container. A instância que o container injeta em
 * App\Actions\Content\ResolveContentMarkers já vive exatamente uma requisição, que é o
 * tempo em que os valores são estáveis; um singleton sobreviveria à requisição (em teste, e
 * sob Octane também em produção) e serviria a contagem de antes de o documento ser
 * publicado.
 */
final class InstitutionalFacts
{
    /** @var array<string, int>|null */
    private ?array $ages = null;

    private ?int $publishedDocuments = null;

    /**
     * Valor já formatado de um marcador de conteúdo.
     */
    public function value(ContentMarker $marker): string
    {
        $milestone = $marker->milestone();

        return $milestone !== null
            ? $this->formatYears($this->ageInYears($milestone))
            : $this->formatDocuments($this->publishedTransparencyDocumentCount());
    }

    /**
     * Todos os marcadores com o valor de agora, na ordem em que o enum os declara.
     *
     * @return array<string, string>
     */
    public function values(): array
    {
        $values = [];

        foreach (ContentMarker::cases() as $marker) {
            $values[$marker->value] = $this->value($marker);
        }

        return $values;
    }

    /**
     * Cada marco com o ano, a data completa quando ela é conhecida, e a idade crua e
     * formatada — é o que alimenta o endpoint público de fatos, para as páginas fixas em
     * .vue que não passam pelo CMS (ver frontend-site/app/pages/index.vue).
     *
     * @return array<string, array{year: int, date: string|null, age_years: int, age: string}>
     */
    public function milestones(): array
    {
        $milestones = [];

        foreach ($this->configuredMilestones() as $key => $raw) {
            $age = $this->ageInYears($key);

            $milestones[$key] = [
                'year' => (int) substr($raw, 0, 4),
                'date' => $this->hasFullDate($raw) ? $raw : null,
                'age_years' => $age,
                'age' => $this->formatYears($age),
            ];
        }

        return $milestones;
    }

    /**
     * Mesmo critério da listagem pública (App\Actions\Transparency\
     * ListPublicTransparencyDocuments): despublicado não conta, excluído também não — o
     * escopo `published()` filtra o primeiro e o SoftDeletes do model, o segundo.
     */
    public function publishedTransparencyDocumentCount(): int
    {
        return $this->publishedDocuments ??= TransparencyDocument::query()->published()->count();
    }

    public function ageInYears(string $milestone): int
    {
        $ages = $this->ages ??= $this->computeAges();

        if (! array_key_exists($milestone, $ages)) {
            throw new InvalidArgumentException(
                "Marco institucional desconhecido: \"{$milestone}\". Ver config/institution.php."
            );
        }

        return $ages[$milestone];
    }

    public function formatYears(int $years): string
    {
        return $this->formatQuantity($years, 'ano', 'anos');
    }

    public function formatDocuments(int $documents): string
    {
        return $this->formatQuantity($documents, 'documento', 'documentos');
    }

    /**
     * Plural do português, que aqui é regular (zero vai no plural: "0 documentos"), e milhar
     * com ponto.
     */
    private function formatQuantity(int $quantity, string $singular, string $plural): string
    {
        return number_format($quantity, 0, ',', '.').' '.($quantity === 1 ? $singular : $plural);
    }

    /**
     * Idade de cada marco no dia de hoje em `config('institution.timezone')`, nunca no fuso
     * da aplicação (UTC): entre 21h e a meia-noite de Londrina o UTC já virou o dia seguinte,
     * e o site anunciaria o aniversário três horas antes.
     *
     * Data completa: o aniversário é respeitado — em 11/07/2026 a associação fundada em
     * 12/07/1953 ainda tem 72 anos, e só em 12/07 passa a 73.
     *
     * IMPRECISÃO CONHECIDA, marco com só o ano: a idade sai por diferença de ano, porque o
     * dia não é conhecido (bazar, 1968; CEI, 2002 — ver docs/contexto.md). De 1º de janeiro
     * até o aniversário real, esse número fica um ano adiantado. É deliberado: o ano é o que
     * está documentado. Confirmando o dia, basta trocar o valor em config/institution.php por
     * uma data completa e a conta passa a respeitar o aniversário sem mais nenhuma mudança.
     *
     * @return array<string, int>
     */
    private function computeAges(): array
    {
        $timezone = $this->timezone();
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $ages = [];

        foreach ($this->configuredMilestones() as $key => $raw) {
            $ages[$key] = $this->hasFullDate($raw)
                ? (int) CarbonImmutable::parse($raw, $timezone)->startOfDay()->diffInYears($today)
                : $today->year - (int) $raw;
        }

        return $ages;
    }

    private function hasFullDate(string $milestone): bool
    {
        return strlen($milestone) > 4;
    }

    /**
     * @return array<string, string>
     */
    private function configuredMilestones(): array
    {
        $milestones = config('institution.milestones');

        if (! is_array($milestones) || $milestones === []) {
            throw new RuntimeException('config/institution.php não declara nenhum marco em "milestones".');
        }

        $validated = [];

        foreach ($milestones as $key => $raw) {
            if (! is_string($key) || ! is_string($raw) || preg_match('/^\d{4}(-\d{2}-\d{2})?$/', $raw) !== 1) {
                throw new RuntimeException(
                    'Todo marco de config/institution.php precisa ser "AAAA" ou "AAAA-MM-DD" — a precisão do '
                    .'valor é o que define como a idade é calculada.'
                );
            }

            $validated[$key] = $raw;
        }

        return $validated;
    }

    private function timezone(): string
    {
        $timezone = config('institution.timezone');

        if (! is_string($timezone) || $timezone === '') {
            throw new RuntimeException('config/institution.php precisa declarar "timezone".');
        }

        return $timezone;
    }
}
