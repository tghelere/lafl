<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Http\UploadedFile;

/**
 * Imagens sintéticas para os testes da biblioteca — geradas na hora com GD, nenhuma foto de
 * verdade no repositório (CLAUDE.md, regra 10).
 */
final class Images
{
    /**
     * JPEG com um bloco EXIF de verdade (APP1) logo depois do SOI: `Orientation` e uma
     * coordenada de GPS. É o cenário que a regra 7 existe para barrar — foto de celular com a
     * localização de onde foi tirada.
     *
     * Metade esquerda vermelha, direita azul: é o que permite conferir, depois do giro, que o
     * pixel foi de fato girado e não só o cabeçalho reescrito.
     */
    public static function jpegWithExif(int $width, int $height, int $orientation = 1): UploadedFile
    {
        $bytes = self::jpegBytes($width, $height);

        // SOI (FFD8) + APP1 + o resto do JPEG que o GD gerou (que começa com o próprio SOI).
        $withExif = "\xFF\xD8".self::app1Exif($orientation).substr($bytes, 2);

        return self::upload($withExif, 'IMG_20260929_foto da Maria.jpg', 'image/jpeg');
    }

    public static function jpeg(int $width, int $height, string $name = 'foto.jpg'): UploadedFile
    {
        return self::upload(self::jpegBytes($width, $height), $name, 'image/jpeg');
    }

    public static function png(int $width, int $height): UploadedFile
    {
        $image = self::canvas($width, $height);
        ob_start();
        imagepng($image);

        return self::upload((string) ob_get_clean(), 'desenho.png', 'image/png');
    }

    public static function webp(int $width, int $height): UploadedFile
    {
        $image = self::canvas($width, $height);
        ob_start();
        imagewebp($image, null, 90);

        return self::upload((string) ob_get_clean(), 'logo.webp', 'image/webp');
    }

    public static function jpegBytes(int $width, int $height): string
    {
        $image = self::canvas($width, $height);
        ob_start();
        imagejpeg($image, null, 90);

        return (string) ob_get_clean();
    }

    private static function canvas(int $width, int $height): \GdImage
    {
        $image = imagecreatetruecolor($width, $height);
        assert($image instanceof \GdImage);
        $red = (int) imagecolorallocate($image, 220, 20, 20);
        $blue = (int) imagecolorallocate($image, 20, 20, 220);
        imagefilledrectangle($image, 0, 0, intdiv($width, 2) - 1, $height - 1, $red);
        imagefilledrectangle($image, intdiv($width, 2), 0, $width - 1, $height - 1, $blue);

        return $image;
    }

    private static function upload(string $bytes, string $name, string $mime): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'laf-img-');
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, $mime, null, true);
    }

    /**
     * TIFF big-endian com um IFD0 de duas entradas: Orientation (0x0112) e o ponteiro para o
     * IFD de GPS (0x8825), que tem latitude "S" (0x0001) — o bastante para `exif_read_data`
     * reconhecer e para a string "GPS" existir nos bytes.
     */
    private static function app1Exif(int $orientation): string
    {
        $tiff = 'MM'."\x00\x2A".pack('N', 8);

        // IFD0 no offset 8: 2 entradas (12 bytes cada) + ponteiro para o próximo (0).
        $gpsOffset = 8 + 2 + 2 * 12 + 4;
        $ifd0 = pack('n', 2)
            .pack('nnN', 0x0112, 3, 1).pack('nn', $orientation, 0)
            .pack('nnNN', 0x8825, 4, 1, $gpsOffset)
            .pack('N', 0);

        // IFD de GPS: 1 entrada, GPSLatitudeRef = "S" (ASCII, 2 bytes, cabe no campo).
        $gps = pack('n', 1).pack('nnN', 0x0001, 2, 2)."S\x00\x00\x00".pack('N', 0);

        $payload = "Exif\x00\x00".$tiff.$ifd0.$gps;

        return "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;
    }
}
