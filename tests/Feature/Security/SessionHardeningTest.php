<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

it('configure le cookie de session comme le §4.6 l’exige', function (): void {
    expect(config('session.http_only'))->toBeTrue()
        ->and(config('session.same_site'))->toBe('lax')
        ->and(config('session.encrypt'))->toBeTrue();
});

it('stocke les sessions en base, jamais en fichier', function (): void {
    // Contrainte de plateforme (§3.1) : le système de fichiers du conteneur
    // n'est pas persistant. Un pilote 'file' déconnecterait les utilisateurs
    // à chaque montée en charge.
    expect(config('session.driver'))->not->toBe('file');
});

it('régénère l’identifiant de session à la connexion', function (): void {
    // Élévation de privilège : sans régénération, un identifiant de session
    // obtenu avant la connexion resterait valide après (fixation de session).
    $user = User::factory()->create(['password' => 'motdepasse-tres-long-1']);

    $this->get('/login');
    $avant = session()->getId();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'motdepasse-tres-long-1',
    ]);

    expect(session()->getId())->not->toBe($avant);
    $this->assertAuthenticatedAs($user);
});

it('applique la protection CSRF à toutes les routes web', function (): void {
    // Le middleware CSRF est neutralisé dans les tests fonctionnels de Laravel,
    // on ne peut donc pas provoquer un 419. On vérifie donc directement qu'il
    // est bien inscrit dans le groupe 'web', qui porte toutes les mutations
    // d'interface (§4.6).
    $groups = app(Kernel::class)->getMiddlewareGroups();

    // Laravel 13 nomme ce middleware PreventRequestForgery,
    // anciennement ValidateCsrfToken.
    expect($groups['web'])->toContain(PreventRequestForgery::class);
});

it('n’expose aucune donnée sensible dans la charge Livewire du composant de mesure', function (): void {
    // Vérification de principe sur le seul composant Livewire existant : une
    // propriété publique est sérialisée vers le navigateur
    // (DISCLOSURE_LEVELS.md §4.2). C'est le mode de fuite le plus probable de
    // cette pile, et le test grandira avec les composants métier.
    $contenu = $this->get('/dev/ui')->getContent();

    expect($contenu)->not->toContain('password')
        ->and($contenu)->not->toContain('two_factor_secret');
});
