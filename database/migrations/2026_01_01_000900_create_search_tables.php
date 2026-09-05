<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recherche asynchrone différée (D-008). Justification : DATA_MODEL.md §2.9.
 *
 * La requête HTTP ne porte pas le résultat : ni la latence, ni le corps de la
 * réponse ne révèlent donc si une correspondance existe (M-05). C'est aussi ce
 * qui détruit la boucle d'énumération rapide (M-07).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_type_id')->constrained()->restrictOnDelete();

            $table->string('owner_name_normalized')->nullable();
            $table->binary('number_hmac')->nullable();
            $table->string('region')->nullable();
            $table->date('approximate_lost_on')->nullable();

            // C1 = type + numéro ; C2 = type + nom + un troisième critère.
            // Une recherche par nom seul est refusée (DISCLOSURE_LEVELS.md §3).
            $table->enum('criteria_combination', ['C1', 'C2']);

            $table->enum('status', ['queued', 'processed', 'failed'])->default('queued');

            // Ordre de grandeur conservé pour la détection d'abus, SANS que le
            // nombre exact ne soit jamais exposé à l'utilisateur (M-07).
            $table->enum('result_count_bucket', ['none', 'one', 'several'])->nullable();

            $table->string('ip', 45)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            // Journalisation exigée par §4.2 : auteur, critères, nombre de
            // résultats. Sert aussi aux quotas quotidiens par compte.
            $table->index(['user_id', 'created_at']);
            $table->index('status');
        });

        Schema::create('search_results', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('search_request_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('found_report_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 4, 3);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['search_request_id', 'found_report_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_results');
        Schema::dropIfExists('search_requests');
    }
};
