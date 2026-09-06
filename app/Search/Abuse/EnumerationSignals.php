<?php

declare(strict_types=1);

namespace App\Search\Abuse;

/** Signaux relevés sur l'activité récente d'un compte. */
final readonly class EnumerationSignals
{
    public function __construct(
        public int $searchCount,
        public int $distinctNumbers,
        public int $distinctNames,
        public float $nameSimilarityMax,
        public int $emptyResults,
    ) {}

    /** @return array<string, int|float> */
    public function toArray(): array
    {
        return [
            'recherches' => $this->searchCount,
            'numeros_distincts' => $this->distinctNumbers,
            'noms_distincts' => $this->distinctNames,
            'similarite_max_noms' => round($this->nameSimilarityMax, 3),
            'resultats_vides' => $this->emptyResults,
        ];
    }
}
