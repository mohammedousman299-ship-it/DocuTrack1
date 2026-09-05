<?php

declare(strict_types=1);

namespace App\Auth;

/**
 * Normalisation des numéros de téléphone au format E.164.
 *
 * Le numéro vérifié est le contrôle anti-Sybil principal (D-013) et l'unicité
 * en base n'a de sens que si deux graphies du même numéro produisent la même
 * chaîne : « +237 6 12 34 56 78 » et « +237612345678 » doivent se heurter.
 *
 * INCERTITUDE ASSUMÉE : les préfixes et longueurs exacts des numéros mobiles
 * camerounais ne sont pas connus avec certitude et ne seront pas inventés
 * (même règle que pour les numéros de documents, D-012). La validation reste
 * donc structurelle — indicatif pays et longueur plausible — et non
 * spécifique à un opérateur. À resserrer si une source fiable est fournie.
 */
final class PhoneNumber
{
    /** Indicatif appliqué à un numéro saisi sans indicatif international. */
    public const DEFAULT_COUNTRY_CODE = '+237';

    public static function normalize(?string $input): ?string
    {
        if ($input === null || trim($input) === '') {
            return null;
        }

        $hasPlus = str_starts_with(trim($input), '+');
        $digits = preg_replace('/\D/', '', $input) ?? '';

        if ($digits === '') {
            return null;
        }

        if ($hasPlus) {
            return '+'.$digits;
        }

        // Préfixe international composé (00), équivalent au « + ».
        if (str_starts_with($digits, '00')) {
            return '+'.substr($digits, 2);
        }

        $countryDigits = ltrim(self::DEFAULT_COUNTRY_CODE, '+');

        // Numéro saisi avec l'indicatif mais SANS le « + ». Sans ce cas, on
        // préfixerait une seconde fois : « 237612345678 » deviendrait
        // « +237237612345678 », et le même abonné produirait deux chaînes
        // distinctes — ce qui viderait de son sens l'unicité du numéro, donc
        // le contrôle anti-Sybil lui-même (D-013).
        //
        // Le seuil de longueur distingue ce cas d'un numéro national qui
        // commencerait par les mêmes chiffres. Il est prudent, faute de
        // connaître avec certitude le plan de numérotation camerounais.
        if (str_starts_with($digits, $countryDigits) && strlen($digits) > 9) {
            return '+'.$digits;
        }

        // Numéro national : le 0 initial est retiré avant l'ajout de
        // l'indicatif, sinon deux graphies du même abonné divergeraient.
        $digits = ltrim($digits, '0');

        return self::DEFAULT_COUNTRY_CODE.$digits;
    }

    /** Validation structurelle : E.164 accepte 8 à 15 chiffres après le « + ». */
    public static function isPlausible(?string $normalized): bool
    {
        return $normalized !== null && preg_match('/^\+[1-9]\d{7,14}$/', $normalized) === 1;
    }
}
