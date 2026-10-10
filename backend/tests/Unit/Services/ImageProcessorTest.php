<?php

declare(strict_types=1);

use App\Services\Media\ImageProcessor;
use Tests\Support\Images;

/**
 * JPEG com os quatro quadrantes em cores distintas e o EXIF de orientação dado. Duas cores só
 * (como em Images::jpegWithExif) não distinguem espelho de giro.
 */
function quadrantJpeg(int $width, int $height, int $orientation): string
{
    $image = imagecreatetruecolor($width, $height);
    assert($image instanceof GdImage);
    $halfX = intdiv($width, 2);
    $halfY = intdiv($height, 2);
    imagefilledrectangle($image, 0, 0, $halfX - 1, $halfY - 1, (int) imagecolorallocate($image, 255, 0, 0));
    imagefilledrectangle($image, $halfX, 0, $width - 1, $halfY - 1, (int) imagecolorallocate($image, 0, 255, 0));
    imagefilledrectangle($image, 0, $halfY, $halfX - 1, $height - 1, (int) imagecolorallocate($image, 0, 0, 255));
    imagefilledrectangle($image, $halfX, $halfY, $width - 1, $height - 1, (int) imagecolorallocate($image, 255, 255, 0));
    ob_start();
    imagejpeg($image, null, 95);
    $path = (string) tempnam(sys_get_temp_dir(), 'laf-img-');
    file_put_contents($path, Images::withExif((string) ob_get_clean(), $orientation));

    return $path;
}

/** Nome da cor dominante no centro de cada quadrante, em ordem: topo-esq., topo-dir., base-esq., base-dir. */
function quadrantColors(GdImage $image): string
{
    $name = static function (int $x, int $y) use ($image): string {
        $c = imagecolorsforindex($image, imagecolorat($image, $x, $y));

        return match (true) {
            $c['red'] > 150 && $c['green'] > 150 => 'amarelo',
            $c['red'] > 150 => 'vermelho',
            $c['green'] > 150 => 'verde',
            default => 'azul',
        };
    };
    $w = imagesx($image);
    $h = imagesy($image);

    return implode(' ', [$name(intdiv($w, 4), intdiv($h, 4)), $name(intdiv(3 * $w, 4), intdiv($h, 4)), $name(intdiv($w, 4), intdiv(3 * $h, 4)), $name(intdiv(3 * $w, 4), intdiv(3 * $h, 4))]);
}

// Gravada: vermelho verde / azul amarelo. Esperado: como a foto deve aparecer, pela tabela do
// EXIF — 2 e 4 espelham, 3 gira 180°, 6 e 8 giram 90°, 5 e 7 são as transposições. Largura
// fora da grade de 16 (1000) exercita a emenda das faixas do giro anti-horário.
test('cada orientação do EXIF vira o pixel certo', function (int $orientation, string $expected, bool $swaps): void {
    $result = (new ImageProcessor)->process(quadrantJpeg(1000, 600, $orientation));
    $original = imagecreatefromstring($result->original);
    assert($original instanceof GdImage);

    expect(quadrantColors($original))->toBe($expected)
        ->and([$result->width, $result->height])->toBe($swaps ? [600, 1000] : [1000, 600])
        ->and([imagesx($original), imagesy($original)])->toBe([$result->width, $result->height]);
})->with([
    'sem giro (1)' => [1, 'vermelho verde azul amarelo', false],
    'espelho horizontal (2)' => [2, 'verde vermelho amarelo azul', false],
    '180° (3)' => [3, 'amarelo azul verde vermelho', false],
    'espelho vertical (4)' => [4, 'azul amarelo vermelho verde', false],
    'transposição (5)' => [5, 'vermelho azul verde amarelo', true],
    '90° horário (6)' => [6, 'azul vermelho amarelo verde', true],
    'transversal (7)' => [7, 'amarelo verde azul vermelho', true],
    '90° anti-horário (8)' => [8, 'verde amarelo vermelho azul', true],
]);

/**
 * O giro de 90° não pode voltar a manter duas cópias inteiras da foto vivas ao mesmo tempo
 * (com `imagerotate` na imagem toda, a foto em pé custava ~1,45× a deitada; ver
 * ImageProcessor::rotatedBands()). Comparação relativa, e não em MB, para não depender da
 * largura da imagem nem da memória que o resto da suíte já deixou alocada.
 *
 * Só mede com o GD embutido no PHP: com a libgd do sistema, o bitmap é alocado fora do
 * gerenciador de memória do PHP, e memory_get_peak_usage() não o enxerga.
 */
test('foto em pé não custa uma segunda cópia inteira da memória', function (): void {
    $peak = static function (string $path): int {
        gc_collect_cycles();
        memory_reset_peak_usage();
        $before = memory_get_usage();
        (new ImageProcessor)->process($path);

        return memory_get_peak_usage() - $before;
    };

    $flat = $peak(quadrantJpeg(4000, 3000, 1));
    $upright = $peak(quadrantJpeg(4000, 3000, 6));

    expect($upright)->toBeLessThan((int) ($flat * 1.2));
})->skip(! str_contains((string) gd_info()['GD Version'], 'bundled'), 'GD externo: o bitmap fica fora da contagem do PHP');
