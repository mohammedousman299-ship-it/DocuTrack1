<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Déclarations de perte. Justification : docs/DATA_MODEL.md §2.4.
 *
 * Le numéro n'existe jamais en clair (D-007) : chiffré pour la restitution en
 * N3, et dupliqué en HMAC indexé pour la seule égalité exacte. Une fuite de
 * base ou de sauvegarde ne livre donc aucun numéro de pièce d'identité (M-08).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lost_declarations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_type_id')->constrained()->restrictOnDelete();

            $table->string('owner_name');
            $table->string('owner_name_normalized');

            // D-007 : jamais indexé, jamais exposé avant N3.
            // Texte : le cast chiffré de Laravel produit une chaîne base64.
            $table->text('number_encrypted')->nullable();
            // D-007 : égalité exacte sans exposer le numéro.
            // HMAC-SHA256 en HEXADÉCIMAL (64 caractères) plutôt qu'en bytea :
            // PDO n'accepte pas d'octets bruts sur une colonne bytea sans
            // liaison LOB, et l'hexadécimal s'indexe et se compare aussi bien.
            $table->string('number_hmac', 64)->nullable();
            // Confirmation N2 uniquement, en réponse à une saisie de l'utilisateur.
            $table->text('number_last4_encrypted')->nullable();

            // Cohérence temporelle : une découverte antérieure à la perte est
            // physiquement impossible et annule le score (MATCHING.md §3.3).
            $table->date('lost_on')->nullable();
            $table->string('lost_region')->nullable();
            $table->string('lost_city')->nullable();

            // Texte libre : jamais exposé avant N3, jamais indexé (M-11).
            $table->text('extra_info')->nullable();

            // M-02 : déclarer une perte au nom d'autrui est le vecteur le moins
            // coûteux du système. Une incohérence ne déclenche AUCUNE
            // notification automatique — la déclaration part en revue.
            $table->enum('name_consistency', ['match', 'mismatch', 'unknown'])
                ->default('unknown');

            $table->enum('status', ['active', 'matched', 'resolved', 'expired', 'withdrawn'])
                ->default('active');

            $table->timestamp('expires_at'); // rétention 180 jours (D-010)
            $table->timestamps();

            $table->index(['document_type_id', 'status']);
            $table->index('number_hmac');
            $table->index('expires_at'); // efficacité du cron de purge
        });

        // Index trigramme pour la présélection du moteur (MATCHING.md §4.1).
        // Doit produire un index scan : à vérifier par EXPLAIN sur 100 000 lignes.
        DB::statement(
            'CREATE INDEX lost_declarations_owner_name_trgm
             ON lost_declarations USING gin (owner_name_normalized gin_trgm_ops)'
        );

        DB::statement(
            'ALTER TABLE lost_declarations
             ADD CONSTRAINT lost_declarations_expiry_check
             CHECK (expires_at > created_at)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('lost_declarations');
    }
};
