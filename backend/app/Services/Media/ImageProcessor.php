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
     * Teto em pixels, conferido pelo cabeçalho ANTES de decodificar. Sem ele, um PNG pequeno
     * em bytes e enorme em pixels derrubaria o processo do PHP-FPM.
     *
     * O piso de memória é a própria decodificação: o GD guarda 4 bytes por pixel, sem
     * decodificar em escala reduzida — 20 MP custam ~91 MB só para abrir. Pico medido do
     * processamento inteiro (JPEG, GD embutido no PHP, que conta tudo no `memory_limit`):
     * 20 MP a ~108 MB deitada e ~112 MB em pé; 30 MP a ~130 MB e ~136 MB. Cabe no
     * `memory_limit` de 256M do servidor (infra/provisionar.sh) com o resto da requisição. 30 MP
     * cobrem o modo padrão das câmeras de celular.
     */
    public const MAX_PIXELS = 30_000_000;

    private const JPEG_QUALITY = 90;

    private const WEBP_ORIGINAL_QUALITY = 90;

    private const WEBP_DERIVATIVE_QUALITY = 80;

    /** Altura, em pixels, das faixas do giro de 90° (múltiplo de 16 — ver rotatedBands()). */
    private const ROTATION_BAND = 256;

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
        // A decodificada vai direto como argumento, sem variável aqui: no giro de 90° ela
        // precisa sair da memória antes de a girada nascer, e uma referência guardada neste
        // escopo a manteria viva (ver applyExifOrientation()).
        $image = $type === IMAGETYPE_JPEG
            ? $this->applyExifOrientation($this->decode($path, $type), $path)
            : $this->decode($path, $type);

        $width = imagesx($image);
        $height = imagesy($image);

        $original = $this->encode($image, $type);

        // Da maior largura para a menor, cada derivada feita a partir da anterior. Assim a
        // decodificada inteira (a maior alocação do processo) sai da memória assim que a
        // primeira derivada existe, em vez de ficar viva até a última.
        $derivatives = [];
        $current = $image;
        unset($image);
        foreach (array_reverse($this->derivativeWidths($width)) as $targetWidth) {
            $current = $this->resize($current, $targetWidth);
            $derivatives[$targetWidth] = $this->encodeWebp($current, self::WEBP_DERIVATIVE_QUALITY);
        }
        unset($current);
        ksort($derivatives);

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
     *
     * 2, 3 e 4 são espelhos (3 é o espelho nos dois eixos, igual ao giro de 180°) e acontecem
     * no próprio bitmap, sem cópia. 5 a 8 trocam largura por altura e exigem um bitmap novo —
     * ver rotatedBands().
     */
    private function applyExifOrientation(GdImage $image, string $path): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        switch ($orientation) {
            case 2:
                imageflip($image, IMG_FLIP_HORIZONTAL);

                return $image;
            case 3:
                imageflip($image, IMG_FLIP_BOTH);

                return $image;
            case 4:
                imageflip($image, IMG_FLIP_VERTICAL);

                return $image;
            case 5:
            case 6:
            case 7:
            case 8:
                $width = imagesy($image);
                $height = imagesx($image);
                $bands = $this->rotatedBands($image, clockwise: $orientation === 5 || $orientation === 6);
                // Última referência à decodificada (process() a passou sem guardar): a memória
                // dela volta aqui, antes de a girada ser criada.
                unset($image);
                $upright = $this->assembleBands($bands, $width, $height);

                if ($orientation === 5 || $orientation === 7) {
                    imageflip($upright, IMG_FLIP_HORIZONTAL);
                }

                return $upright;
            default:
                return $image;
        }
    }

    /**
     * Giro de 90° sem duas cópias inteiras da foto na memória ao mesmo tempo.
     *
     * `imagerotate` na imagem toda precisaria da original e da girada vivas juntas: 20 MP
     * custam ~91 MB cada no GD, e a foto de celular tirada em pé (EXIF 6, o caso mais comum)
     * passava de 170 MB. Aqui a original é cortada em faixas de colunas; cada faixa é girada
     * (pequena) e guardada comprimida em JPEG. Só depois de a original sair da memória o
     * bitmap girado é criado e as faixas são coladas nele, uma a uma.
     *
     * As faixas passam por uma geração extra de JPEG, em qualidade 100 — esse caminho só
     * existe para JPEG (só JPEG tem orientação aplicada), que já é recodificado com perda
     * logo em seguida. Faixas de altura múltipla de 16 caem no mesmo bloco da codificação
     * final, e a emenda não aparece.
     *
     * Duas fases porque a original só sai da memória quando a ÚLTIMA referência a ela some:
     * esta função devolve as faixas, quem chama solta a original, e só então
     * assembleBands() cria o bitmap girado.
     *
     * @return array<int, string> faixa JPEG indexada pela linha da girada onde começa
     */
    private function rotatedBands(GdImage $image, bool $clockwise): array
    {
        $sourceWidth = imagesx($image);
        $sourceHeight = imagesy($image);

        // Linha y da girada vem da coluna y da original (horário) ou da coluna
        // largura - 1 - y (anti-horário). Faixas indexadas pela posição na girada mantêm
        // todas as emendas alinhadas à grade de 16 px a partir do topo.
        $bands = [];
        for ($top = 0; $top < $sourceWidth; $top += self::ROTATION_BAND) {
            $rows = min(self::ROTATION_BAND, $sourceWidth - $top);
            $column = $clockwise ? $top : $sourceWidth - $top - $rows;

            $band = imagecrop($image, ['x' => $column, 'y' => 0, 'width' => $rows, 'height' => $sourceHeight]);
            if (! $band instanceof GdImage) {
                throw new RuntimeException('Falha ao girar a imagem.');
            }

            $rotated = imagerotate($band, $clockwise ? -90 : 90, 0);
            unset($band);
            if (! $rotated instanceof GdImage) {
                throw new RuntimeException('Falha ao girar a imagem.');
            }

            $bands[$top] = $this->capture(static fn () => imagejpeg($rotated, null, 100));
            unset($rotated);
        }

        return $bands;
    }

    /**
     * Por referência para que cada faixa saia da memória assim que é colada — por valor, o
     * array de quem chama continuaria segurando todas até o fim.
     *
     * @param  array<int, string>  $bands
     * @param  int<1, max>  $width
     * @param  int<1, max>  $height
     */
    private function assembleBands(array &$bands, int $width, int $height): GdImage
    {
        $upright = imagecreatetruecolor($width, $height);
        if (! $upright instanceof GdImage) {
            throw new RuntimeException('Falha ao girar a imagem.');
        }

        // Pelas chaves, e não `foreach ($bands as ...)`: o foreach por valor seguraria uma
        // referência ao array, e o unset não liberaria a faixa já colada.
        foreach (array_keys($bands) as $top) {
            $band = @imagecreatefromstring($bands[$top]);
            unset($bands[$top]);
            if (! $band instanceof GdImage) {
                throw new RuntimeException('Falha ao girar a imagem.');
            }

            imagecopy($upright, $band, 0, $top, 0, 0, imagesx($band), imagesy($band));
            unset($band);
        }

        imagealphablending($upright, false);
        imagesavealpha($upright, true);

        return $upright;
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
