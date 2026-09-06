<?php

declare(strict_types=1);

namespace App\Documents;

use RuntimeException;

/**
 * Suppression des métadonnées d'image, côté serveur (M-12, D-029).
 *
 * Une photographie de pièce d'identité prise au téléphone embarque des
 * métadonnées EXIF, dont les coordonnées GPS du lieu de prise de vue. Publiées
 * ou fuitées, elles indiquent où se trouvait le document — donc, très souvent,
 * où habite ou travaille son propriétaire.
 *
 * La suppression procède par DÉCODAGE puis RÉENCODAGE : l'image est reconstruite
 * à partir de ses seuls pixels, ce qui écarte par construction tout segment de
 * métadonnées, connu ou non. Une approche par liste de segments à retirer
 * laisserait passer ceux qu'on n'a pas prévus.
 *
 * Coût assumé : le réencodage recompresse l'image. La qualité retenue est un
 * compromis entre lisibilité pour l'administrateur et poids stocké.
 */
final class ExifStripper
{
    public const QUALITY = 85;

    /** @var list<string> */
    public const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    /** Renvoie les octets d'une image dépourvue de toute métadonnée. */
    public static function strip(string $bytes): string
    {
        $image = @imagecreatefromstring($bytes);

        if ($image === false) {
            throw new RuntimeException(
                "Le contenu n'est pas une image décodable. Un fichier non "
                .'décodable ne doit jamais être servi ni conservé.'
            );
        }

        // Préserver la transparence éventuelle avant réencodage.
        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();
        imagejpeg($image, null, self::QUALITY);
        $clean = (string) ob_get_clean();

        imagedestroy($image);

        return self::removeCommentSegments($clean);
    }

    /**
     * Retire les segments de commentaire (COM, 0xFFFE) d'un flux JPEG.
     *
     * GD signe ses propres images — « CREATOR: gd-jpeg … quality = N ». Ce
     * commentaire n'est pas une donnée personnelle, mais c'est bien une
     * métadonnée, et il révèle la chaîne de traitement du serveur. Le retirer
     * permet en outre à la vérification post-nettoyage de constater
     * l'ABSENCE TOTALE de métadonnées, plutôt que d'entretenir une liste
     * d'exceptions tolérées — liste qui finirait par laisser passer autre
     * chose.
     */
    private static function removeCommentSegments(string $jpeg): string
    {
        $output = substr($jpeg, 0, 2); // marqueur SOI
        $offset = 2;
        $length = strlen($jpeg);

        while ($offset < $length - 1) {
            if (ord($jpeg[$offset]) !== 0xFF) {
                break;
            }

            $marker = ord($jpeg[$offset + 1]);

            // Début des données compressées : tout le reste est copié tel quel.
            if ($marker === 0xDA) {
                return $output.substr($jpeg, $offset);
            }

            $segmentLength = (int) (unpack('n', substr($jpeg, $offset + 2, 2))[1] ?? 0);

            if ($segmentLength < 2) {
                break;
            }

            $segment = substr($jpeg, $offset, 2 + $segmentLength);

            if ($marker !== 0xFE) {
                $output .= $segment;
            }

            $offset += 2 + $segmentLength;
        }

        // Structure inattendue : on rend le flux d'origine plutôt qu'un JPEG
        // tronqué. La vérification qui suit refusera de le marquer servable.
        return $offset >= $length - 1 ? $output : $jpeg;
    }

    /**
     * Indique si des métadonnées subsistent.
     *
     * Sert de vérification APRÈS nettoyage : c'est ce contrôle, et non la
     * confiance au navigateur, qui autorise à marquer une pièce jointe comme
     * servable.
     */
    public static function hasMetadata(string $bytes): bool
    {
        $temp = tempnam(sys_get_temp_dir(), 'exif');

        if ($temp === false) {
            // Dans le doute, on considère qu'il en reste : refuser de servir
            // est toujours préférable à servir une image non vérifiée.
            return true;
        }

        try {
            file_put_contents($temp, $bytes);
            $data = @exif_read_data($temp);

            if ($data === false) {
                return false;
            }

            // Certaines clés sont produites par la lecture elle-même et ne
            // proviennent pas du fichier ; elles ne comptent pas.
            $inherent = ['FileName', 'FileDateTime', 'FileSize', 'FileType',
                'MimeType', 'SectionsFound', 'COMPUTED'];

            return array_diff(array_keys($data), $inherent) !== [];
        } finally {
            @unlink($temp);
        }
    }

    /** Détecte spécifiquement des coordonnées de géolocalisation. */
    public static function hasLocation(string $bytes): bool
    {
        $temp = tempnam(sys_get_temp_dir(), 'exif');

        if ($temp === false) {
            return true;
        }

        try {
            file_put_contents($temp, $bytes);
            $data = @exif_read_data($temp);

            if ($data === false) {
                return false;
            }

            foreach (array_keys($data) as $key) {
                if (str_starts_with((string) $key, 'GPS')) {
                    return true;
                }
            }

            return false;
        } finally {
            @unlink($temp);
        }
    }
}
