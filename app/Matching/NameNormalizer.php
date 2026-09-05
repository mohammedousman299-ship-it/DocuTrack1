<?php

declare(strict_types=1);

namespace App\Matching;

use Normalizer;

/**
 * Miroir PHP de la fonction SQL docutrack_normalize_name().
 *
 * Les deux implémentations DOIVENT produire des résultats identiques : une
 * divergence rendrait les correspondances irreproductibles selon le chemin
 * emprunté. Un test d'équivalence les compare sur un corpus partagé.
 *
 * Voir docs/MATCHING.md §2.1.
 */
final class NameNormalizer
{
    /**
     * Version de la normalisation. Toute modification des règles ci-dessous
     * doit l'incrémenter ET incrémenter algorithm_version : les valeurs déjà
     * stockées ont été calculées avec les anciennes règles.
     */
    public const VERSION = 'name-v1';

    /**
     * Caractères que PostgreSQL unaccent replie mais que la décomposition
     * Unicode NFD ne traite pas — ils n'ont pas de forme décomposée.
     *
     * Cette table n'est pas devinée : elle est DÉRIVÉE de PostgreSQL, en
     * comparant unaccent() à NFD sur la plage U+00C0–U+024F, et ne retient
     * que les écarts. La régénérer si la version de PostgreSQL change.
     *
     * @var array<string, string>
     */
    private const UNACCENT_EXCEPTIONS = [
        'Æ' => 'AE',
        'Ð' => 'D',
        '×' => '*',
        'Ø' => 'O',
        'Þ' => 'TH',
        'ß' => 'ss',
        'æ' => 'ae',
        'ð' => 'd',
        '÷' => '/',
        'ø' => 'o',
        'þ' => 'th',
        'Đ' => 'D',
        'đ' => 'd',
        'Ħ' => 'H',
        'ħ' => 'h',
        'ı' => 'i',
        'Ĳ' => 'IJ',
        'ĳ' => 'ij',
        'ĸ' => 'q',
        'Ŀ' => 'L',
        'ŀ' => 'l',
        'Ł' => 'L',
        'ł' => 'l',
        'ŉ' => '\'n',
        'Ŋ' => 'N',
        'ŋ' => 'n',
        'Œ' => 'OE',
        'œ' => 'oe',
        'Ŧ' => 'T',
        'ŧ' => 't',
        'ſ' => 's',
        'ƀ' => 'b',
        'Ɓ' => 'B',
        'Ƃ' => 'B',
        'ƃ' => 'b',
        'Ƈ' => 'C',
        'ƈ' => 'c',
        'Ɖ' => 'D',
        'Ɗ' => 'D',
        'Ƌ' => 'D',
        'ƌ' => 'd',
        'Ɛ' => 'E',
        'Ƒ' => 'F',
        'ƒ' => 'f',
        'Ɠ' => 'G',
        'ƕ' => 'hv',
        'Ɩ' => 'I',
        'Ɨ' => 'I',
        'Ƙ' => 'K',
        'ƙ' => 'k',
        'ƚ' => 'l',
        'Ɲ' => 'N',
        'ƞ' => 'n',
        'Ƣ' => 'OI',
        'ƣ' => 'oi',
        'Ƥ' => 'P',
        'ƥ' => 'p',
        'ƫ' => 't',
        'Ƭ' => 'T',
        'ƭ' => 't',
        'Ʈ' => 'T',
        'Ʋ' => 'V',
        'Ƴ' => 'Y',
        'ƴ' => 'y',
        'Ƶ' => 'Z',
        'ƶ' => 'z',
        'Ǆ' => 'DZ',
        'ǅ' => 'Dz',
        'ǆ' => 'dz',
        'Ǉ' => 'LJ',
        'ǈ' => 'Lj',
        'ǉ' => 'lj',
        'Ǌ' => 'NJ',
        'ǋ' => 'Nj',
        'ǌ' => 'nj',
        'Ǥ' => 'G',
        'ǥ' => 'g',
        'Ǳ' => 'DZ',
        'ǲ' => 'Dz',
        'ǳ' => 'dz',
        'ȡ' => 'd',
        'Ȥ' => 'Z',
        'ȥ' => 'z',
        'ȴ' => 'l',
        'ȵ' => 'n',
        'ȶ' => 't',
        'ȷ' => 'j',
        'ȸ' => 'db',
        'ȹ' => 'qp',
        'Ⱥ' => 'A',
        'Ȼ' => 'C',
        'ȼ' => 'c',
        'Ƚ' => 'L',
        'Ⱦ' => 'T',
        'ȿ' => 's',
        'ɀ' => 'z',
        'Ƀ' => 'B',
        'Ʉ' => 'U',
        'Ɇ' => 'E',
        'ɇ' => 'e',
        'Ɉ' => 'J',
        'ɉ' => 'j',
        'Ɍ' => 'R',
        'ɍ' => 'r',
        'Ɏ' => 'Y',
        'ɏ' => 'y',
    ];

    /** Apostrophes et marques internes : supprimées, jamais séparatrices. */
    private const INTERNAL_MARKS = ["'", "\u{2019}", "\u{02BC}", '`'];

    /**
     * Replie les accents, supprime la ponctuation, découpe en tokens et les
     * trie par ordre alphabétique.
     *
     * Le tri ne sert pas à neutraliser l'inversion nom/prénom pour la
     * similarité — pg_trgm compare des ensembles de trigrammes et ignore déjà
     * l'ordre des mots. Il sert aux comparaisons par ÉGALITÉ : empreinte de
     * doublon et contrôle de cohérence du nom du compte.
     */
    public static function normalize(?string $input): string
    {
        if ($input === null || trim($input) === '') {
            return '';
        }

        $value = self::unaccent($input);
        $value = mb_strtolower($value, 'UTF-8');
        $value = str_replace(self::INTERNAL_MARKS, '', $value);

        // Tout le reste sépare, tirets compris : un nom composé doit se
        // rapprocher de sa graphie sans tiret.
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';

        $tokens = array_values(array_filter(explode(' ', trim($value)), static fn (string $t): bool => $t !== ''));

        sort($tokens, SORT_STRING);

        return implode(' ', $tokens);
    }

    /** Reproduit le comportement de PostgreSQL unaccent(). */
    public static function unaccent(string $input): string
    {
        $input = strtr($input, self::UNACCENT_EXCEPTIONS);

        $decomposed = Normalizer::normalize($input, Normalizer::FORM_D);

        if ($decomposed === false) {
            return $input;
        }

        return preg_replace('/\p{Mn}+/u', '', $decomposed) ?? $input;
    }
}
