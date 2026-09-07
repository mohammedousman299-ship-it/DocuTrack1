<?php

declare(strict_types=1);

namespace App\Matching;

use Illuminate\Support\Carbon;

/**
 * Facteur temporel `T` (MATCHING.md §3.3).
 *
 * MULTIPLICATIF, et non additif : une découverte antérieure à la perte
 * déclarée est physiquement impossible et doit ANNULER la correspondance, pas
 * la décoter. Un facteur additif laisserait un couple impossible franchir le
 * seuil grâce à un excellent score de nom.
 */
final class TemporalFactor
{
    /**
     * La date de perte est souvent approximative : on constate la disparition
     * d'un document plusieurs jours après l'avoir perdu.
     */
    public const TOLERANCE_DAYS = 7;

    public static function for(?Carbon $lostOn, ?Carbon $foundOn): float
    {
        // Une date absente est NEUTRE, jamais pénalisante : le Trouveur peut
        // ignorer quand le document a été perdu, le Propriétaire quand il a
        // été trouvé.
        if ($lostOn === null || $foundOn === null) {
            return 1.0;
        }

        $days = $lostOn->diffInDays($foundOn, absolute: false);

        return match (true) {
            $days < -self::TOLERANCE_DAYS => 0.0,   // trouvé avant d'être perdu
            $days <= 180 => 1.0,
            $days <= 365 => 0.9,
            default => 0.8,
        };
    }
}
