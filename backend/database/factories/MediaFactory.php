<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Só a linha no banco — sem arquivo no disco. Teste que precisa do arquivo sobe a imagem pela
 * API (App\Actions\Media\StoreMedia), que é o único caminho que gera original e derivadas.
 *
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    protected $model = Media::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'alt' => fake()->sentence(6),
            'caption' => null,
            'depicts_assisted_minor' => false,
            'version' => 1,
            'mime' => 'image/jpeg',
            'extension' => 'jpg',
            'size' => 120_000,
            'width' => 1280,
            'height' => 960,
            'widths' => [400, 640, 960, 1280],
            'sha256' => hash('sha256', fake()->uuid()),
        ];
    }

    public function depictingAssistedMinor(): static
    {
        return $this->state(fn (): array => ['depicts_assisted_minor' => true]);
    }
}
