<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Matching\Calibration\SweepResult;
use App\Matching\Calibration\ThresholdSweep;
use App\Matching\MatchScorer;
use App\Matching\Synthetic\DatasetGenerator;
use App\Models\MatchingSetting;
use Illuminate\Console\Command;

/**
 * Balayage des seuils sur le jeu synthétique (MATCHING.md §6.2).
 *
 * La commande ne DÉCIDE de rien : elle produit le tableau sur lequel la
 * décision se prend, et cette décision est consignée dans DECISIONS.md. Une
 * commande qui écrirait elle-même les seuils retenus enlèverait à la mesure
 * son seul intérêt, celui d'être discutée.
 */
final class CalibrateMatching extends Command
{
    protected $signature = 'matching:calibrate
        {--pairs=2000 : Taille du jeu synthétique}
        {--seed=20260907 : Graine, pour rejouer exactement la même mesure}
        {--at=* : Seuils pour lesquels détailler la ventilation par famille}
        {--two-terms : Ancienne formule de nom sans le terme d\'inclusion (D-040)}
        {--min-name-length= : Annule ou remplace le plancher de longueur du score de nom}
        {--json : Sortie machine}';

    protected $description = 'Balaie les seuils de rapprochement sur un jeu synthétique étiqueté';

    public function handle(): int
    {
        $settings = MatchingSetting::active();
        $reviewThreshold = (float) $settings->review_threshold;

        $pairs = (new DatasetGenerator((int) $this->option('seed')))
            ->generate((int) $this->option('pairs'));

        $sweep = new ThresholdSweep(new MatchScorer($settings));

        $startedAt = microtime(true);
        $scored = $sweep->scoreAll(
            $pairs,
            ! $this->option('two-terms'),
            $this->option('min-name-length') === null ? null : (int) $this->option('min-name-length'),
        );
        $scoringMs = (int) round((microtime(true) - $startedAt) * 1000);

        $results = $sweep->sweep($scored, $reviewThreshold);

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'pairs' => count($pairs),
                'seed' => (int) $this->option('seed'),
                'algorithm_version' => $settings->algorithm_version,
                'containment' => ! $this->option('two-terms'),
                'review_threshold' => $reviewThreshold,
                'scoring_ms' => $scoringMs,
                'sweep' => array_map(static fn (SweepResult $r): array => [
                    'threshold' => $r->threshold,
                    'precision' => $r->precision(),
                    'recall' => $r->recall(),
                    'coverage' => $r->coverage(),
                    'f1' => $r->f1(),
                    'false_positives' => $r->falsePositives,
                    'false_negatives' => $r->falseNegatives,
                    'review_volume' => $r->reviewVolume,
                ], $results),
                'by_family' => $sweep->byFamily($scored, (float) $settings->notify_threshold, $reviewThreshold),
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%d paires, graine %d, version %s, score de nom %s — scorées en %d ms',
            count($pairs), (int) $this->option('seed'), $settings->algorithm_version,
            $this->option('two-terms') ? 'à 2 termes' : 'à 3 termes', $scoringMs
        ));

        $this->newLine();
        $this->table(
            ['Seuil', 'Précision', 'Rappel', 'Couverture', 'F1', 'Faux +', 'Faux −', 'En revue'],
            array_map(static fn (SweepResult $r): array => [
                number_format($r->threshold, 2),
                $r->precision() === null ? '—' : number_format($r->precision(), 4),
                $r->recall() === null ? '—' : number_format($r->recall(), 4),
                $r->coverage() === null ? '—' : number_format($r->coverage(), 4),
                $r->f1() === null ? '—' : number_format($r->f1(), 4),
                $r->falsePositives,
                $r->falseNegatives,
                $r->reviewVolume,
            ], $results)
        );

        $thresholds = $this->option('at') !== []
            ? array_map(floatval(...), (array) $this->option('at'))
            : [(float) $settings->notify_threshold];

        foreach ($thresholds as $threshold) {
            $this->newLine();
            $this->info(sprintf('Ventilation par famille au seuil %s :', number_format($threshold, 2)));

            $families = $sweep->byFamily($scored, $threshold, $reviewThreshold);

            $this->table(
                ['Famille', 'Vérité', 'Total', 'Notifiées', 'En revue', 'Score moyen'],
                array_map(
                    static fn (string $name, array $f): array => [
                        $name,
                        // Sur une famille négative, « notifiées » compte des
                        // FAUX POSITIFS : c'est la colonne qui dit d'où ils
                        // viennent, ce que l'agrégat de précision masque.
                        $f['should_match'] ? 'match' : 'NON',
                        $f['total'], $f['notified'], $f['review'],
                        number_format($f['mean_score'], 3),
                    ],
                    array_keys($families),
                    $families
                )
            );
        }

        return self::SUCCESS;
    }
}
