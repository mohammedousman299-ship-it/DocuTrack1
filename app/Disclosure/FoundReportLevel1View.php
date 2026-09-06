<?php

declare(strict_types=1);

namespace App\Disclosure;

use App\Models\FoundReport;

/**
 * Vue de niveau N1 d'un signalement — « existence ».
 *
 * ------------------------------------------------------------------------
 * LE MASQUAGE EST UN TYPE DE DONNÉE, PAS UN AFFICHAGE.
 *
 * Cette classe ne PORTE PAS les champs interdits à ce niveau. Ce n'est pas
 * une commodité : c'est ce qui rend la fuite impossible plutôt
 * qu'improbable. Un modèle Eloquent transmis à un composant Livewire est
 * sérialisé EN ENTIER vers le navigateur, y compris les champs jamais rendus
 * à l'écran — c'est le mode de fuite le plus probable de cette pile, et
 * précisément l'erreur relevée dans le prototype audité (AUDIT_PROTOTYPE V3).
 *
 * Ce qui n'est PAS ici, et ne doit jamais y arriver :
 * numéro du document même partiel, nom complet, ville, point de dépôt,
 * texte libre du Trouveur, identité du Trouveur, image, score de
 * correspondance, nombre de résultats.
 * ------------------------------------------------------------------------
 *
 * Voir docs/DISCLOSURE_LEVELS.md §2.
 */
final readonly class FoundReportLevel1View
{
    private function __construct(
        /** Jeton opaque : jamais l'identifiant technique, qui serait énumérable. */
        public string $token,
        public string $documentTypeLabel,
        /** Initiales seulement — « M. O. » */
        public string $ownerInitials,
        /** Mois et année seulement — « août 2026 » */
        public string $foundMonth,
        /** Région seulement, jamais la ville. */
        public string $foundRegion,
    ) {}

    public static function from(FoundReport $report, ?string $locale = null): self
    {
        return new self(
            token: self::tokenFor($report),
            documentTypeLabel: $report->documentType->label($locale),
            ownerInitials: self::initials($report->owner_name_normalized),
            foundMonth: $report->found_on?->translatedFormat('F Y') ?? '',
            foundRegion: $report->found_region,
        );
    }

    /**
     * Jeton opaque dérivé de l'identifiant, non réversible côté client.
     *
     * L'identifiant technique n'est pas exposé : même non séquentiel, le
     * publier permettrait de recouper deux résultats entre eux.
     */
    private static function tokenFor(FoundReport $report): string
    {
        return substr(hash_hmac('sha256', 'n1:'.$report->id, (string) config('app.key')), 0, 32);
    }

    /**
     * Initiales calculées CÔTÉ SERVEUR à partir du nom normalisé.
     *
     * Le masquage ne peut pas être fait par CSS ni par JavaScript : la donnée
     * complète parviendrait au navigateur (§12 du master prompt).
     */
    private static function initials(?string $normalizedName): string
    {
        if ($normalizedName === null || $normalizedName === '') {
            return '—';
        }

        $initials = array_map(
            static fn (string $token): string => mb_strtoupper(mb_substr($token, 0, 1)).'.',
            explode(' ', $normalizedName)
        );

        return implode(' ', $initials);
    }
}
