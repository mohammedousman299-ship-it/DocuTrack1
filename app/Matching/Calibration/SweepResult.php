<?php

declare(strict_types=1);

namespace App\Matching\Calibration;

/**
 * Résultat du balayage à UN seuil de notification donné.
 *
 * `reviewVolume` figure au même rang que la précision : un seuil qui atteint
 * 0,99 de précision en envoyant la moitié du trafic en revue humaine n'est pas
 * un bon seuil, c'est un report de la décision sur 2-3 administrateurs (Q-27).
 */
final readonly class SweepResult
{
    public function __construct(
        public float $threshold,
        public int $truePositives,
        public int $falsePositives,
        public int $trueNegatives,
        public int $falseNegatives,
        public int $reviewVolume,
        /** Vraies correspondances non notifiées mais routées en revue. */
        public int $matchesInReview = 0,
    ) {}

    public function precision(): ?float
    {
        $predicted = $this->truePositives + $this->falsePositives;

        // Aucune notification émise : la précision n'est pas 1, elle n'est pas
        // définie. La renvoyer à 1 ferait passer pour parfait un seuil qui ne
        // rapproche jamais rien.
        return $predicted === 0 ? null : round($this->truePositives / $predicted, 4);
    }

    public function recall(): ?float
    {
        $actual = $this->truePositives + $this->falseNegatives;

        return $actual === 0 ? null : round($this->truePositives / $actual, 4);
    }

    /**
     * Couverture : part des vraies correspondances qui aboutissent à QUELQUE
     * CHOSE — notification ou revue humaine.
     *
     * Le rappel seul dramatise le choix d'un seuil élevé : une correspondance
     * non notifiée n'est pas perdue, elle passe devant un humain. C'est ce
     * chiffre, et non le rappel, qui dit ce que l'utilisateur reçoit — à
     * condition que la file de revue soit réellement traitée (Q-27), sans quoi
     * il ne mesure qu'une bonne intention.
     */
    public function coverage(): ?float
    {
        $actual = $this->truePositives + $this->falseNegatives;

        return $actual === 0
            ? null
            : round(($this->truePositives + $this->matchesInReview) / $actual, 4);
    }

    public function f1(): ?float
    {
        $p = $this->precision();
        $r = $this->recall();

        if ($p === null || $r === null || $p + $r === 0.0) {
            return null;
        }

        return round(2 * $p * $r / ($p + $r), 4);
    }
}
