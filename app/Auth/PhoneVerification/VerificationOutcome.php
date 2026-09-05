<?php

declare(strict_types=1);

namespace App\Auth\PhoneVerification;

/**
 * Résultat d'une tentative de vérification.
 *
 * Les cas d'échec sont distingués ICI pour la logique interne, mais l'interface
 * n'en révèle pas le détail au-delà du nécessaire : indiquer qu'aucun code
 * n'est actif est utile à l'utilisateur légitime, préciser combien de
 * tentatives restent à un attaquant l'est moins.
 */
enum VerificationOutcome
{
    case Verified;
    case Invalid;
    case Expired;
    case TooManyAttempts;
    case NoActiveCode;

    public function isSuccess(): bool
    {
        return $this === self::Verified;
    }

    public function translationKey(): string
    {
        return 'phone_verification.'.match ($this) {
            self::Verified => 'verified',
            self::Invalid => 'invalid',
            self::Expired => 'expired',
            self::TooManyAttempts => 'too_many_attempts',
            self::NoActiveCode => 'no_active_code',
        };
    }
}
