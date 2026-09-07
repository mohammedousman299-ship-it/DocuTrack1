<?php

use App\Enums\MatchingVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Premier jeu de réglages actif.
 *
 * Les valeurs sont celles de MATCHING.md §3.1 et §3.4 — **proposées, jamais
 * mesurées**. Elles existent pour que le moteur puisse tourner et être
 * mesuré ; le balayage du jalon 5 les remplace par des valeurs calibrées.
 *
 * Le jeu est versionné plutôt que modifié : un score porte sa version, sinon
 * il devient inexplicable dès que les réglages changent.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('matching_settings')->insertOrIgnore([
            'algorithm_version' => MatchingVersion::V1->value,
            'notify_threshold' => 0.750,
            'review_threshold' => 0.550,
            'weight_number' => 0.600,
            'weight_name' => 0.350,
            'weight_geography' => 0.050,
            'number_typo_name_threshold' => 0.850,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('matching_settings')
            ->where('algorithm_version', MatchingVersion::V1->value)
            ->delete();
    }
};
