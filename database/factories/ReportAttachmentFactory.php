<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FoundReport;
use App\Models\ReportAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ReportAttachment> */
final class ReportAttachmentFactory extends Factory
{
    protected $model = ReportAttachment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'found_report_id' => FoundReport::factory(),
            // Clé non devinable : une clé prévisible annulerait le bénéfice du
            // bucket privé (M-12).
            'object_key' => 'documents/'.Str::uuid().'.jpg',
            'content_hash' => hash('sha256', Str::random(32)),
            'mime_type' => 'image/jpeg',
            'byte_size' => random_int(50_000, 400_000),
            'width' => 1024,
            'height' => 768,
            // Par défaut NON nettoyée : c'est l'état réel juste après un envoi,
            // et l'état dans lequel la pièce ne doit jamais être servie.
            'exif_stripped_at' => null,
            'expires_at' => now()->addDays(90),
        ];
    }

    public function stripped(): static
    {
        return $this->state(fn (): array => ['exif_stripped_at' => now()]);
    }
}
