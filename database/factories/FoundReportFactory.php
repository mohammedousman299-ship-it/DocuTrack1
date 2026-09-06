<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FoundReportStatus;
use App\Matching\Synthetic\SyntheticNameGenerator;
use App\Models\DocumentType;
use App\Models\FoundReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FoundReport>
 *
 * Données ENTIÈREMENT FICTIVES (§12 du master prompt).
 */
final class FoundReportFactory extends Factory
{
    protected $model = FoundReport::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $generator = new SyntheticNameGenerator(random_int(1, PHP_INT_MAX));

        return [
            'finder_user_id' => User::factory(),
            'document_type_id' => fn () => DocumentType::query()->value('id')
                ?? DocumentType::create([
                    'code' => 'fabrique_'.uniqid(),
                    'label_fr' => 'Type de fabrique',
                    'label_en' => 'Factory type',
                    'sensitivity' => 'standard',
                    'retention_days' => 180,
                ])->id,
            'owner_name' => $generator->fullName(),
            'found_on' => now()->subDays(random_int(0, 30))->toDateString(),
            'found_region' => 'Centre',
            'found_city' => 'Ville de test',
            'status' => FoundReportStatus::Active,
            'expires_at' => now()->addDays(180),
        ];
    }

    public function withNumber(string $number): static
    {
        return $this->afterMaking(fn (FoundReport $r) => $r->setDocumentNumber($number))
            ->afterCreating(function (FoundReport $r) use ($number): void {
                $r->setDocumentNumber($number);
                $r->save();
            });
    }

    public function pendingReview(): static
    {
        return $this->state(fn (): array => ['status' => FoundReportStatus::PendingReview]);
    }

    public function sensitive(): static
    {
        return $this->state(fn (): array => [
            'document_type_id' => DocumentType::firstOrCreate(
                ['code' => 'national_id'],
                [
                    'label_fr' => "Carte nationale d'identité",
                    'label_en' => 'National Identity Card',
                    'sensitivity' => 'high',
                    'retention_days' => 180,
                ]
            )->id,
        ]);
    }
}
