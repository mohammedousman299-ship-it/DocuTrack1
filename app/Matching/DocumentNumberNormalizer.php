<?php

declare(strict_types=1);

namespace App\Matching;

/**
 * Miroir PHP de la fonction SQL docutrack_normalize_number().
 *
 * Le numéro normalisé n'est JAMAIS stocké en clair : il alimente le calcul du
 * HMAC puis est écarté (D-007). Voir docs/MATCHING.md §2.2.
 */
final class DocumentNumberNormalizer
{
    public const VERSION = 'number-v1';

    /**
     * Homoglyphes repliés — volontairement limités à O→0 et I→1.
     *
     * S↔5 et B↔8 sont écartés : ils créent des collisions entre numéros
     * réellement distincts. Ces confusions-là relèvent de la distance
     * d'édition, pas de la normalisation.
     */
    private const HOMOGLYPHS = ['O' => '0', 'I' => '1'];

    public static function normalize(?string $input): string
    {
        if ($input === null || trim($input) === '') {
            return '';
        }

        // Le filtrage précède la mise en majuscules, et c'est délibéré :
        // la casse de certains caractères dépend de la locale (« ß » devient
        // « SS » ou reste « ß » selon l'implémentation). En ne gardant que de
        // l'ASCII avant, le résultat devient indépendant de la locale — sans
        // quoi SQL et PHP divergent, ce qu'a révélé le test d'équivalence.
        $value = preg_replace('/[^A-Za-z0-9]/', '', $input) ?? '';
        $value = strtoupper($value);

        return strtr($value, self::HOMOGLYPHS);
    }
}
