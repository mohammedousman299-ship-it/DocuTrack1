<?php

declare(strict_types=1);

use App\Models\User;
use PragmaRX\Google2FA\Google2FA;

it('affiche l’écran d’activation à un compte authentifié', function (): void {
    $this->actingAs(User::factory()->adminWithoutTwoFactor()->create())
        ->get(route('two-factor.setup'))
        ->assertOk()
        ->assertSee('Activer la double authentification');
});

it('explique pourquoi le SMS est écarté pour ce rôle', function (): void {
    // Un SMS est vulnérable au transfert frauduleux de carte SIM (M-13), et
    // c'est le compte administrateur qui voit toutes les images (M-09).
    $this->actingAs(User::factory()->adminWithoutTwoFactor()->create())
        ->get(route('two-factor.setup'))
        ->assertSee('et non', escape: false)
        ->assertSee('carte SIM', escape: false);
});

it('permet à un administrateur d’activer puis de confirmer sa 2FA', function (): void {
    // Sans cet écran, aucun administrateur ne pouvait activer sa 2FA, donc
    // l'espace d'administration était inaccessible en pratique (Q-29).
    $admin = User::factory()->adminWithoutTwoFactor()->create([
        'password' => 'motdepasse-tres-long-1',
    ]);

    $this->actingAs($admin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('two-factor.enable'));

    $admin->refresh();
    expect($admin->two_factor_secret)->not->toBeNull()
        ->and($admin->hasConfirmedTwoFactor())->toBeFalse();

    // Un vrai code TOTP, dérivé du secret que Fortify vient de générer.
    $code = (new Google2FA)->getCurrentOtp(decrypt($admin->two_factor_secret));

    $this->actingAs($admin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('two-factor.confirm'), ['code' => $code]);

    expect($admin->fresh()->hasConfirmedTwoFactor())->toBeTrue();
});

it('débloque l’administration une fois la 2FA confirmée', function (): void {
    $admin = User::factory()->adminWithoutTwoFactor()->create([
        'password' => 'motdepasse-tres-long-1',
    ]);

    $this->actingAs($admin)->get('/administration')->assertRedirect(route('two-factor.setup'));

    $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('two-factor.enable'));
    $admin->refresh();
    $code = (new Google2FA)->getCurrentOtp(decrypt($admin->two_factor_secret));
    $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('two-factor.confirm'), ['code' => $code]);

    $this->actingAs($admin->fresh())->get('/administration')->assertOk();
});

it('refuse un code TOTP erroné', function (): void {
    $admin = User::factory()->adminWithoutTwoFactor()->create([
        'password' => 'motdepasse-tres-long-1',
    ]);

    $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('two-factor.enable'));

    $this->actingAs($admin->fresh())->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('two-factor.confirm'), ['code' => '000000']);

    expect($admin->fresh()->hasConfirmedTwoFactor())->toBeFalse();
});

it('affiche les écrans d’authentification restants', function (): void {
    // Ils étaient des ébauches à la fin du jalon 2 (Q-30).
    $this->get(route('password.request'))->assertOk()->assertSee('Mot de passe oublié');
    $this->actingAs(User::factory()->unverifiedEmail()->create())
        ->get('/email/verify')->assertOk()->assertSee('Confirmez votre adresse');
});
