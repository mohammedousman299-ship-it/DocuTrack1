<?php

declare(strict_types=1);

use App\Enums\AdminRole;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

it('attribue un identifiant non séquentiel', function (): void {
    // Un entier auto-incrémenté révélerait le volume d'utilisateurs et
    // permettrait l'énumération (docs/DATA_MODEL.md §2.1).
    $user = User::factory()->create();

    expect($user->id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
});

it('dérive le nom normalisé du nom saisi', function (): void {
    $user = User::factory()->create(['full_name' => 'Ölanda Miro']);

    expect($user->full_name_normalized)->toBe('miro olanda');
});

it('recalcule le nom normalisé quand le nom change', function (): void {
    // Une dérive entre les deux fausserait le contrôle de cohérence du nom
    // (M-01, M-02) et l'empreinte de doublon.
    $user = User::factory()->create(['full_name' => 'Ölanda Miro']);

    $user->update(['full_name' => 'Bekundi Tavi']);

    expect($user->fresh()->full_name_normalized)->toBe('bekundi tavi');
});

it('chiffre le secret de double authentification en base', function (): void {
    $user = User::factory()->admin()->create();

    $stored = DB::table('users')->where('id', $user->id)->value('two_factor_secret');

    expect($stored)->not->toBe($user->two_factor_secret)
        ->and($user->two_factor_secret)->not->toBeNull();
});

it('ne sérialise jamais les secrets', function (): void {
    $serialised = User::factory()->admin()->create()->toArray();

    expect($serialised)->not->toHaveKeys([
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
    ]);
});

it('distingue un téléphone vérifié d’un téléphone non vérifié', function (): void {
    expect(User::factory()->create()->hasVerifiedPhone())->toBeTrue()
        ->and(User::factory()->unverifiedPhone()->create()->hasVerifiedPhone())->toBeFalse();
});

it('refuse les pouvoirs d’administration à un administrateur sans 2FA', function (): void {
    // La 2FA est obligatoire pour tout administrateur (D-014, D-023).
    $withTwoFactor = User::factory()->admin()->create();
    $withoutTwoFactor = User::factory()->adminWithoutTwoFactor()->create();

    expect($withTwoFactor->canAdministerFunctionally())->toBeTrue()
        ->and($withTwoFactor->canAccessSensitiveData())->toBeTrue()
        ->and($withoutTwoFactor->canAdministerFunctionally())->toBeFalse()
        ->and($withoutTwoFactor->canAccessSensitiveData())->toBeFalse();
});

it('sépare l’administration fonctionnelle de la revue sensible', function (): void {
    // Un administrateur fonctionnel ne voit jamais un nom complet, un numéro
    // ni une image (D-014).
    $functional = User::factory()->admin(AdminRole::Functional)->create();
    $sensitive = User::factory()->admin(AdminRole::Sensitive)->create();

    expect($functional->canAdministerFunctionally())->toBeTrue()
        ->and($functional->canAccessSensitiveData())->toBeFalse()
        ->and($sensitive->canAccessSensitiveData())->toBeTrue()
        ->and($sensitive->canAdministerFunctionally())->toBeFalse();
});

it('ne considère pas un utilisateur ordinaire comme administrateur', function (): void {
    expect(User::factory()->create()->isAdministrator())->toBeFalse();
});

it('reconnaît un compte bloqué et un blocage expiré', function (): void {
    expect(User::factory()->blocked()->create()->isBlocked())->toBeTrue()
        ->and(User::factory()->create(['blocked_until' => now()->subDay()])->isBlocked())->toBeFalse();
});

it('impose l’unicité du numéro de téléphone', function (): void {
    // Un numéro, un compte : sans cela le contrôle anti-Sybil s'effondre (M-04).
    User::factory()->create(['phone_e164' => '+237600000000']);

    expect(fn () => User::factory()->create(['phone_e164' => '+237600000000']))
        ->toThrow(UniqueConstraintViolationException::class);
});
