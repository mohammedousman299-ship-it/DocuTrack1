<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Journal d'audit APPEND-ONLY (§4.4).
 *
 * Ni mise à jour ni suppression, y compris par un administrateur. Cette
 * propriété n'est PAS une convention de code : elle est appliquée par des
 * déclencheurs PostgreSQL, qu'un contournement applicatif ne peut pas lever.
 *
 * Le rôle applicatif devrait en outre se voir révoquer UPDATE et DELETE sur
 * cette table — voir la note de fin de fichier.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignUuid('actor_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('actor_role', 20)->nullable();

            $table->string('action', 60);
            $table->string('entity_type', 60)->nullable();
            $table->string('entity_id')->nullable();
            $table->text('reason')->nullable();

            $table->string('ip', 45)->nullable();
            $table->string('session_fingerprint', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['actor_user_id', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
            $table->index('created_at'); // rétention 3 ans (D-010)
        });

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION audit_logs_append_only() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION
                    'audit_logs est append-only : % interdit (docs/THREAT_MODEL.md M-09)',
                    TG_OP;
            END;
            $$ LANGUAGE plpgsql
        SQL);

        DB::statement(
            'CREATE TRIGGER audit_logs_no_update
             BEFORE UPDATE ON audit_logs
             FOR EACH ROW EXECUTE FUNCTION audit_logs_append_only()'
        );

        DB::statement(
            'CREATE TRIGGER audit_logs_no_delete
             BEFORE DELETE ON audit_logs
             FOR EACH ROW EXECUTE FUNCTION audit_logs_append_only()'
        );

        // NOTE D'EXPLOITATION
        // Les déclencheurs ci-dessus sont la barrière portable. En production,
        // ils doivent être DOUBLÉS d'une révocation de droits sur le rôle
        // applicatif, qui ne peut pas être contournée même par un super-
        // utilisateur applicatif :
        //
        //   REVOKE UPDATE, DELETE ON audit_logs FROM <role_applicatif>;
        //
        // Cette révocation n'est pas exécutée ici : le rôle de production n'est
        // pas connu, et le rôle local est propriétaire de la base (il pourrait
        // donc se re-accorder les droits). À appliquer lors du déploiement —
        // voir docs/DEVELOPMENT.md §4, élément NON VALIDÉ.
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS audit_logs_no_delete ON audit_logs');
        DB::statement('DROP TRIGGER IF EXISTS audit_logs_no_update ON audit_logs');
        DB::statement('DROP FUNCTION IF EXISTS audit_logs_append_only()');
        Schema::dropIfExists('audit_logs');
    }
};
