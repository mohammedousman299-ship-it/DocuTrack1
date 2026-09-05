<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;

/** Message prêt à partir, déjà rendu et déjà validé par le répartiteur. */
final readonly class OutgoingNotification
{
    /** @param array<string, string> $parameters */
    public function __construct(
        public User $recipient,
        public NotificationTemplate $template,
        public string $channel,
        public string $body,
        public array $parameters = [],
    ) {}

    public function destination(): string
    {
        return $this->channel === 'sms'
            ? (string) $this->recipient->phone_e164
            : $this->recipient->email;
    }
}
