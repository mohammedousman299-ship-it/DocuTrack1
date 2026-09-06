<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LostDeclarationStatus;
use App\Enums\NameConsistency;
use App\Matching\Synthetic\SyntheticNameGenerator;
use App\Models\DocumentType;
use App\Models\LostDeclaration;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LostDeclaration> */
final class LostDeclarationFactory extends Factory
{
    protected $model = LostDeclaration::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $generator = new SyntheticNameGenerator(random_int(1, PHP_INT_MAX));

        return [
            'user_id' => User::factory(),
            'document_type_id' => fn () => DocumentType::query()->value('id')
                ?? DocumentType::create([
                    'code' => 'declaration_'.uniqid(),
                    'label_fr' => 'Type', 'label_en' => 'Type',
                    'sensitivity' => 'standard', 'retention_days' => 180,
                ])->id,
            'owner_name' => $generator->fullName(),
            'lost_on' => now()->subDays(random_int(0, 20))->toDateString(),
            'lost_region' => 'Centre',
            'lost_city' => 'Ville de test',
            'name_consistency' => NameConsistency::Match,
            'status' => LostDeclarationStatus::Active,
            'expires_at' => now()->addDays(180),
        ];
    }

    public function withNumber(string $number): static
    {
        return $this->afterCreating(function (LostDeclaration $d) use ($number): void {
            $d->setDocumentNumber($number);
            $d->save();
        });
    }

    public function mismatchedName(): static
    {
        return $this->state(fn (): array => [
            'name_consistency' => NameConsistency::Mismatch,
            'status' => LostDeclarationStatus::PendingReview,
        ]);
    }
}
