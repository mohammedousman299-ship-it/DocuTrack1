<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Codes de vérification du numéro de téléphone.
 *
 * La vérification SMS est le CONTRÔLE ANTI-SYBIL PRINCIPAL (D-013) : sans coût
 * d'entrée réel, les quotas par compte seraient décoratifs, et la limitation
 * par IP est inopérante au Cameroun à cause du CGNAT des opérateurs (M-04).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_verification_codes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();

            // Le numéro visé est figé à l'émission : si l'utilisateur change
            // de numéro entre-temps, le code devient inutilisable plutôt que
            // de valider le mauvais numéro.
            $table->string('phone_e164', 20);

            // Le code est HACHÉ, jamais stocké en clair : une fuite de base ne
            // doit pas permettre de valider des numéros à la place des
            // utilisateurs.
            $table->string('code_hash', 64);

            // Tentatives bornées : un code à 6 chiffres se devine en 10^6
            // essais, ce qui est atteignable sans cette borne.
            $table->smallInteger('attempts')->default(0);

            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'consumed_at']);
            $table->index('expires_at'); // purge
        });

        DB::statement(
            'ALTER TABLE phone_verification_codes
             ADD CONSTRAINT phone_verification_codes_attempts_check
             CHECK (attempts >= 0 AND attempts <= 5)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_verification_codes');
    }
};
