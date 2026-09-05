<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\Auth\VerifyEmailNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

it('dépose la vérification d’adresse dans la file, jamais en synchrone', function (): void {
    // Un envoi synchrone ferait dépendre le temps de réponse de l'inscription
    // d'un service tiers, et une panne de messagerie ferait échouer la
    // création de compte elle-même. On vérifie le COMPORTEMENT : un travail
    // atterrit bien dans la file, drainée ensuite par l'endpoint interne.
    config()->set('queue.default', 'database');

    expect(DB::table('jobs')->count())->toBe(0);

    User::factory()->unverifiedEmail()->create()->sendEmailVerificationNotification();

    expect(DB::table('jobs')->count())->toBe(1);
});

it('notifie l’utilisateur à l’inscription', function (): void {
    Notification::fake();

    $this->post('/register', [
        'full_name' => 'Tavi Ndzomo',
        'email' => 'verif@docutrack.invalid',
        'phone' => '0612345699',
        'password' => 'motdepasse-tres-long-1',
        'password_confirmation' => 'motdepasse-tres-long-1',
    ]);

    Notification::assertSentTo(
        User::where('email', 'verif@docutrack.invalid')->firstOrFail(),
        VerifyEmailNotification::class
    );
});

it('ne contient aucune donnée métier dans le message', function (): void {
    // Un message d'authentification porte un lien, rien d'autre : les données
    // de niveau N2 ou N3 n'y ont pas leur place (M-10).
    $user = User::factory()->unverifiedEmail()->create();

    $mail = (new VerifyEmailNotification)->toMail($user);
    $rendered = (string) $mail->render();

    expect($rendered)->not->toContain($user->full_name)
        ->and($rendered)->not->toContain((string) $user->phone_e164);
});
