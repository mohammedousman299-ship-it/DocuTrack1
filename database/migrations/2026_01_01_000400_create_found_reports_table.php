<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Signalements de découverte. Justification : docs/DATA_MODEL.md §2.5.
 *
 * Cette table ne porte VOLONTAIREMENT aucun compteur de correspondances ni
 * statut de rapprochement visible du Trouveur. Un tel retour transformerait
 * l'envoi de faux signalements en canal d'extraction, contournant tous les
 * quotas de recherche (M-06).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('found_reports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('finder_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('document_type_id')->constrained()->restrictOnDelete();

            // Le Trouveur ne connaît pas toujours le nom porté sur le document.
            $table->string('owner_name')->nullable();
            $table->string('owner_name_normalized')->nullable();

            $table->binary('number_encrypted')->nullable();
            $table->binary('number_hmac')->nullable();
            $table->text('number_last4_encrypted')->nullable();

            // Exposée tronquée au mois en N1, exacte en N2 (DISCLOSURE_LEVELS.md).
            $table->date('found_on');
            $table->string('found_region');
            $table->string('found_city');

            // Contenu de N3 (D-009).
            $table->foreignUuid('deposit_point_id')->nullable()
                ->constrained('deposit_points')->nullOnDelete();
            $table->text('deposit_reference_encrypted')->nullable();

            // Saisie libre tant qu'aucun partenaire n'existe (C-03).
            // REVUE ADMINISTRATEUR OBLIGATOIRE avant exposition en N3 : le
            // Trouveur peut y recopier le numéro du document (M-11).
            $table->text('deposit_free_text')->nullable();

            $table->text('extra_info')->nullable(); // jamais avant N3 (M-11)

            // Empreinte normalisée, pas égalité stricte (§4.5). Une collision
            // ne rejette pas : elle place le signalement en revue.
            $table->binary('duplicate_fingerprint');

            $table->enum('status', [
                'pending_review', 'active', 'matched', 'returned', 'rejected', 'expired',
            ])->default('pending_review');

            $table->timestamp('expires_at'); // rétention 180 jours (D-010)
            $table->timestamps();

            $table->index(['document_type_id', 'status']);
            $table->index('number_hmac');
            $table->index('duplicate_fingerprint');
            $table->index('expires_at');
        });

        DB::statement(
            'CREATE INDEX found_reports_owner_name_trgm
             ON found_reports USING gin (owner_name_normalized gin_trgm_ops)'
        );

        DB::statement(
            'ALTER TABLE found_reports
             ADD CONSTRAINT found_reports_expiry_check
             CHECK (expires_at > created_at)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('found_reports');
    }
};
