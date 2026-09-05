<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Points de dépôt et de retrait — contenu de l'information N3 (D-009).
 *
 * ATTENTION : aucun partenaire réel n'existe à ce jour. Tant que cette table
 * est vide, N3 n'a pas de contenu défendable et le jalon 6 reste bloqué.
 * Voir DECISIONS.md C-03 et OPEN_QUESTIONS.md Q-14.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposit_points', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->enum('kind', ['police', 'city_hall', 'partner', 'other']);

            // Alimente le masquage géographique progressif : région en N1,
            // ville en N2, adresse complète en N3 (DISCLOSURE_LEVELS.md §2).
            $table->string('region');
            $table->string('city');
            $table->text('address');
            $table->string('opening_hours')->nullable();

            // Contact de l'ORGANISME, jamais d'une personne physique.
            $table->string('institutional_contact')->nullable();

            // Un point non vérifié n'entre jamais en N3.
            $table->boolean('is_verified')->default(false);
            $table->timestamps();

            $table->index(['region', 'city']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposit_points');
    }
};
