<?php

declare(strict_types=1);

namespace App\Documents;

use App\Enums\NameConsistency;
use App\Matching\NameNormalizer;

/**
 * Cohérence entre le nom du compte et le nom déclaré (M-02, D-036).
 *
 * ------------------------------------------------------------------------
 * POURQUOI CE CONTRÔLE EXISTE.
 *
 * Déclarer une perte au nom d'autrui est le vecteur le MOINS COÛTEUX du
 * système : j'ouvre un compte, je déclare qu'une personne a perdu sa carte
 * d'identité, et la plateforme me prévient dès qu'un Trouveur la dépose. Je
 * suis alors en position de la revendiquer avant son propriétaire, avec un
 * temps d'avance offert par le service lui-même.
 *
 * Le contrôle ne REFUSE pas la déclaration : déclarer pour un parent âgé, un
 * enfant ou un conjoint est un cas légitime et fréquent. Il retire seulement
 * l'automatisme, et confie le cas à un humain.
 * ------------------------------------------------------------------------
 */
final class NameConsistencyCheck
{
    /**
     * Nombre minimal de tokens communs exigé.
     *
     * Un seul token partagé ne prouve rien — les prénoms se répètent. Deux
     * tokens concordants sont un signal raisonnable sans exiger une égalité
     * stricte, qui rejetterait les noms d'usage, les prénoms composés omis et
     * les orthographes administratives divergentes.
     */
    private const MINIMUM_SHARED_TOKENS = 2;

    public static function compare(?string $accountName, ?string $declaredName): NameConsistency
    {
        if ($accountName === null || $declaredName === null
            || trim($accountName) === '' || trim($declaredName) === '') {
            return NameConsistency::Unknown;
        }

        $accountTokens = self::tokens($accountName);
        $declaredTokens = self::tokens($declaredName);

        if ($accountTokens === [] || $declaredTokens === []) {
            return NameConsistency::Unknown;
        }

        if ($accountTokens === $declaredTokens) {
            return NameConsistency::Match;
        }

        // Le nom le plus court doit être contenu dans le plus long : un compte
        // « Miro Olanda » couvre une déclaration « Ayo Miro Olanda », un second
        // prénom ayant simplement été omis à l'inscription.
        $shorter = count($accountTokens) <= count($declaredTokens) ? $accountTokens : $declaredTokens;
        $longer = $shorter === $accountTokens ? $declaredTokens : $accountTokens;

        $shared = array_intersect($shorter, $longer);

        if (count($shared) === count($shorter) && count($shorter) >= self::MINIMUM_SHARED_TOKENS) {
            return NameConsistency::Match;
        }

        return NameConsistency::Mismatch;
    }

    /** @return list<string> */
    private static function tokens(string $name): array
    {
        $normalized = NameNormalizer::normalize($name);

        return $normalized === '' ? [] : explode(' ', $normalized);
    }
}
