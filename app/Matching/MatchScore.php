<?php

declare(strict_types=1);

namespace App\Matching;

/**
 * Résultat d'un rapprochement : le score ET la façon dont il a été obtenu.
 *
 * `breakdown` n'est pas décoratif. Sans lui, régler un seuil relève de la
 * divination et expliquer un faux positif après coup est impossible (§5 du
 * master prompt, MATCHING.md §4.4). Il est stocké tel quel dans
 * `matches.score_breakdown`.
 */
final readonly class MatchScore
{
    /** @param array<string, mixed> $breakdown */
    public function __construct(
        public float $score,
        public bool $possibleNumberTypo,
        public array $breakdown,
    ) {}
}
