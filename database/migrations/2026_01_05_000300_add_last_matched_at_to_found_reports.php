<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marque les signalements déjà confrontés aux déclarations existantes.
 *
 * Le rapprochement se fait dans DEUX sens, et il en manquait un :
 *
 *   - une déclaration nouvelle est confrontée aux signalements existants —
 *     c'est le traitement de la demande de recherche (D-008), déjà en place ;
 *   - un signalement nouveau doit être confronté aux déclarations existantes,
 *     ce que rien ne faisait.
 *
 * Sans le second sens, la promesse faite à l'écran — « votre déclaration reste
 * active, vous serez prévenu si quelqu'un le signale plus tard » — n'était
 * jamais tenue. C'est pourtant tout l'intérêt de D-035.
 *
 * Chaque couple est ainsi examiné UNE fois : un signalement balayé à l'instant
 * T couvre les déclarations existantes à T, et une déclaration créée après T
 * couvre ce signalement par son propre chemin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('found_reports', function (Blueprint $table): void {
            $table->timestamp('last_matched_at')->nullable()->after('expires_at');

            // Index partiel : la requête du cron ne cherche que les lignes
            // NON balayées, qui sont une petite minorité en régime établi.
            $table->index(['last_matched_at'], 'found_reports_unmatched_index');
        });
    }

    public function down(): void
    {
        Schema::table('found_reports', function (Blueprint $table): void {
            $table->dropIndex('found_reports_unmatched_index');
            $table->dropColumn('last_matched_at');
        });
    }
};
