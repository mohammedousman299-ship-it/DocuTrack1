<?php

declare(strict_types=1);

namespace App\Matching;

use Illuminate\Support\Facades\DB;

/**
 * Score de nom composite (MATCHING.md §3.1.1).
 *
 *   P = max( similarity(a, b), 1 − levenshtein(a, b) / longueur_max )
 *
 * Le trigramme seul ne suffit pas : les mesures du jalon 1 lui donnent 0,667
 * sur une faute d'un seul caractère — l'erreur la plus fréquente — soit sous
 * le seuil de notification. La distance d'édition la capte exactement. Prendre
 * le maximum garde les qualités des deux : le trigramme reste insensible à
 * l'ordre des mots et tolère un token manquant, la distance rattrape les
 * fautes courtes.
 *
 * ------------------------------------------------------------------------
 * Le calcul est fait EN BASE, jamais réimplémenté en PHP.
 *
 * Le jalon 1 a produit une divergence exactement de ce type : `upper()` est
 * dépendant de la locale, et « ßeta9 » donnait ETA9 en SQL contre SSETA9 en
 * PHP. Une seconde implémentation d'une même règle est une seconde règle.
 * ------------------------------------------------------------------------
 *
 * Les noms attendus sont DÉJÀ NORMALISÉS (NameNormalizer::normalize ou
 * docutrack_normalize_name) : c'est la forme stockée en base et celle sur
 * laquelle l'index GIN travaille.
 */
final class NameScore
{
    /**
     * Longueur en deçà de laquelle la distance d'édition n'est pas retenue.
     *
     * Sur des noms très courts, la distance normalisée est généreuse : deux
     * noms de 4 caractères différant d'une lettre obtiennent 0,75, alors
     * qu'ils désignent probablement deux personnes distinctes. Le risque est
     * signalé dans MATCHING.md §3.1.1.
     *
     * VALEUR PROVISOIRE, non mesurée à ce stade. Elle est posée ici pour que
     * le balayage de seuils puisse l'éprouver, et doit être confirmée ou
     * corrigée par la mesure avant d'être considérée comme un choix. Le
     * balayage compare explicitement les variantes avec et sans plancher.
     */
    public const MINIMUM_LENGTH_FOR_EDIT_DISTANCE = 8;

    /** Score d'une paire. Pour un lot, préférer forPairs : une requête suffit. */
    public static function between(?string $a, ?string $b): ?float
    {
        if ($a === null || $b === null || $a === '' || $b === '') {
            return null; // Champ non comparable : neutre, jamais pénalisant.
        }

        return self::forPairs([[$a, $b]])[0];
    }

    /**
     * Nombre de tokens exactement communs à partir duquel l'inclusion compte.
     *
     * Un seul token partagé est le cas banal du patronyme commun : deux
     * personnes différentes. Deux tokens identiques sont une coïncidence bien
     * plus improbable. Le seuil rejoint celui de NameConsistencyCheck, qui
     * tranche la même question sur le nom du compte.
     */
    public const MINIMUM_SHARED_TOKENS_FOR_CONTAINMENT = 2;

    /**
     * Scores d'un lot, en UNE requête.
     *
     * 2 000 paires en 2 000 allers-retours rendraient le balayage de seuils
     * insupportablement lent, et l'outil de mesure finirait par ne pas être
     * relancé — c'est ainsi qu'on cesse de mesurer.
     *
     * La formule de production est celle à TROIS termes (D-040). Passer
     * `$withContainment` à false retrouve l'ancienne formule à deux termes, et
     * `$minimumLength` annule le plancher de longueur : ces deux leviers
     * existent pour que le balayage puisse COMPARER les variantes sur le même
     * jeu, plutôt que d'en remplacer une par une autre sur une intuition.
     *
     * @param  list<array{0: string, 1: string}>  $pairs
     * @return list<float>
     */
    public static function forPairs(
        array $pairs,
        bool $withContainment = true,
        ?int $minimumLength = null,
    ): array {
        if ($pairs === []) {
            return [];
        }

        $left = array_map(static fn (array $p): string => $p[0], $pairs);
        $right = array_map(static fn (array $p): string => $p[1], $pairs);

        // Troisième terme : INCLUSION DE TOKENS. Il ne vaut que si les tokens
        // du nom le plus court se retrouvent tous exactement dans le plus
        // long, et qu'ils sont au moins deux. Il vise le cas où un côté omet
        // un prénom — que les deux autres termes punissent lourdement, la
        // disparition d'un token entier coûtant plus qu'une faute de frappe.
        $containment = $withContainment
            ? <<<'SQL'
                , CASE
                    WHEN least(
                            array_length(string_to_array(a, ' '), 1),
                            array_length(string_to_array(b, ' '), 1)
                         ) >= ?
                     AND (
                            string_to_array(a, ' ') @> string_to_array(b, ' ')
                         OR string_to_array(b, ' ') @> string_to_array(a, ' ')
                         )
                        THEN 1
                    ELSE 0
                  END
                SQL
            : '';

        $bindings = [
            $minimumLength ?? self::MINIMUM_LENGTH_FOR_EDIT_DISTANCE,
            ...($withContainment ? [self::MINIMUM_SHARED_TOKENS_FOR_CONTAINMENT] : []),
            '{'.implode(',', array_map(self::quote(...), $left)).'}',
            '{'.implode(',', array_map(self::quote(...), $right)).'}',
        ];

        $rows = DB::select(
            <<<SQL
            SELECT greatest(
                similarity(a, b),
                CASE
                    -- Sous la longueur minimale, la distance d'édition est
                    -- trop généreuse : on s'en remet au seul trigramme.
                    WHEN greatest(length(a), length(b)) < ?
                        THEN 0
                    ELSE 1 - levenshtein(a, b)::numeric / greatest(length(a), length(b))
                END
                {$containment}
            )::float8 AS score
            FROM unnest(?::text[], ?::text[]) AS t(a, b)
            SQL,
            $bindings
        );

        return array_map(
            static fn (object $row): float => round((float) $row->score, 4),
            $rows
        );
    }

    /** Échappement pour un littéral de tableau PostgreSQL. */
    private static function quote(string $value): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }
}
