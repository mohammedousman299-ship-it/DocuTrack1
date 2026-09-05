<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Contracts\NotificationChannel;
use App\Notifications\OutgoingNotification;
use Illuminate\Support\Facades\Log;

/**
 * Adaptateur e-mail factice : journalise au lieu d'envoyer.
 *
 * Le canal réel passera par le pilote de messagerie de Laravel ; l'interface
 * ne change pas.
 */
final class FakeMailChannel implements NotificationChannel
{
    public function name(): string
    {
        return 'email';
    }

    public function send(OutgoingNotification $notification): bool
    {
        Log::info('[E-mail factice] notification', [
            'template' => $notification->template->value,
            'destination' => $this->maskDestination($notification->destination()),
            'body' => $notification->body,
        ]);

        return true;
    }

    private function maskDestination(string $destination): string
    {
        [$local, $domain] = array_pad(explode('@', $destination, 2), 2, '');

        return mb_substr($local, 0, 2).str_repeat('*', max(0, mb_strlen($local) - 2)).'@'.$domain;
    }
}
