<?php

declare(strict_types=1);

namespace App\Matching\Synthetic;

/**
 * Une paire étiquetée du jeu de mesure.
 *
 * `shouldMatch` est la VÉRITÉ TERRAIN : les deux côtés désignent-ils le même
 * document ? Elle est décidée par le générateur au moment de la construction,
 * jamais déduite du score — sans quoi la mesure validerait le moteur avec
 * lui-même.
 */
final readonly class LabelledPair
{
    public function __construct(
        public string $family,
        public bool $shouldMatch,
        public string $lostName,
        public ?string $lostNumber,
        public ?string $lostRegion,
        public ?string $lostOn,
        public string $foundName,
        public ?string $foundNumber,
        public ?string $foundRegion,
        public ?string $foundOn,
    ) {}
}
