<?php

declare(strict_types=1);

namespace App\Documents;

use App\Matching\DocumentNumberNormalizer;
use App\Matching\NameNormalizer;

/**
 * Empreinte de doublon d'un signalement (§1.5, §4.5).
 *
 * Le contrôle porte sur une empreinte NORMALISÉE, pas sur une égalité stricte :
 * deux personnes signalant le même document ne l'écriront pas de la même
 * façon.
 *
 * Une collision ne rejette JAMAIS automatiquement le signalement : elle le
 * place en revue. Un même document peut légitimement être signalé deux fois
 * par deux personnes, et refuser un signalement légitime coûte plus cher au
 * projet qu'un doublon à trier (docs/DATA_MODEL.md §4).
 */
final class DuplicateFingerprint
{
    public static function compute(
        int $documentTypeId,
        ?string $rawNumber,
        ?string $rawOwnerName,
    ): string {
        $parts = [
            (string) $documentTypeId,
            DocumentNumberNormalizer::normalize($rawNumber),
            NameNormalizer::normalize($rawOwnerName),
        ];

        return hash('sha256', implode('|', $parts));
    }
}
