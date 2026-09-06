<?php

declare(strict_types=1);

namespace App\Enums;

enum LostDeclarationStatus: string
{
    /** Nom incohérent avec le compte : aucune notification automatique (D-036). */
    case PendingReview = 'pending_review';
    case Active = 'active';
    case Matched = 'matched';
    case Resolved = 'resolved';
    case Expired = 'expired';
    case Withdrawn = 'withdrawn';

    /** Déclarations confrontées aux signalements par le moteur. */
    public function isMatchable(): bool
    {
        return $this === self::Active || $this === self::Matched;
    }
}
