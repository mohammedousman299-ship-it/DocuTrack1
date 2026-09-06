<?php

declare(strict_types=1);

namespace App\Search\Abuse;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Détection de schémas d'énumération (§4.2, THREAT_MODEL.md M-07).
 *
 * ------------------------------------------------------------------------
 * CE QUE D-007 NOUS A RETIRÉ, ET QU'IL FAUT SAVOIR.
 *
 * Le §4.2 demande de détecter « les variations systématiques de numéros » —
 * un attaquant qui essaie AB123456, AB123457, AB123458. C'est IMPOSSIBLE avec
 * notre schéma : les numéros ne sont stockés que sous forme de HMAC, dont la
 * propriété même est de détruire toute similarité entre entrées voisines.
 *
 * Nous détectons donc le VOLUME de numéros distincts, pas leur proximité. Un
 * attaquant patient qui espace ses essais reste plus difficile à repérer que
 * si les numéros étaient comparables. C'est le prix assumé de D-007, qui
 * protège en échange contre une fuite de base — un risque bien plus grave.
 *
 * Sur les NOMS, en revanche, la similarité reste mesurable : ils sont
 * conservés normalisés en clair pour le rapprochement. Le balayage de noms
 * voisins est donc détectable, et c'est le signal le plus utile dont nous
 * disposons.
 * ------------------------------------------------------------------------
 */
final class EnumerationDetector
{
    /** Fenêtre d'observation, en heures. */
    public const WINDOW_HOURS = 24;

    /** Nombre de numéros distincts au-delà duquel le compte est suspect. */
    public const DISTINCT_NUMBERS_THRESHOLD = 4;

    /** Similarité de noms au-delà de laquelle un balayage est probable. */
    public const NAME_SWEEP_SIMILARITY = 0.60;

    public function signalsFor(User $user): EnumerationSignals
    {
        $since = now()->subHours(self::WINDOW_HOURS);

        $rows = DB::table('search_requests')
            ->where('user_id', $user->id)
            ->where('created_at', '>=', $since)
            ->get(['number_hmac', 'owner_name_normalized', 'result_count_bucket']);

        $names = $rows->pluck('owner_name_normalized')->filter()->unique()->values();

        return new EnumerationSignals(
            searchCount: $rows->count(),
            distinctNumbers: $rows->pluck('number_hmac')->filter()->unique()->count(),
            distinctNames: $names->count(),
            nameSimilarityMax: $this->maxPairwiseNameSimilarity($names->all()),
            emptyResults: $rows->where('result_count_bucket', 'none')->count(),
        );
    }

    /**
     * Le compte présente-t-il un schéma d'énumération ?
     *
     * Deux signaux distincts, volontairement séparés : un attaquant qui balaye
     * des numéros et un attaquant qui balaye des noms ne se ressemblent pas.
     */
    public function isEnumerating(EnumerationSignals $signals): bool
    {
        if ($signals->distinctNumbers >= self::DISTINCT_NUMBERS_THRESHOLD) {
            return true;
        }

        // Plusieurs noms DIFFÉRENTS mais TRÈS PROCHES : c'est la signature du
        // balayage. Des noms sans rapport entre eux ressemblent davantage à
        // quelqu'un qui cherche pour plusieurs proches — cas légitime, traité
        // par la revue humaine (D-036) et non par un blocage.
        return $signals->distinctNames >= 3
            && $signals->nameSimilarityMax >= self::NAME_SWEEP_SIMILARITY;
    }

    /**
     * Similarité maximale entre deux noms distincts de l'échantillon.
     *
     * Calculée par PostgreSQL, avec la même fonction que le moteur de
     * rapprochement : deux mesures divergentes seraient impossibles à
     * expliquer.
     *
     * @param  list<string>  $names
     */
    private function maxPairwiseNameSimilarity(array $names): float
    {
        $count = count($names);

        if ($count < 2) {
            return 0.0;
        }

        $max = 0.0;

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $similarity = (float) DB::selectOne(
                    'select similarity(?, ?) as value',
                    [$names[$i], $names[$j]]
                )->value;

                $max = max($max, $similarity);
            }
        }

        return $max;
    }
}
