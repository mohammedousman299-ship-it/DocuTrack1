<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\ViewErrorBag;

it('affiche le formulaire de connexion', function (): void {
    $this->get('/login')->assertOk()->assertSee('Se connecter');
});

it('connecte un compte valide', function (): void {
    $user = User::factory()->create(['password' => 'motdepasse-tres-long-1']);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'motdepasse-tres-long-1',
    ]);

    $this->assertAuthenticatedAs($user);
});

it('ne distingue pas un compte inconnu d’un mot de passe erroné', function (): void {
    // Distinguer les deux confirmerait l'existence d'un compte : c'est une
    // divulgation en soi (THREAT_MODEL.md M-05).
    $user = User::factory()->create(['password' => 'motdepasse-tres-long-1']);

    $premierMessage = static function (): string {
        $errors = session('errors');

        return $errors instanceof ViewErrorBag
            ? (string) $errors->first()
            : (string) Arr::flatten((array) $errors)[0];
    };

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'mauvais-mot-de-passe-1',
    ]);
    $messageMauvaisMotDePasse = $premierMessage();

    session()->forget('errors');

    $this->post('/login', [
        'email' => 'inexistant@docutrack.invalid',
        'password' => 'mauvais-mot-de-passe-1',
    ]);
    $messageCompteInconnu = $premierMessage();

    expect($messageMauvaisMotDePasse)->toBe($messageCompteInconnu)
        ->and($messageMauvaisMotDePasse)->not->toBe('');

    $this->assertGuest();
});
