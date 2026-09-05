<?php

declare(strict_types=1);

namespace App\Providers;

use App\Notifications\Channels\FakeMailChannel;
use App\Notifications\Channels\FakeSmsChannel;
use App\Notifications\NotificationDispatcher;
use Illuminate\Support\ServiceProvider;

final class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NotificationDispatcher::class, function (): NotificationDispatcher {
            // Aucun prestataire réel n'est confirmé (Q-15). Les adaptateurs
            // factices sont le SEUL choix disponible ; le jour où un
            // prestataire existe, seul ce tableau change.
            $channels = [
                'sms' => new FakeSmsChannel,
                'email' => new FakeMailChannel,
            ];

            return new NotificationDispatcher(
                channels: $channels,
                cap: (int) config('docutrack.notifications.cap_per_window'),
                capWindowHours: (int) config('docutrack.notifications.cap_window_hours'),
            );
        });
    }
}
