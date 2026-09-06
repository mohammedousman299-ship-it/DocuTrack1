<?php

declare(strict_types=1);

namespace App\Documents;

use App\Matching\DocumentNumberNormalizer;
use RuntimeException;

/**
 * Formes dérivées d'un numéro de document (D-007).
 *
 * Le numéro ne subsiste JAMAIS en clair en base. Il y prend trois formes :
 *
 * - `normalized` — écrit dans une colonne au cast `encrypted`, jamais indexée,
 *   déchiffré uniquement pour la restitution en N3 ;
 * - `hmac` — HMAC-SHA256 du numéro normalisé, indexé, qui permet l'égalité
 *   exacte sans exposer le numéro ;
 * - `last4` — les quatre derniers caractères, écrits eux aussi dans une
 *   colonne chiffrée, servant uniquement à CONFIRMER une saisie de
 *   l'utilisateur en N2.
 *
 * Le chiffrement est laissé aux casts Eloquent : chiffrer ici obligerait à
 * déchiffrer aussitôt pour laisser le cast rechiffrer.
 *
 * Conséquence assumée : la recherche approximative sur le numéro est
 * impossible. C'est le prix payé pour qu'une fuite de base ou de sauvegarde ne
 * livre aucun numéro de pièce d'identité (THREAT_MODEL.md M-08).
 */
final readonly class DocumentNumber
{
    private function __construct(
        public ?string $normalized,
        public ?string $hmac,
        public ?string $last4,
    ) {}

    public static function fromInput(?string $input): self
    {
        $normalized = DocumentNumberNormalizer::normalize($input);

        if ($normalized === '') {
            // Un numéro absent est NEUTRE dans le rapprochement, jamais
            // pénalisant (docs/MATCHING.md §3.2).
            return new self(null, null, null);
        }

        return new self(
            normalized: $normalized,
            hmac: self::hmac($normalized),
            last4: mb_substr($normalized, -4),
        );
    }

    /**
     * HMAC du numéro normalisé.
     *
     * La clé vit en variable d'environnement, hors de la base : sans cela, une
     * copie de la base permettrait de recalculer les empreintes et de tester
     * des numéros par force brute — ce qui annulerait tout le bénéfice de
     * D-007.
     */
    public static function hmac(string $normalized): string
    {
        $key = (string) config('docutrack.document_number_hmac_key');

        if ($key === '') {
            throw new RuntimeException(
                'DOCUMENT_NUMBER_HMAC_KEY est vide. Sans clé, les empreintes de '
                .'numéros seraient recalculables par quiconque obtient la base.'
            );
        }

        return hash_hmac('sha256', $normalized, $key);
    }

    /** Empreinte de recherche pour une saisie utilisateur. */
    public static function hmacForInput(?string $input): ?string
    {
        $normalized = DocumentNumberNormalizer::normalize($input);

        return $normalized === '' ? null : self::hmac($normalized);
    }

    public function isPresent(): bool
    {
        return $this->hmac !== null;
    }
}
