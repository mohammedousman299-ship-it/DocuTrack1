<?php

declare(strict_types=1);

use App\Enums\AdminRole;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

it('renvoie 404 — et non 403 — à un utilisateur ordinaire', function (): void {
    // Un 403 confirmerait l'existence de l'interface d'administration : c'est
    // une divulgation en soi (PERMISSIONS.md §3.3).
    $this->actingAs(User::factory()->create())
        ->get('/administration')
        ->assertNotFound();
});

it('renvoie 404 à un visiteur anonyme', function (): void {
    $this->get('/administration')->assertNotFound();
});

it('redirige un administrateur sans 2FA vers son activation', function (): void {
    // Ce n'est pas un intrus : c'est un compte incomplètement sécurisé.
    $this->actingAs(User::factory()->adminWithoutTwoFactor()->create())
        ->get('/administration')
        ->assertRedirect(route('two-factor.setup'));
});

it('laisse entrer un administrateur avec 2FA confirmée', function (): void {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/administration')
        ->assertOk();
});

it('renvoie 404 à un administrateur bloqué', function (): void {
    $admin = User::factory()->admin()->create(['blocked_until' => now()->addDay()]);

    $this->actingAs($admin)->get('/administration')->assertNotFound();
});

it('refuse les pouvoirs sensibles à un administrateur fonctionnel', function (): void {
    $functional = User::factory()->admin(AdminRole::Functional)->create();

    expect(Gate::forUser($functional)->allows('admin.functional'))->toBeTrue()
        ->and(Gate::forUser($functional)->allows('admin.sensitive'))->toBeFalse();
});

it('refuse les pouvoirs fonctionnels à un relecteur sensible', function (): void {
    $sensitive = User::factory()->admin(AdminRole::Sensitive)->create();

    expect(Gate::forUser($sensitive)->allows('admin.sensitive'))->toBeTrue()
        ->and(Gate::forUser($sensitive)->allows('admin.functional'))->toBeFalse();
});

it('refuse la recherche à un administrateur', function (): void {
    // La recherche est l'outil d'extraction du système. Un administrateur
    // dispose déjà d'un accès direct et tracé ; lui laisser la recherche
    // créerait un chemin moins tracé que les autres.
    expect(Gate::forUser(User::factory()->admin()->create())->allows('search.perform'))
        ->toBeFalse();
});

it('refuse la recherche sans téléphone vérifié', function (): void {
    expect(Gate::forUser(User::factory()->unverifiedPhone()->create())->allows('search.perform'))
        ->toBeFalse();
});

it('refuse la recherche à un compte bloqué', function (): void {
    expect(Gate::forUser(User::factory()->blocked()->create())->allows('search.perform'))
        ->toBeFalse();
});

it('autorise la recherche à un compte ordinaire vérifié', function (): void {
    expect(Gate::forUser(User::factory()->create())->allows('search.perform'))->toBeTrue();
});
