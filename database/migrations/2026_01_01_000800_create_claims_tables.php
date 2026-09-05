<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Revendications et tentatives de preuve. Justification : DATA_MODEL.md §2.8.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claims', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('match_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('claimant_user_id')->constrained('users')->cascadeOnDelete();

            $table->enum('status', [
                'open', 'proof_failed', 'proof_passed', 'admin_review',
                'approved', 'rejected', 'fulfilled', 'refunded',
            ])->default('open');

            // Incohérence => revue administrateur, jamais refus silencieux (M-01).
            $table->enum('name_consistency', ['match', 'mismatch', 'unknown'])
                ->default('unknown');

            // 3 tentatives À VIE, pas 3 par heure : un formulaire juste/faux est
            // un oracle, et le lieu de naissance se devine en quelques essais (M-03).
            $table->smallInteger('attempts_used')->default(0);

            $table->foreignUuid('admin_decision_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('admin_decision_at')->nullable();
            $table->text('admin_decision_reason')->nullable();

            $table->smallInteger('disclosed_level')->default(1);
            $table->timestamps();

            $table->unique(['match_id', 'claimant_user_id']);
            $table->index('status');
        });

        DB::statement(
            'ALTER TABLE claims
             ADD CONSTRAINT claims_attempts_check
             CHECK (attempts_used >= 0 AND attempts_used <= 3)'
        );

        DB::statement(
            'ALTER TABLE claims
             ADD CONSTRAINT claims_disclosed_level_check
             CHECK (disclosed_level BETWEEN 1 AND 3)'
        );

        // D-004 : un compte ne peut pas revendiquer un signalement dont il est
        // l'auteur, sinon il s'auto-attribue un document. Deux barrières —
        // celle-ci en base, et une Policy — parce qu'une seule serait une seule.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION claims_reject_self_claim() RETURNS trigger AS $$
            DECLARE
                finder uuid;
            BEGIN
                SELECT fr.finder_user_id INTO finder
                FROM matches m
                JOIN found_reports fr ON fr.id = m.found_report_id
                WHERE m.id = NEW.match_id;

                IF finder IS NOT NULL AND finder = NEW.claimant_user_id THEN
                    RAISE EXCEPTION
                        'un compte ne peut pas revendiquer son propre signalement (D-004)';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        SQL);

        DB::statement(
            'CREATE TRIGGER claims_reject_self_claim_trigger
             BEFORE INSERT OR UPDATE ON claims
             FOR EACH ROW EXECUTE FUNCTION claims_reject_self_claim()'
        );

        Schema::create('claim_attempts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('claim_id')->constrained()->cascadeOnDelete();

            // Les éléments de preuve sont HACHÉS, jamais stockés en clair : ce
            // sont des données dont la fuite détruirait le mécanisme de
            // vérification lui-même. Le hachage suffit à détecter un balayage.
            $table->string('submitted_fields_hash', 64); // sha256 hexadécimal

            // Résultat GLOBAL, jamais par champ : ne jamais indiquer quel champ
            // était faux, ni combien étaient corrects (M-03).
            $table->boolean('result');

            $table->string('ip', 45)->nullable();
            $table->string('session_fingerprint', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['claim_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_attempts');
        DB::statement('DROP TRIGGER IF EXISTS claims_reject_self_claim_trigger ON claims');
        DB::statement('DROP FUNCTION IF EXISTS claims_reject_self_claim()');
        Schema::dropIfExists('claims');
    }
};
