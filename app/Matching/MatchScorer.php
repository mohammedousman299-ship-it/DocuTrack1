<?php

declare(strict_types=1);

namespace App\Matching;

use App\Models\MatchingSetting;
use Illuminate\Support\Carbon;

/**
 * Score de rapprochement (MATCHING.md §3.2).
 *
 *   Comparables = composantes renseignées DES DEUX CÔTÉS
 *   Base  = Σ (poids × valeur) / Σ (poids)     sur les seules comparables
 *   Score = Base × T
 *
 * ------------------------------------------------------------------------
 * La renormalisation sur les seuls champs comparables est indispensable.
 *
 * Sans elle, un couple sans numéro des deux côtés plafonnerait à 0,40 et ne
 * serait jamais notifié — alors que l'absence de numéro est le cas courant et
 * légitime : le Trouveur ne l'a pas relevé, le Propriétaire ne le connaît pas.
 * Un champ absent est NEUTRE, jamais pénalisant.
 * ------------------------------------------------------------------------
 *
 * Le score de nom est fourni par l'appelant (NameScore), qui le calcule en
 * base et par lots : le calculer ici, paire par paire, rendrait le balayage de
 * seuils trop lent pour être relancé.
 */
final class MatchScorer
{
    public function __construct(private readonly MatchingSetting $settings) {}

    public function score(
        ?string $lostNumberHmac,
        ?string $foundNumberHmac,
        ?float $nameScore,
        ?string $lostRegion,
        ?string $foundRegion,
        ?Carbon $lostOn,
        ?Carbon $foundOn,
    ): MatchScore {
        $components = [];

        $numbersComparable = $lostNumberHmac !== null && $foundNumberHmac !== null;

        if ($numbersComparable) {
            $components['number'] = [
                'weight' => (float) $this->settings->weight_number,
                'value' => $lostNumberHmac === $foundNumberHmac ? 1.0 : 0.0,
            ];
        }

        if ($nameScore !== null) {
            $components['name'] = [
                'weight' => (float) $this->settings->weight_name,
                'value' => $nameScore,
            ];
        }

        if ($lostRegion !== null && $foundRegion !== null) {
            $components['geography'] = [
                'weight' => (float) $this->settings->weight_geography,
                'value' => $lostRegion === $foundRegion ? 1.0 : 0.0,
            ];
        }

        $weightSum = array_sum(array_column($components, 'weight'));

        $base = $weightSum <= 0.0
            // Aucune composante comparable : rien ne permet de conclure. Zéro,
            // et non une valeur moyenne qui laisserait croire à un indice.
            ? 0.0
            : array_sum(array_map(
                static fn (array $c): float => $c['weight'] * $c['value'],
                $components
            )) / $weightSum;

        $temporal = TemporalFactor::for($lostOn, $foundOn);
        $score = round($base * $temporal, 3);

        return new MatchScore(
            score: $score,
            possibleNumberTypo: $this->isPossibleNumberTypo(
                $numbersComparable, $lostNumberHmac, $foundNumberHmac, $nameScore, $temporal
            ),
            breakdown: [
                'components' => $components,
                'weight_sum' => round($weightSum, 3),
                'base' => round($base, 4),
                'temporal_factor' => $temporal,
                'algorithm_version' => $this->settings->algorithm_version,
            ],
        );
    }

    /**
     * Faute de frappe probable sur le numéro (MATCHING.md §3.5).
     *
     * D-007 a supprimé toute comparaison approchée des numéros : le HMAC
     * détruit la similarité entre entrées voisines. Deux numéros qui diffèrent
     * d'un seul caractère sont donc traités comme deux documents sans rapport,
     * et le score plafonne mécaniquement à 0,40 — sous le seuil de revue.
     *
     * Une simple faute de frappe suffit ainsi à faire DISPARAÎTRE une
     * correspondance parfaitement valide. Ce drapeau la rattrape : c'est lui
     * qui route le couple en revue, pas le score, qui reste bas.
     */
    private function isPossibleNumberTypo(
        bool $numbersComparable,
        ?string $lostHmac,
        ?string $foundHmac,
        ?float $nameScore,
        float $temporal,
    ): bool {
        return $numbersComparable
            && $lostHmac !== $foundHmac
            && $nameScore !== null
            && $nameScore >= (float) $this->settings->number_typo_name_threshold
            && $temporal > 0.0;
    }
}
