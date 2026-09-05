<?php

declare(strict_types=1);

namespace App\Internal;

/**
 * Budget de temps d'un lot.
 *
 * Les boucles de traitement l'interrogent entre deux unités de travail et
 * s'arrêtent proprement quand il est épuisé, laissant le passage suivant
 * reprendre là où celui-ci s'est arrêté.
 */
final class TaskBudget
{
    private readonly float $startedAt;

    public function __construct(private readonly int $seconds)
    {
        $this->startedAt = microtime(true);
    }

    public function isExhausted(): bool
    {
        return $this->elapsed() >= $this->seconds;
    }

    public function hasTimeLeft(): bool
    {
        return ! $this->isExhausted();
    }

    public function elapsed(): float
    {
        return microtime(true) - $this->startedAt;
    }
}
