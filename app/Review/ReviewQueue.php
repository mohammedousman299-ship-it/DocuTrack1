<?php

declare(strict_types=1);

namespace App\Review;

use App\Enums\LostDeclarationStatus;
use App\Models\LostDeclaration;
use Illuminate\Support\Collection;

/**
 * File de revue des déclarations à nom incohérent (D-036).
 *
 * Ordre : la plus ANCIENNE d'abord. Un utilisateur qui déclare pour un parent
 * âgé attend sans savoir pourquoi ; traiter d'abord les arrivées récentes
 * laisserait indéfiniment de côté les cas les plus mal servis.
 */
final class ReviewQueue
{
    /** @return Collection<int, DeclarationReviewItem> */
    public function pending(?string $locale = null, int $limit = 25): Collection
    {
        return LostDeclaration::query()
            ->with(['user', 'documentType'])
            ->where('status', LostDeclarationStatus::PendingReview)
            ->orderBy('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (LostDeclaration $d): DeclarationReviewItem => DeclarationReviewItem::from($d, $locale));
    }

    public function pendingCount(): int
    {
        return LostDeclaration::query()
            ->where('status', LostDeclarationStatus::PendingReview)
            ->count();
    }

    /**
     * Ancienneté du plus vieil élément, en heures.
     *
     * Q-27 met en doute la soutenabilité de la file. On ne peut pas y répondre
     * sans la mesurer : la profondeur seule ne dit rien, un délai qui s'allonge
     * dit que la revue ne suit plus.
     */
    public function oldestPendingHours(): ?int
    {
        $oldest = LostDeclaration::query()
            ->where('status', LostDeclarationStatus::PendingReview)
            ->min('created_at');

        return $oldest === null ? null : (int) now()->diffInHours($oldest, absolute: true);
    }
}
