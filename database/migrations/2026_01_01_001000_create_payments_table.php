<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Paiements des frais de service. Justification : DATA_MODEL.md §2.10.
 *
 * Le paiement suit TOUJOURS une vérification d'identité réussie (D-016).
 * L'inverse transformerait chaque faux positif du moteur en litige et chaque
 * tentative d'usurpation en recette.
 *
 * Le module est désactivable par configuration (D-015) : la licéité du modèle
 * payant n'est pas tranchée (PAYMENT_DECISION.md §1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('claim_id')->constrained()->cascadeOnDelete();

            $table->string('provider', 40)->default('fake'); // aucun prestataire confirmé
            $table->string('provider_reference')->unique()->nullable();
            $table->string('idempotency_key')->unique(); // rejeu sans effet de bord

            // Unités mineures entières : aucun flottant sur de la monnaie.
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3)->default('XAF');

            // 'partial' et 'expired' sont fréquents en mobile money : ce sont
            // des états de première classe, pas des cas d'erreur.
            $table->enum('status', [
                'pending', 'succeeded', 'failed', 'expired', 'partial', 'refunded',
            ])->default('pending');

            // SEUL champ autorisant le passage en N3. Renseigné exclusivement
            // par confirmation serveur-à-serveur, JAMAIS par une redirection
            // navigateur (§6 du master prompt, garde-fou §12).
            $table->timestamp('confirmed_server_side_at')->nullable();

            $table->jsonb('webhook_payloads')->default('[]');
            $table->timestamp('reconciled_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('reconciled_at');
        });

        DB::statement(
            'ALTER TABLE payments
             ADD CONSTRAINT payments_amount_check
             CHECK (amount_minor > 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
