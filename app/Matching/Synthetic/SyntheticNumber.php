<?php

declare(strict_types=1);

namespace App\Matching\Synthetic;

use Random\Randomizer;

/**
 * Numéros de document inventés.
 *
 * Les formats ci-dessous sont PLAUSIBLES sans prétendre reproduire un format
 * officiel réel : je ne dispose d'aucune source vérifiable sur la structure
 * des numéros de documents camerounais, et en inventer une en la présentant
 * comme réelle serait pire que de ne rien dire. Ils servent à mesurer le
 * comportement du moteur, pas à valider un format.
 */
final class SyntheticNumber
{
    /** @var list<string> Motifs : L = lettre, 9 = chiffre. */
    private const PATTERNS = ['LL999999', 'L9999999', '999999999', 'LLL99999'];

    private const LETTERS = 'ABCDEFGHJKLMNPQRSTUVWXYZ';

    public function __construct(private readonly Randomizer $randomizer) {}

    public function generate(): string
    {
        $pattern = self::PATTERNS[$this->randomizer->getInt(0, count(self::PATTERNS) - 1)];
        $number = '';

        foreach (mb_str_split($pattern) as $symbol) {
            $number .= $symbol === 'L'
                ? self::LETTERS[$this->randomizer->getInt(0, mb_strlen(self::LETTERS) - 1)]
                : (string) $this->randomizer->getInt(0, 9);
        }

        return $number;
    }

    /**
     * Numéro VOISIN : un seul chiffre modifié.
     *
     * Cette famille existe pour vérifier une propriété que le moteur doit
     * avoir : deux numéros voisins ne doivent PAS se rapprocher. C'est la
     * conséquence assumée de D-007, dont le HMAC détruit toute similarité —
     * la mesure doit le confirmer, pas le supposer.
     */
    public function neighbourOf(string $number): string
    {
        $characters = mb_str_split($number);
        $digits = array_keys(array_filter(
            $characters,
            static fn (string $c): bool => ctype_digit($c)
        ));

        if ($digits === []) {
            return $number;
        }

        $at = $digits[$this->randomizer->getInt(0, count($digits) - 1)];
        $current = (int) $characters[$at];

        // Décalage de ±1, borné : un voisin, pas un numéro quelconque.
        $characters[$at] = (string) match (true) {
            $current === 0 => 1,
            $current === 9 => 8,
            default => $current + ($this->randomizer->getInt(0, 1) === 0 ? -1 : 1),
        };

        return implode('', $characters);
    }
}
