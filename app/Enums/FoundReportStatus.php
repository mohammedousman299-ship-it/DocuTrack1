<?php

declare(strict_types=1);

namespace App\Enums;

enum FoundReportStatus: string
{
    /** En attente de revue : Trouveur peu fiable, ou collision d'empreinte (M-06). */
    case PendingReview = 'pending_review';
    case Active = 'active';
    case Matched = 'matched';
    case Returned = 'returned';
    case Rejected = 'rejected';
    case Expired = 'expired';

    /** Un signalement n'entre dans l'index de rapprochement qu'une fois actif. */
    public function isMatchable(): bool
    {
        return $this === self::Active || $this === self::Matched;
    }
}
