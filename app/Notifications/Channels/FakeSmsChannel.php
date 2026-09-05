<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Contracts\NotificationChannel;
use App\Notifications\OutgoingNotification;
use Illuminate\Support\Facades\Log;

/**
 * Adaptateur SMS factice.
 *
 * Aucun prestataire n'est confirmé (Q-15) : aucun nom d'API, endpoint ou
 * format de charge utile n'est inventé. Cet adaptateur journalise l'envoi et
 * le déclare accepté.
 *
 * AVERTISSEMENT D'EXPLOITATION : la mise en production est impossible tant
 * qu'aucun prestataire réel n'existe. Sans SMS, aucun compte ne peut être
 * vérifié, donc ni recherche ni revendication ne fonctionnent (D-013).
 */
final class FakeSmsChannel implements NotificationChannel
{
    public function name(): string
    {
        return 'sms';
    }

    public function send(OutgoingNotification $notification): bool
    {
        // Le corps est journalisé UNIQUEMENT parce que ce canal est factice et
        // que les gabarits ne contiennent, par construction, aucune donnée de
        // niveau N2 ou N3 (M-10). Un adaptateur réel ne journaliserait pas le
        // corps, et jamais le numéro en clair.
        Log::info('[SMS factice] notification', [
            'template' => $notification->template->value,
            'destination' => $this->maskDestination($notification->destination()),
            'body' => $notification->body,
        ]);

        return true;
    }

    /** Masque le numéro dans les journaux : §12 interdit d'y écrire des données personnelles. */
    private function maskDestination(string $destination): string
    {
        return mb_substr($destination, 0, 4).str_repeat('*', max(0, mb_strlen($destination) - 6))
            .mb_substr($destination, -2);
    }
}
