<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Paramètres du moteur de rapprochement.
 *
 * Les seuils vivent en base et sont modifiables SANS REDÉPLOIEMENT (§5). Les
 * valeurs initiales sont argumentées mais non validées : le protocole de
 * mesure du jalon 5 (MATCHING.md §6) doit les confirmer ou les corriger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matching_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('algorithm_version', 20)->unique();

            $table->decimal('notify_threshold', 4, 3)->default(0.750);
            $table->decimal('review_threshold', 4, 3)->default(0.550);

            // Pondérations par composante (MATCHING.md §3.1).
            $table->decimal('weight_number', 4, 3)->default(0.600);
            $table->decimal('weight_name', 4, 3)->default(0.350);
            $table->decimal('weight_geography', 4, 3)->default(0.050);

            // Déclenche l'indicateur possible_number_typo : numéros différents
            // mais nom très proche. Abaissé de 0,90 à 0,85 après mesure (D-018).
            $table->decimal('number_typo_name_threshold', 4, 3)->default(0.850);

            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        DB::statement(
            'ALTER TABLE matching_settings
             ADD CONSTRAINT matching_settings_threshold_order_check
             CHECK (review_threshold <= notify_threshold)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('matching_settings');
    }
};
