<?php

use App\Enums\MatchingVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Réglages calibrés par le balayage du jalon 5 (D-041, MATCHING.md §3.7).
 *
 * Le seuil de notification passe de 0,75 à 0,90 : à 0,75, le balayage relève
 * 170 faux positifs sur 2 000 paires, contre 16 à 0,90 — et la couverture, qui
 * mesure ce qui aboutit à quelque chose, notification ou revue, reste à 0,9959
 * dans les deux cas. Relever le seuil ne perd donc pas de correspondances : il
 * déplace du travail vers la revue humaine.
 *
 * v1 n'est pas supprimée. Un score porte sa version, et les scores écrits sous
 * v1 doivent rester lisibles.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('matching_settings')->insertOrIgnore([
            'algorithm_version' => MatchingVersion::V2->value,
            // D-041.
            'notify_threshold' => 0.900,
            // Inchangé : le balayage ne l'a pas éprouvé, faute d'un critère
            // pour juger de la BONNE quantité d'éléments en revue. Le régler
            // demande d'abord de répondre à Q-27.
            'review_threshold' => 0.550,
            'weight_number' => 0.600,
            'weight_name' => 0.350,
            'weight_geography' => 0.050,
            'number_typo_name_threshold' => 0.850,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Un seul jeu actif à la fois : deux jeux actifs rendraient le moteur
        // dépendant de l'ordre des lignes.
        DB::table('matching_settings')
            ->where('algorithm_version', '!=', MatchingVersion::V2->value)
            ->update(['is_active' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('matching_settings')
            ->where('algorithm_version', MatchingVersion::V2->value)
            ->delete();

        DB::table('matching_settings')
            ->where('algorithm_version', MatchingVersion::V1->value)
            ->update(['is_active' => true, 'updated_at' => now()]);
    }
};
