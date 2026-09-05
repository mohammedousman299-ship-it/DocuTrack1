<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\NotificationDispatcher;
use App\Notifications\NotificationTemplate;
use Illuminate\Support\Facades\DB;

function dispatcher(): NotificationDispatcher
{
    return app(NotificationDispatcher::class);
}

it('refuse tout paramètre non déclaré par le gabarit', function (): void {
    // Le cœur du contrôle M-10 : une notification ne peut pas porter de donnée
    // de niveau N2 ou N3. Ce n'est pas une consigne de rédaction, c'est refusé
    // à l'exécution.
    $user = User::factory()->create();

    expect(fn () => dispatcher()->queue(
        $user,
        NotificationTemplate::MatchFound,
        'sms',
        'cle-1',
        ['document_number' => 'AB1234567'],
    ))->toThrow(InvalidArgumentException::class, 'document_number');
});

it('n’autorise aucun paramètre sur la notification de correspondance', function (): void {
    // Le message annonce l'existence d'une correspondance possible et invite à
    // se connecter. Rien du document, rien du signalement.
    expect(NotificationTemplate::MatchFound->allowedParameters())->toBe([]);
});

it('interdit le SMS pour les gabarits porteurs de lien sensible', function (): void {
    // Un SMS n'est pas confidentiel : il s'affiche sur un écran verrouillé.
    $user = User::factory()->create();

    expect(fn () => dispatcher()->queue(
        $user,
        NotificationTemplate::PasswordReset,
        'sms',
        'cle-2',
        ['url' => 'https://example.test/reset'],
    ))->toThrow(InvalidArgumentException::class);
});

it('est idempotent : la même clé ne notifie qu’une fois', function (): void {
    $user = User::factory()->create();

    $first = dispatcher()->queue($user, NotificationTemplate::MatchFound, 'sms', 'match-42');
    $second = dispatcher()->queue($user, NotificationTemplate::MatchFound, 'sms', 'match-42');

    expect($first)->toBeTrue()
        ->and($second)->toBeFalse()
        ->and(DB::table('notifications')->where('idempotency_key', 'match-42')->count())->toBe(1);
});

it('respecte un désabonnement par canal', function (): void {
    $user = User::factory()->create(['notification_prefs' => ['sms' => false]]);

    expect(dispatcher()->queue($user, NotificationTemplate::MatchFound, 'sms', 'cle-3'))->toBeFalse()
        ->and(dispatcher()->queue($user, NotificationTemplate::MatchFound, 'email', 'cle-4'))->toBeTrue();
});

it('notifie par défaut un utilisateur qui n’a rien réglé', function (): void {
    // Le désabonnement est explicite : l'absence de préférence ne prive pas
    // l'utilisateur d'une information qui le concerne.
    $user = User::factory()->create(['notification_prefs' => []]);

    expect(dispatcher()->queue($user, NotificationTemplate::MatchFound, 'sms', 'cle-5'))->toBeTrue();
});

it('applique un plafond par période', function (): void {
    $user = User::factory()->create();
    config()->set('docutrack.notifications.cap_per_window', 3);

    $accepted = 0;
    for ($i = 0; $i < 6; $i++) {
        if (dispatcher()->queue($user, NotificationTemplate::MatchFound, 'sms', "plafond-{$i}")) {
            $accepted++;
        }
    }

    expect($accepted)->toBe(3);
});

it('ne notifie pas par SMS un compte sans numéro', function (): void {
    $user = User::factory()->create(['phone_e164' => null, 'phone_verified_at' => null]);

    expect(dispatcher()->queue($user, NotificationTemplate::MatchFound, 'sms', 'cle-6'))->toBeFalse();
});

it('ne stocke jamais le corps du message en base', function (): void {
    // La table porte l'intention d'envoi, pas le contenu : le corps est
    // reconstruit à l'envoi depuis le gabarit (M-10).
    $user = User::factory()->create();
    dispatcher()->queue($user, NotificationTemplate::MatchFound, 'sms', 'cle-7');

    $columns = array_keys((array) DB::table('notifications')->first());

    expect($columns)->not->toContain('body')
        ->and($columns)->not->toContain('content')
        ->and($columns)->not->toContain('payload');
});

it('envoie les notifications en attente et les marque envoyées', function (): void {
    $user = User::factory()->create();
    dispatcher()->queue($user, NotificationTemplate::MatchFound, 'sms', 'cle-8');

    expect(dispatcher()->flush())->toBe(1)
        ->and(DB::table('notifications')->where('idempotency_key', 'cle-8')->value('status'))->toBe('sent');
});

it('rend le message dans la langue du destinataire', function (): void {
    $fr = User::factory()->create(['locale' => 'fr']);
    $en = User::factory()->create(['locale' => 'en']);

    expect(__('notifications.match_found', [], $fr->locale))->toContain('correspondance possible')
        ->and(__('notifications.match_found', [], $en->locale))->toContain('possible match');
});

it('ne promet jamais qu’un document a été retrouvé', function (): void {
    // Le vocabulaire est une exigence de conception, pas une nuance de
    // rédaction : un rapprochement n'est jamais une certitude (§5).
    foreach (['fr', 'en'] as $locale) {
        $message = (string) __('notifications.match_found', [], $locale);

        expect(mb_strtolower($message))
            ->not->toContain('retrouvé')
            ->not->toContain('found your')
            ->not->toContain('a été retrouvé');
    }
});
