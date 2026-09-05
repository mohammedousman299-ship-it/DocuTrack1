<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trace des accès aux données : qui a vu quoi, à quel niveau, quand (§4.4).
 *
 * Alimentée à CHAQUE construction d'une vue de divulgation N1, N2 ou N3, y
 * compris pour un administrateur — dont les accès sont journalisés au même
 * titre que ceux des autres acteurs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disclosures', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('actor_user_id')->constrained('users')->cascadeOnDelete();

            $table->string('subject_type', 60);
            $table->uuid('subject_id');
            $table->smallInteger('level'); // 1, 2 ou 3

            $table->enum('context', ['search', 'claim', 'notification', 'admin_review']);

            // Obligatoire pour un accès administrateur sensible (D-014) :
            // c'est le contrôle préventif qui manquait à la journalisation seule.
            $table->text('reason')->nullable();

            // Chaque génération d'URL signée vers une image est tracée
            // individuellement (M-12).
            $table->boolean('signed_url_generated')->default(false);

            $table->string('ip', 45)->nullable();
            $table->string('session_fingerprint', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['actor_user_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disclosures');
    }
};
