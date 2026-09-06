<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DepositPoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DepositPoint> */
final class DepositPointFactory extends Factory
{
    protected $model = DepositPoint::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => 'Point de dépôt d\'essai',
            'kind' => 'partner',
            'region' => 'Centre',
            'city' => 'Ville de test',
            'address' => 'Adresse de test',
            'opening_hours' => '08h00 – 16h00',
            'institutional_contact' => 'contact@exemple.invalid',
            // Non vérifié par défaut : c'est l'état d'un point qu'un
            // administrateur vient d'ajouter.
            'is_verified' => false,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (): array => ['is_verified' => true]);
    }
}
