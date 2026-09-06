<?php

declare(strict_types=1);

namespace App\Enums;

enum NameConsistency: string
{
    case Match = 'match';
    case Mismatch = 'mismatch';
    case Unknown = 'unknown';

    /**
     * Une déclaration incohérente ne déclenche AUCUNE notification
     * automatique : elle part en revue (D-036, M-02).
     */
    public function allowsAutomaticNotification(): bool
    {
        return $this === self::Match;
    }
}
