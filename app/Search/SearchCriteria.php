<?php

declare(strict_types=1);

namespace App\Search;

/**
 * Combinaison minimale de critères de recherche (§4.2, DISCLOSURE_LEVELS §3).
 *
 * ------------------------------------------------------------------------
 * UNE RECHERCHE PAR NOM SEUL EST REFUSÉE.
 *
 * Elle remonterait tous les documents d'un homonyme, ce qui est un vecteur
 * d'attaque direct : un attaquant balaierait des noms courants et
 * collecterait des existences de documents sans rien connaître d'autre.
 * ------------------------------------------------------------------------
 *
 * Deux combinaisons acceptées :
 *   C1 — type de document + numéro complet
 *   C2 — type de document + nom complet + un troisième critère
 *
 * Les valeurs vivent en configuration pour être resserrées sans redéploiement
 * si la détection d'abus le justifie.
 */
enum SearchCriteria: string
{
    case C1 = 'C1';
    case C2 = 'C2';

    /**
     * Détermine la combinaison satisfaite, ou null si aucune.
     *
     * @param  array<string, mixed>  $input
     */
    public static function satisfiedBy(array $input): ?self
    {
        $hasType = self::filled($input['document_type_id'] ?? null);

        if (! $hasType) {
            return null;
        }

        if (self::filled($input['document_number'] ?? null)) {
            return self::C1;
        }

        if (! self::filled($input['owner_name'] ?? null)) {
            return null;
        }

        $thirdCriteria = [
            $input['lost_region'] ?? null,
            $input['lost_on'] ?? null,
        ];

        foreach ($thirdCriteria as $criterion) {
            if (self::filled($criterion)) {
                return self::C2;
            }
        }

        return null;
    }

    private static function filled(mixed $value): bool
    {
        return $value !== null && $value !== '' && $value !== [];
    }

    /** Message expliquant ce qui manque, sans révéler la logique de détection. */
    public function description(): string
    {
        return match ($this) {
            self::C1 => 'type de document et numéro',
            self::C2 => 'type de document, nom et un troisième élément',
        };
    }
}
