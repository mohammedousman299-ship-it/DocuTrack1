<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Contracts\NotificationChannel;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Répartiteur de notifications.
 *
 * Porte quatre garanties exigées par la §7 :
 *
 * 1. CONTENU MINIMAL — seuls les paramètres déclarés par le gabarit sont
 *    acceptés ; tout autre paramètre lève une exception (M-10).
 * 2. IDEMPOTENCE — la clé est dérivée de l'ÉVÈNEMENT MÉTIER, jamais du passage
 *    de cron : un rejeu ne notifie pas deux fois.
 * 3. PRÉFÉRENCES ET DÉSABONNEMENT — respectés par canal.
 * 4. PLAFOND PAR PÉRIODE — évite le harcèlement et la fuite par accumulation.
 */
final class NotificationDispatcher
{
    /** @param array<string, NotificationChannel> $channels */
    public function __construct(
        private readonly array $channels,
        private readonly int $cap,
        private readonly int $capWindowHours,
    ) {}

    /**
     * Met une notification en file. Renvoie false si elle a été supprimée
     * (doublon, préférence, plafond, destination absente).
     *
     * @param  array<string, string>  $parameters
     */
    public function queue(
        User $recipient,
        NotificationTemplate $template,
        string $channel,
        string $idempotencyKey,
        array $parameters = [],
    ): bool {
        $this->assertParametersAreAllowed($template, $parameters);

        if ($channel === 'sms' && ! $template->allowsSms()) {
            throw new InvalidArgumentException(
                "Le gabarit {$template->value} ne doit jamais partir par SMS : "
                .'un SMS n\'est pas confidentiel.'
            );
        }

        if (! $this->hasDestination($recipient, $channel)) {
            return false;
        }

        if (! $this->acceptsChannel($recipient, $channel)) {
            return false;
        }

        if ($this->hasReachedCap($recipient)) {
            return false;
        }

        // insertOrIgnore + clé unique : c'est la base qui garantit
        // l'idempotence, pas une vérification applicative sujette aux courses.
        $inserted = DB::table('notifications')->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'user_id' => $recipient->id,
            'channel' => $channel,
            'template' => $template->value,
            'idempotency_key' => $idempotencyKey,
            'status' => 'queued',
            'retry_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $inserted > 0;
    }

    /**
     * Envoie les notifications en attente, dans la limite du nombre demandé.
     *
     * Appelé par POST /internal/cron/notify : il n'existe ni worker permanent
     * ni scheduler en processus sur la plateforme cible (§3.1).
     */
    public function flush(int $limit = 50): int
    {
        $pending = DB::table('notifications')
            ->where('status', 'queued')
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        $sent = 0;

        foreach ($pending as $row) {
            $user = User::find($row->user_id);
            $template = NotificationTemplate::tryFrom($row->template);

            if ($user === null || $template === null) {
                $this->markFailed((string) $row->id, 'destinataire ou gabarit introuvable');

                continue;
            }

            $channel = $this->channels[$row->channel] ?? null;

            if ($channel === null) {
                $this->markFailed((string) $row->id, "canal {$row->channel} non configuré");

                continue;
            }

            // Le corps est reconstruit ICI, à l'envoi, et n'est jamais stocké :
            // la table ne doit contenir aucun contenu de message (M-10).
            $body = $this->render($template, $user, $this->parametersFor($template, $user));

            $accepted = $channel->send(new OutgoingNotification(
                recipient: $user,
                template: $template,
                channel: (string) $row->channel,
                body: $body,
            ));

            if ($accepted) {
                DB::table('notifications')->where('id', $row->id)->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'updated_at' => now(),
                ]);
                $sent++;
            } else {
                $this->markFailed((string) $row->id, 'canal a refusé l\'envoi');
            }
        }

        return $sent;
    }

    /** @param array<string, string> $parameters */
    private function assertParametersAreAllowed(
        NotificationTemplate $template,
        array $parameters,
    ): void {
        $allowed = $template->allowedParameters();
        $unexpected = array_diff(array_keys($parameters), $allowed);

        if ($unexpected !== []) {
            throw new InvalidArgumentException(sprintf(
                'Paramètres interdits pour le gabarit %s : %s. Une notification ne '
                .'contient jamais de donnée de niveau N2 ou N3 (docs/THREAT_MODEL.md M-10). '
                .'Paramètres autorisés : %s.',
                $template->value,
                implode(', ', $unexpected),
                $allowed === [] ? 'aucun' : implode(', ', $allowed),
            ));
        }
    }

    /** @return array<string, string> */
    private function parametersFor(NotificationTemplate $template, User $user): array
    {
        if (! in_array('given_name', $template->allowedParameters(), true)) {
            return [];
        }

        // Premier token seulement : jamais le nom complet.
        $tokens = preg_split('/\s+/', trim($user->full_name)) ?: [];

        return ['given_name' => (string) ($tokens[0] ?? '')];
    }

    /** @param array<string, string> $parameters */
    private function render(
        NotificationTemplate $template,
        User $user,
        array $parameters,
    ): string {
        return (string) __($template->translationKey(), $parameters, $user->locale);
    }

    private function hasDestination(User $user, string $channel): bool
    {
        return $channel === 'sms'
            ? $user->phone_e164 !== null
            : $user->email !== '';
    }

    private function acceptsChannel(User $user, string $channel): bool
    {
        $prefs = $user->notification_prefs;

        // Par défaut, un canal est accepté : un utilisateur qui n'a rien réglé
        // doit recevoir ce qui le concerne. Le désabonnement est explicite.
        return ! array_key_exists($channel, $prefs) || $prefs[$channel] !== false;
    }

    private function hasReachedCap(User $user): bool
    {
        $recent = DB::table('notifications')
            ->where('user_id', $user->id)
            ->where('created_at', '>=', now()->subHours($this->capWindowHours))
            ->count();

        return $recent >= $this->cap;
    }

    private function markFailed(string $id, string $reason): void
    {
        DB::table('notifications')->where('id', $id)->update([
            'status' => 'failed',
            'failed_at' => now(),
            'failure_reason' => $reason,
            'retry_count' => DB::raw('retry_count + 1'),
            'updated_at' => now(),
        ]);
    }
}
