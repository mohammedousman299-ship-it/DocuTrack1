<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Fabrique des images de test.
 *
 * Aucune image réelle de document n'entre dans le dépôt (§12) : ces fixtures
 * sont des rectangles unis, auxquels on greffe un segment EXIF construit à la
 * main pour prouver que le nettoyage serveur retire réellement les
 * coordonnées de géolocalisation.
 */
final class ImageFixture
{
    /** JPEG uni, sans aucune métadonnée. */
    public static function plainJpeg(int $width = 64, int $height = 48): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 20, 90, 160));

        ob_start();
        imagejpeg($image, null, 90);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }

    /** JPEG porteur d'un segment EXIF contenant une référence GPS. */
    public static function jpegWithGps(): string
    {
        $jpeg = self::plainJpeg();
        $app1 = "Exif\x00\x00".self::tiffWithGps();
        $segment = "\xFF\xE1".pack('n', strlen($app1) + 2).$app1;

        // Inséré juste après le marqueur de début d'image (SOI).
        return substr($jpeg, 0, 2).$segment.substr($jpeg, 2);
    }

    /** Bloc TIFF minimal : un IFD0 pointant vers un IFD GPS. */
    private static function tiffWithGps(): string
    {
        $ifd0Offset = 8;
        $gpsIfdOffset = $ifd0Offset + 2 + 12 + 4;

        $ifd0 = pack('v', 1)
            .pack('vvVV', 0x8825, 4, 1, $gpsIfdOffset) // GPSInfoIFDPointer
            .pack('V', 0);

        $gps = pack('v', 1)
            .pack('vvV', 0x0001, 2, 2)."N\x00\x00\x00"  // GPSLatitudeRef
            .pack('V', 0);

        return "II\x2a\x00".pack('V', $ifd0Offset).$ifd0.$gps;
    }
}
