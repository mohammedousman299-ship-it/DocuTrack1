<?php

declare(strict_types=1);

namespace App\Matching\Calibration;

use App\Documents\DocumentNumber;
use App\Matching\MatchScore;
use App\Matching\MatchScorer;
use App\Matching\NameNormalizer;
use App\Matching\NameScore;
use App\Matching\Synthetic\LabelledPair;
use Illuminate\Support\Carbon;

/**
 * Balayage du seuil de notification sur le jeu étiqueté (MATCHING.md §6.2).
 *
 * Les paires sont scorées UNE FOIS, puis relues à chaque seuil : le score ne
 * dépend pas du seuil, et le recalculer donnerait les mêmes chiffres pour un
 * coût multiplié par la taille du balayage.
 *
 * Le jeu est chiffré exactement comme la production : les numéros passent par
 * DocumentNumber, donc par le HMAC de D-007. Comparer ici des numéros en clair
 * mesurerait un moteur qui n'existe pas.
 */
final class ThresholdSweep
{
    public function __construct(private readonly MatchScorer $scorer) {}

    /**
     * @param  list<LabelledPair>  $pairs
     * @return list<array{pair: LabelledPair, score: MatchScore}>
     */
    public function scoreAll(
        array $pairs,
        bool $withContainment = true,
        ?int $minimumNameLength = null,
    ): array {
        // Un seul aller-retour pour tous les scores de nom.
        $nameScores = NameScore::forPairs(array_map(
            static fn (LabelledPair $p): array => [
                NameNormalizer::normalize($p->lostName),
                NameNormalizer::normalize($p->foundName),
            ],
            $pairs
        ), $withContainment, $minimumNameLength);

        $scored = [];

        foreach ($pairs as $index => $pair) {
            $scored[] = [
                'pair' => $pair,
                'score' => $this->scorer->score(
                    lostNumberHmac: DocumentNumber::hmacForInput($pair->lostNumber),
                    foundNumberHmac: DocumentNumber::hmacForInput($pair->foundNumber),
                    nameScore: $nameScores[$index],
                    lostRegion: $pair->lostRegion,
                    foundRegion: $pair->foundRegion,
                    lostOn: $pair->lostOn === null ? null : Carbon::parse($pair->lostOn),
                    foundOn: $pair->foundOn === null ? null : Carbon::parse($pair->foundOn),
                ),
            ];
        }

        return $scored;
    }

    /**
     * @param  list<array{pair: LabelledPair, score: MatchScore}>  $scored
     * @return list<SweepResult>
     */
    public function sweep(array $scored, float $reviewThreshold): array
    {
        $results = [];

        for ($threshold = 0.40; $threshold <= 0.9501; $threshold += 0.05) {
            $threshold = round($threshold, 2);

            $tp = $fp = $tn = $fn = $review = $matchesInReview = 0;

            foreach ($scored as $row) {
                $notified = $row['score']->score >= $threshold;

                // Le drapeau de faute de frappe route en revue SANS notifier :
                // c'est le drapeau qui déclenche la revue, pas le score.
                $inReview = ! $notified && (
                    $row['score']->possibleNumberTypo
                    || $row['score']->score >= $reviewThreshold
                );

                if ($inReview) {
                    $review++;
                    $matchesInReview += $row['pair']->shouldMatch ? 1 : 0;
                }

                // Les branches sont évaluées dans l'ordre : arrivé à la
                // deuxième, `$notified` implique déjà que la vérité terrain
                // est fausse, et le répéter serait une condition morte.
                match (true) {
                    $notified && $row['pair']->shouldMatch => $tp++,
                    $notified => $fp++,
                    $row['pair']->shouldMatch => $fn++,
                    default => $tn++,
                };
            }

            $results[] = new SweepResult($threshold, $tp, $fp, $tn, $fn, $review, $matchesInReview);
        }

        return $results;
    }

    /**
     * Rappel par famille au seuil donné.
     *
     * L'agrégat cache l'essentiel : c'est la ventilation qui dit QUELLE
     * famille le moteur rate — notamment « faute de frappe sur le numéro »,
     * dont le rappel chiffre le coût réel de D-007.
     *
     * @param  list<array{pair: LabelledPair, score: MatchScore}>  $scored
     * @return array<string, array{total: int, notified: int, review: int, mean_score: float, should_match: bool}>
     */
    public function byFamily(array $scored, float $threshold, float $reviewThreshold): array
    {
        $families = [];

        foreach ($scored as $row) {
            $family = $row['pair']->family;
            $families[$family] ??= [
                'total' => 0, 'notified' => 0, 'review' => 0, 'sum' => 0.0,
                'should_match' => $row['pair']->shouldMatch,
            ];

            $notified = $row['score']->score >= $threshold;

            $families[$family]['total']++;
            $families[$family]['sum'] += $row['score']->score;
            $families[$family]['notified'] += $notified ? 1 : 0;
            $families[$family]['review'] += (! $notified && (
                $row['score']->possibleNumberTypo || $row['score']->score >= $reviewThreshold
            )) ? 1 : 0;
        }

        return array_map(
            static fn (array $f): array => [
                'total' => $f['total'],
                'notified' => $f['notified'],
                'review' => $f['review'],
                'mean_score' => round($f['sum'] / $f['total'], 3),
                'should_match' => $f['should_match'],
            ],
            $families
        );
    }
}
