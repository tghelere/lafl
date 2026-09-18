<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TransparencyDocumentType;
use App\Models\TransparencyDocument;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TransparencyDocument>
 */
class TransparencyDocumentFactory extends Factory
{
    protected $model = TransparencyDocument::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            // A Action gera o slug na criação (App\Actions\Transparency\SaveTransparencyDocument);
            // a factory cria o registro sem passar por ela, então gera aqui, a partir do
            // título que de fato ficou (inclusive quando o teste passa o seu). O sufixo
            // sorteado é obrigatório: a coluna é UNIQUE e `count()` resolve todos os
            // atributos antes de gravar qualquer linha, então dois títulos iguais no mesmo
            // lote não se enxergariam.
            'slug' => fn (array $attributes): string => Str::slug((string) $attributes['title'])
                .'-'.fake()->unique()->numberBetween(1, 1_000_000),
            'year' => fake()->numberBetween(2015, 2026),
            'type' => fake()->randomElement(TransparencyDocumentType::cases()),
            'file_path' => 'transparency-documents/'.fake()->uuid().'.pdf',
            'file_size' => fake()->numberBetween(1_000, 500_000),
            'download_count' => 0,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'published_at' => now(),
        ]);
    }
}
