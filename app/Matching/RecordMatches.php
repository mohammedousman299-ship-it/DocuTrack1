<?php

declare(strict_types=1);

namespace App\Matching;

use App\Models\DocumentMatch;
use App\Models\FoundReport;
use App\Models\LostDeclaration;
use App\Models\MatchingSetting;
use Illuminate\Support\Collection;

/**
 * Score une déclaration contre ses candidats et enregistre les correspondances.
 *
 * Trois destins possibles, et un seul est une notification :
 *
 *   score ≥ notify_threshold                      → `candidate`, à notifier
 *   score ≥ review_threshold OU drapeau de frappe → `under_review`
 *   sinon                                          → RIEN N'EST ÉCRIT
 *
 * Le troisième cas est délibéré. Écrire les couples sous le seuil de revue
 * remplirait la table de bruit et, surtout, constituerait une trace exploitable
 * de « qui a failli correspondre à quoi » — une donnée que personne n'a demandé
 * à produire et que rien ne consomme.
 */
final class RecordMatches
{
    /*
     | Le scoreur est construit ICI, à partir des réglages passés à handle(),
     | et n'est PAS injecté par le conteneur.
     |
     | MatchScorer dépend d'un MatchingSetting. Résolu par le conteneur, celui-ci
     | est un modèle Eloquent VIDE : tous les poids valent null, donc 0 une fois
     | convertis, la somme des poids vaut 0, et tout score vaut 0. Le moteur
     | n'enregistrait plus aucune correspondance — sans lever la moindre erreur.
     |
     | Construire le scoreur à partir des réglages effectivement chargés
     | supprime la possibilité même de cette panne : il n'existe plus de chemin
     | par lequel il puisse recevoir des réglages que personne n'a lus.
     */

    /**
     * @param  Collection<int, FoundReport>  $candidates
     * @return Collection<int, DocumentMatch>
     */
    public function handle(
        LostDeclaration $declaration,
        Collection $candidates,
        MatchingSetting $settings,
    ): Collection {
        if ($candidates->isEmpty()) {
            return collect();
        }

        // Un seul aller-retour pour tous les scores de nom, quel que soit le
        // nombre de candidats.
        $nameScores = NameScore::forPairs(
            $candidates->map(fn (FoundReport $r): array => [
                $declaration->owner_name_normalized,
                $r->owner_name_normalized ?? '',
            ])->all()
        );

        $scorer = new MatchScorer($settings);
        $notify = (float) $settings->notify_threshold;
        $review = (float) $settings->review_threshold;

        $recorded = collect();

        foreach ($candidates as $index => $candidate) {
            $score = $scorer->score(
                lostNumberHmac: $declaration->number_hmac,
                foundNumberHmac: $candidate->number_hmac,
                nameScore: $candidate->owner_name_normalized === null
                    || $candidate->owner_name_normalized === ''
                        ? null
                        : $nameScores[$index],
                lostRegion: $declaration->lost_region,
                foundRegion: $candidate->found_region,
                lostOn: $declaration->lost_on,
                foundOn: $candidate->found_on,
            );

            $status = match (true) {
                $score->score >= $notify => 'candidate',
                $score->score >= $review, $score->possibleNumberTypo => 'under_review',
                default => null,
            };

            if ($status === null) {
                continue;
            }

            // Idempotence : la clé (déclaration, signalement, version) est
            // unique en base. Rejouer le cron ne crée pas de doublon et,
            // surtout, ne renotifie pas.
            $match = DocumentMatch::query()->firstOrCreate(
                [
                    'lost_declaration_id' => $declaration->id,
                    'found_report_id' => $candidate->id,
                    'algorithm_version' => $settings->algorithm_version,
                ],
                [
                    'score' => $score->score,
                    'score_breakdown' => $score->breakdown,
                    'possible_number_typo' => $score->possibleNumberTypo,
                    'status' => $status,
                ]
            );

            $recorded->push($match);
        }

        return $recorded;
    }
}
