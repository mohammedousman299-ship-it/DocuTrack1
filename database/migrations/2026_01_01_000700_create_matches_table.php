<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Correspondances possibles. Justification : docs/DATA_MODEL.md §2.7.
 *
 * Une correspondance n'est JAMAIS une certitude. Le score et sa décomposition
 * ne sont exposés à aucun utilisateur : ce serait un oracle de matching,
 * renseignant un attaquant sur la qualité de son approximation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('lost_declaration_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('found_report_id')->constrained()->cascadeOnDelete();

            // Sans ces trois colonnes, impossible de régler un seuil ou
            // d'expliquer un faux positif après coup (§5).
            $table->string('algorithm_version', 20);
            $table->decimal('score', 4, 3);
            $table->jsonb('score_breakdown');

            // Numéros différents mais nom très proche : le score reste bas,
            // c'est ce drapeau qui déclenche la revue (MATCHING.md §3.5).
            $table->boolean('possible_number_typo')->default(false);

            $table->enum('status', [
                'candidate', 'notified', 'under_review', 'confirmed', 'rejected', 'expired',
            ])->default('candidate');

            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            // Idempotence : un même couple ne produit qu'une correspondance et
            // qu'une notification, quel que soit le nombre de passages du cron.
            $table->unique(
                ['lost_declaration_id', 'found_report_id', 'algorithm_version'],
                'matches_pair_unique'
            );

            $table->index(['status', 'score']);
        });

        DB::statement(
            'ALTER TABLE matches
             ADD CONSTRAINT matches_score_range_check
             CHECK (score >= 0 AND score <= 1)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
