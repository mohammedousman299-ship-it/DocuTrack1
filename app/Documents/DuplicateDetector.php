<?php

declare(strict_types=1);

namespace App\Documents;

use App\Enums\FoundReportStatus;
use App\Models\FoundReport;
use Illuminate\Support\Collection;

/**
 * Contrôle de doublon avant enregistrement d'un signalement (§1.5, §4.5).
 *
 * ------------------------------------------------------------------------
 * UNE COLLISION NE REJETTE JAMAIS UN SIGNALEMENT.
 *
 * Elle le place en revue. Un même document peut légitimement être signalé deux
 * fois — par deux passants, ou par la même personne qui croit sa première
 * saisie perdue. Refuser un signalement légitime coûte plus cher au projet
 * qu'un doublon à trier : chaque signalement perdu est un document non
 * restitué.
 * ------------------------------------------------------------------------
 *
 * Le contrôle porte sur une empreinte NORMALISÉE, jamais sur une égalité
 * stricte : deux personnes ne saisissent ni le nom ni le numéro de la même
 * façon.
 */
final class DuplicateDetector
{
    /**
     * Signalements existants partageant l'empreinte du candidat.
     *
     * @return Collection<int, FoundReport>
     */
    public static function existingFor(
        int $documentTypeId,
        ?string $rawNumber,
        ?string $rawOwnerName,
        ?string $excludeReportId = null,
    ): Collection {
        $fingerprint = DuplicateFingerprint::compute($documentTypeId, $rawNumber, $rawOwnerName);

        return FoundReport::query()
            ->where('duplicate_fingerprint', $fingerprint)
            ->whereNotIn('status', [
                // Un signalement rejeté ou expiré ne fait pas doublon : il
                // n'est plus dans l'index.
                FoundReportStatus::Rejected->value,
                FoundReportStatus::Expired->value,
            ])
            ->when($excludeReportId !== null, fn ($q) => $q->whereKeyNot($excludeReportId))
            ->get();
    }

    /**
     * Statut à donner à un signalement entrant.
     *
     * Trois causes distinctes de mise en revue, volontairement traitées
     * ensemble : collision d'empreinte, Trouveur peu fiable (M-06), et absence
     * de pièce jointe sur un type qui l'exige (D-030).
     */
    public static function statusFor(
        bool $hasDuplicate,
        int $reporterScore,
        bool $missingRequiredAttachment,
    ): FoundReportStatus {
        if ($hasDuplicate || $missingRequiredAttachment || $reporterScore < self::TRUSTED_SCORE) {
            return FoundReportStatus::PendingReview;
        }

        return FoundReportStatus::Active;
    }

    /**
     * Score en deçà duquel les signalements d'un Trouveur passent en revue.
     *
     * Valeur initiale argumentée, NON validée : la charge réelle de la file de
     * revue reste à mesurer (OPEN_QUESTIONS.md Q-27), et c'est elle qui devra
     * fixer ce seuil.
     */
    public const TRUSTED_SCORE = 30;
}
