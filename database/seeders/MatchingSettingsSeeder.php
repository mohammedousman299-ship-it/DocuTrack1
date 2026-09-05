<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Paramètres initiaux du moteur de rapprochement.
 *
 * Ces valeurs sont ARGUMENTÉES mais NON VALIDÉES : le protocole de mesure du
 * jalon 5 (docs/MATCHING.md §6) doit les confirmer ou les corriger. Les
 * premières sondes suggèrent d'ailleurs qu'elles sont peut-être trop élevées
 * (OPEN_QUESTIONS.md Q-28).
 *
 * Elles vivent en base précisément pour être ajustables sans redéploiement.
 */
final class MatchingSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        DB::table('matching_settings')->updateOrInsert(
            ['algorithm_version' => 'v1'],
            [
                'notify_threshold' => 0.750,
                'review_threshold' => 0.550,
                'weight_number' => 0.600,
                'weight_name' => 0.350,
                'weight_geography' => 0.050,
                // Abaissé de 0,90 à 0,85 après mesure : le seuil initial était
                // inatteignable avec le trigramme seul (D-018).
                'number_typo_name_threshold' => 0.850,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }
}
