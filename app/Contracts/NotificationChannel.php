<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Notifications\OutgoingNotification;

/**
 * Canal d'envoi de notification.
 *
 * Aucun prestataire SMS n'est confirmé (OPEN_QUESTIONS.md Q-15) : aucun nom
 * d'API, aucun endpoint et aucun format de charge utile n'est inventé. Le
 * développement se fait sur adaptateurs factices.
 *
 * Rappel de cadrage : le SMS n'est pas un canal de confort. C'est le contrôle
 * anti-Sybil principal (D-013), et son budget est un budget de sécurité.
 */
interface NotificationChannel
{
    public function name(): string;

    /** @return bool true si l'envoi a été accepté par le canal */
    public function send(OutgoingNotification $notification): bool;
}
