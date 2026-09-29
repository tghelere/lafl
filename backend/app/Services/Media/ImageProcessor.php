<?php

declare(strict_types=1);

namespace App\Services\Media;

use GdImage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Transforma o arquivo enviado na original limpa mais as derivadas webp.
 *
 * **EXIF sai sempre (CLAUDE.md, regra 7), e sai por reconstrução, não por remoção.** A
 * original é decodificada em pixels e codificada de novo: o arquivo gravado nasce do zero e
 * não carrega nenhum bloco do enviado — EXIF, GPS, XMP, IPTC, comentário, miniatura
 * embutida. Apagar segmentos conhecidos de um arquivo que veio de fora seria uma lista de
 * exclusão, e lista de exclusão esquece o formato que ninguém previu.
 *
 * O preço, declarado: JPEG recodificado em qualidade 90 perde um pouco (imperceptível para
 * uso na web), e o perfil de cor embutido também sai — foto em Display P3 fica levemente
 * menos saturada. PNG é recodificado sem perda.
 *
 * A orientação da câmera é gravada no pixel ANTES de o EXIF sumir. Sem isto, foto de celular
 * tirada em pé apareceria deitada, porque é o EXIF que manda o navegador girar.
 *
 * Sem dependência nova: GD já é extensão exigida pelo servidor (infra/provisionar.sh) e tem
 * suporte a WebP.
 */
final class ImageProcessor
{
    /** Escala do projeto, a mesma das fotos fixas do site (docs/fotos.md). */
    public const WIDTHS = [400, 640, 960, 1280, 1920];

    /**
     * Teto em pixels, conferido pelo cabeçalho ANTES de decodificar. Medido com o
     * `memory_limit` de 256M do servidor (infra/provisionar.sh): 30 MP chegam a ~162 MB de pico
     * no processamento, 36 MP a ~192 MB — esta última deixaria pouca folga para o resto da
     * requisição. 30 MP cobrem o modo padrão das câmeras de celular. Sem o teto, um PNG pequeno
     * em bytes e enorme em pixels derrubaria o processo do PHP-FPM.
     */
    public const MAX_PIXELS = 30_000_000;

    private const JPEG_QUALITY = 90;

    private const WEBP_ORIGINAL_QUALITY = 90;

    private const WEBP_DERIVATIVE_QUALITY = 80;

    /** @var array<int, array{mime: string, extension: string}> */
    private const TYPES = [
        IMAGETYPE_JPEG => ['mime' => 'image/jpeg', 'extension' => 'jpg'],
        IMAGETYPE_PNG => ['mime' => 'image/png', 'extension' => 'png'],
        IMAGETYPE_WEBP => ['mime' => 'image/webp', 'extension' => 'webp'],
    ];

    public function process(string $path): ProcessedImage
    {
        $info = @getimagesize($path);

        if ($info === false || ! isset(self::TYPES[$info[2]])) {
            $this->fail('A imagem precisa ser JPEG, PNG ou WebP.');
        }

        if ($info[0] * $info[1] > self::MAX_PIXELS) {
            $this->fail('A imagem é grande demais (mais de 30 megapixels). Reduza as dimensões e envie de novo.');
        }

        $type = $info[2];
        $image = $this->decode($path, $type);

        if ($type === IMAGETYPE_JPEG) {
            $image = $this->applyExifOrientation($image, $path);
        }

        $width = imagesx($image);
        $height = imagesy($image);

        $original = $this->encode($image, $type);

        $derivatives = [];
        foreach ($this->derivativeWidths($width) as $targetWidth) {
            $derivatives[$targetWidth] = $this->encodeWebp($this->resize($image, $targetWidth), self::WEBP_DERIVATIVE_QUALITY);
        }

        return new ProcessedImage(
            mime: self::TYPES[$type]['mime'],
            extension: self::TYPES[$type]['extension'],
            width: $width,
            height: $height,
            original: $original,
            derivatives: $derivatives,
        );
    }

    /**
     * Só larguras até a da original — ampliar gera arquivo maior e pior. Original mais estreita
     * que a menor largura da escala ganha uma derivada só, na própria largura.
     *
     * @return list<int>
     */
    private function derivativeWidths(int $width): array
    {
        $widths = array_values(array_filter(self::WIDTHS, static fn (int $w): bool => $w <= $width));

        return $widths === [] ? [$width] : $widths;
    }

    private function decode(string $path, int $type): GdImage
    {
        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            default => @imagecreatefromwebp($path),
        };

        if (! $image instanceof GdImage) {
            // Arquivo com cabeçalho válido e corpo corrompido, ou WebP animado (o GD não lê).
            $this->fail('Não foi possível ler a imagem. Ela pode estar corrompida ou ser animada.');
        }

        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        return $image;
    }

    /**
     * Os oito valores de `Orientation` do EXIF. 5 e 7 são as transposições (espelho mais giro
     * de 90°), que câmera de celular quase nunca grava mas o padrão permite.
     */
    private function applyExifOrientation(GdImage $image, string $path): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        $rotate = static function (GdImage $source, int $angle): GdImage {
            $rotated = imagerotate($source, $angle, 0);

            if (! $rotated instanceof GdImage) {
                throw new RuntimeException('Falha ao girar a imagem.');
            }

            imagealphablending($rotated, false);
            imagesavealpha($rotated, true);

            return $rotated;
        };

        switch ($orientation) {
            case 2:
                imageflip($image, IMG_FLIP_HORIZONTAL);

                return $image;
            case 3:
                return $rotate($image, 180);
            case 4:
                imageflip($image, IMG_FLIP_VERTICAL);

                return $image;
            case 5:
                $image = $rotate($image, -90);
                imageflip($image, IMG_FLIP_HORIZONTAL);

                return $image;
            case 6:
                return $rotate($image, -90);
            case 7:
                $image = $rotate($image, 90);
                imageflip($image, IMG_FLIP_HORIZONTAL);

                return $image;
            case 8:
                return $rotate($image, 90);
            default:
                return $image;
        }
    }

    private function resize(GdImage $image, int $targetWidth): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);

        if ($targetWidth === $width) {
            return $image;
        }

        $targetWidth = max(1, $targetWidth);
        $targetHeight = max(1, (int) round($height * $targetWidth / $width));
        $resized = imagecreatetruecolor($targetWidth, $targetHeight);

        if (! $resized instanceof GdImage) {
            throw new RuntimeException('Falha ao criar a derivada da imagem.');
        }

        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        return $resized;
    }

    private function encode(GdImage $image, int $type): string
    {
        return match ($type) {
            IMAGETYPE_JPEG => $this->capture(static fn () => imagejpeg($image, null, self::JPEG_QUALITY)),
            IMAGETYPE_PNG => $this->capture(static fn () => imagepng($image, null, 6)),
            default => $this->encodeWebp($image, self::WEBP_ORIGINAL_QUALITY),
        };
    }

    private function encodeWebp(GdImage $image, int $quality): string
    {
        return $this->capture(static fn () => imagewebp($image, null, $quality));
    }

    /**
     * @param  callable(): bool  $write
     */
    private function capture(callable $write): string
    {
        ob_start();
        $ok = $write();
        $bytes = (string) ob_get_clean();

        if (! $ok || $bytes === '') {
            throw new RuntimeException('Falha ao codificar a imagem.');
        }

        return $bytes;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => [$message]]);
    }
}
