<?php

declare(strict_types=1);

use App\Models\User;

it('affiche le formulaire d’inscription', function (): void {
    $this->get('/register')->assertOk()->assertSee('Créer un compte');
});

it('crée un compte unique servant aux deux usages', function (): void {
    // Un seul type de compte (D-004) : Propriétaire et Trouveur sont des
    // capacités, pas des types exclusifs.
    $this->post('/register', [
        'full_name' => 'Ölanda Miro',
        'email' => 'nouveau@docutrack.invalid',
        'phone' => '0612345678',
        'password' => 'motdepasse-tres-long-1',
        'password_confirmation' => 'motdepasse-tres-long-1',
    ]);

    $user = User::where('email', 'nouveau@docutrack.invalid')->first();

    expect($user)->not->toBeNull()
        ->and($user->full_name_normalized)->toBe('miro olanda')
        ->and($user->phone_e164)->toBe('+237612345678');
});

it('crée le compte SANS téléphone vérifié', function (): void {
    // La vérification est un parcours distinct : tant qu'elle n'a pas abouti,
    // le compte ne peut ni rechercher ni revendiquer (D-013).
    $this->post('/register', [
        'full_name' => 'Bekundi Tavi',
        'email' => 'nonverifie@docutrack.invalid',
        'phone' => '0612345679',
        'password' => 'motdepasse-tres-long-1',
        'password_confirmation' => 'motdepasse-tres-long-1',
    ]);

    expect(User::where('email', 'nonverifie@docutrack.invalid')->first()->hasVerifiedPhone())
        ->toBeFalse();
});

it('refuse un numéro déjà utilisé, même écrit autrement', function (): void {
    // Sans cela, un attaquant créerait autant de comptes qu'il veut en variant
    // la graphie du même numéro, et les quotas par compte deviendraient
    // décoratifs (M-04).
    User::factory()->create(['phone_e164' => '+237612345678']);

    $this->post('/register', [
        'full_name' => 'Tavi Ndzomo',
        'email' => 'doublon@docutrack.invalid',
        'phone' => '00237612345678',
        'password' => 'motdepasse-tres-long-1',
        'password_confirmation' => 'motdepasse-tres-long-1',
    ])->assertSessionHasErrors('phone_e164');

    expect(User::where('email', 'doublon@docutrack.invalid')->exists())->toBeFalse();
});

it('refuse un mot de passe trop court', function (): void {
    $this->post('/register', [
        'full_name' => 'Tavi Ndzomo',
        'email' => 'faible@docutrack.invalid',
        'phone' => '0612345680',
        'password' => 'court1',
        'password_confirmation' => 'court1',
    ])->assertSessionHasErrors('password');
});

it('refuse un numéro de téléphone absent', function (): void {
    $this->post('/register', [
        'full_name' => 'Tavi Ndzomo',
        'email' => 'sanstel@docutrack.invalid',
        'password' => 'motdepasse-tres-long-1',
        'password_confirmation' => 'motdepasse-tres-long-1',
    ])->assertSessionHasErrors('phone_e164');
});
