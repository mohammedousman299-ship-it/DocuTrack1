<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AdminRole;
use App\Matching\NameNormalizer;
use App\Matching\Synthetic\SyntheticNameGenerator;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 *
 * Les noms sont ENTIÈREMENT FICTIFS, produits par combinaison de syllabes
 * inventées (§12 du master prompt). Aucun nom réel, même à titre d'exemple.
 */
final class UserFactory extends Factory
{
    protected $model = User::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = (new SyntheticNameGenerator(random_int(1, PHP_INT_MAX)))->fullName();

        return [
            'full_name' => $name,
            'full_name_normalized' => NameNormalizer::normalize($name),
            // .invalid est réservé par la norme : ces adresses ne peuvent
            // atteindre personne, même par accident.
            'email' => Str::lower(Str::random(12)).'@docutrack.invalid',
            'email_verified_at' => now(),
            'phone_e164' => '+2376'.random_int(10000000, 99999999),
            'phone_verified_at' => now(),
            'password' => 'password',
            'admin_role' => AdminRole::None,
            'reporter_score' => 50,
            'locale' => 'fr',
            'notification_prefs' => [],
            'remember_token' => Str::random(10),
        ];
    }

    /** Compte dont l'adresse e-mail n'est pas vérifiée. */
    public function unverifiedEmail(): static
    {
        return $this->state(fn (): array => ['email_verified_at' => null]);
    }

    /** Compte sans téléphone vérifié : ne peut ni rechercher ni revendiquer. */
    public function unverifiedPhone(): static
    {
        return $this->state(fn (): array => ['phone_verified_at' => null]);
    }

    /** Administrateur avec 2FA confirmée. */
    public function admin(AdminRole $role = AdminRole::Both): static
    {
        return $this->state(fn (): array => [
            'admin_role' => $role,
            'two_factor_secret' => Str::random(32),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    /** Administrateur SANS 2FA : doit se voir refuser tout accès admin. */
    public function adminWithoutTwoFactor(AdminRole $role = AdminRole::Both): static
    {
        return $this->state(fn (): array => [
            'admin_role' => $role,
            'two_factor_confirmed_at' => null,
        ]);
    }

    public function blocked(): static
    {
        return $this->state(fn (): array => ['blocked_until' => now()->addDay()]);
    }
}
