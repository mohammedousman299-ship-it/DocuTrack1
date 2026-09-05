<?php

declare(strict_types=1);

use App\Auth\PhoneVerification\PhoneVerificationService;
use App\Auth\PhoneVerification\VerificationOutcome;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

function service(): PhoneVerificationService
{
    return app(PhoneVerificationService::class);
}

it('ne stocke jamais le code en clair', function (): void {
    // Une fuite de base ne doit pas permettre de valider des numéros à la
    // place des utilisateurs.
    $user = User::factory()->unverifiedPhone()->create();
    $code = service()->issue($user);

    $stored = DB::table('phone_verification_codes')->where('user_id', $user->id)->value('code_hash');

    expect($code)->not->toBeNull()
        ->and($stored)->not->toBe($code)
        ->and($stored)->toBe(hash('sha256', (string) $code));
});

it('vérifie le numéro avec le bon code', function (): void {
    $user = User::factory()->unverifiedPhone()->create();
    $code = service()->issue($user);

    expect(service()->verify($user, (string) $code))->toBe(VerificationOutcome::Verified)
        ->and($user->fresh()->hasVerifiedPhone())->toBeTrue();
});

it('refuse un code erroné', function (): void {
    $user = User::factory()->unverifiedPhone()->create();
    service()->issue($user);

    expect(service()->verify($user, '000000'))->toBe(VerificationOutcome::Invalid)
        ->and($user->fresh()->hasVerifiedPhone())->toBeFalse();
});

it('borne les tentatives de devinette', function (): void {
    // Un code à 6 chiffres se devine en 10^6 essais : sans borne, l'attaque
    // est réalisable.
    $user = User::factory()->unverifiedPhone()->create();
    service()->issue($user);

    for ($i = 0; $i < PhoneVerificationService::MAX_ATTEMPTS; $i++) {
        service()->verify($user, '000000');
    }

    expect(service()->verify($user, '000000'))->toBe(VerificationOutcome::TooManyAttempts)
        ->and($user->fresh()->hasVerifiedPhone())->toBeFalse();
});

it('refuse un code expiré', function (): void {
    $user = User::factory()->unverifiedPhone()->create();
    $code = service()->issue($user);

    $this->travel(PhoneVerificationService::LIFETIME_MINUTES + 1)->minutes();

    expect(service()->verify($user, (string) $code))->toBe(VerificationOutcome::Expired);
});

it('invalide les codes précédents à chaque nouvelle émission', function (): void {
    // Plusieurs codes valides simultanés multiplieraient les chances d'un
    // attaquant.
    $user = User::factory()->unverifiedPhone()->create();
    $ancien = service()->issue($user);
    $nouveau = service()->issue($user);

    expect(service()->verify($user, (string) $ancien))->toBe(VerificationOutcome::Invalid);

    $user->refresh();
    expect(service()->verify($user, (string) $nouveau))->toBe(VerificationOutcome::Verified);
});

it('refuse un code émis pour un autre numéro', function (): void {
    $user = User::factory()->unverifiedPhone()->create(['phone_e164' => '+237600000001']);
    $code = service()->issue($user);

    $user->forceFill(['phone_e164' => '+237600000002'])->save();

    expect(service()->verify($user->fresh(), (string) $code))->toBe(VerificationOutcome::NoActiveCode);
});

it('ne consomme pas le code d’un autre compte', function (): void {
    $cible = User::factory()->unverifiedPhone()->create();
    $attaquant = User::factory()->unverifiedPhone()->create();
    $code = service()->issue($cible);

    expect(service()->verify($attaquant, (string) $code))->toBe(VerificationOutcome::NoActiveCode)
        ->and($cible->fresh()->hasVerifiedPhone())->toBeFalse();
});

it('envoie le code par SMS et jamais par e-mail', function (): void {
    // Le code doit atteindre le téléphone dont on vérifie la possession.
    $user = User::factory()->unverifiedPhone()->create();
    service()->issue($user);

    $channels = DB::table('notifications')->where('user_id', $user->id)->pluck('channel');

    expect($channels->all())->toBe(['sms']);
});

it('bloque la recherche tant que le numéro n’est pas vérifié', function (): void {
    // Le middleware garde les fonctions qui reposent sur les quotas par compte.
    Route::middleware(['web', 'auth', 'phone.verified'])->get('/_test/protege', fn () => 'ok');

    $nonVerifie = User::factory()->unverifiedPhone()->create();
    $verifie = User::factory()->create();

    $this->actingAs($nonVerifie)->get('/_test/protege')
        ->assertRedirect(route('phone.verify.show'));

    $this->actingAs($verifie)->get('/_test/protege')->assertOk();
});

it('refuse un compte bloqué même avec un numéro vérifié', function (): void {
    Route::middleware(['web', 'auth', 'phone.verified'])->get('/_test/protege2', fn () => 'ok');

    $this->actingAs(User::factory()->blocked()->create())
        ->get('/_test/protege2')->assertForbidden();
});

it('affiche le parcours de vérification et accepte le code saisi', function (): void {
    $user = User::factory()->unverifiedPhone()->create();

    $this->actingAs($user)->get(route('phone.verify.show'))->assertOk();

    $this->actingAs($user)->post(route('phone.verify.send'))->assertRedirect();

    $code = DB::table('phone_verification_codes')->where('user_id', $user->id)->first();
    expect($code)->not->toBeNull();

    // Le code en clair n'existe qu'à l'émission : on rejoue via le service.
    $clair = service()->issue($user->fresh());

    $this->actingAs($user->fresh())
        ->post(route('phone.verify'), ['code' => $clair])
        ->assertRedirect(route('home'));

    expect($user->fresh()->hasVerifiedPhone())->toBeTrue();
});
