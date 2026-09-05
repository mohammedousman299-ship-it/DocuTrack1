<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comptes utilisateurs.
 *
 * Un seul type de compte (D-004) : « Propriétaire » et « Trouveur » sont des
 * capacités contextuelles, pas des colonnes. Seul l'administrateur est un rôle.
 *
 * Chaque colonne est justifiée dans docs/DATA_MODEL.md §2.1. Les données
 * écartées volontairement y figurent aussi : ni date de naissance, ni adresse,
 * ni pièce d'identité du titulaire du compte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            // Identifiant non séquentiel : un entier auto-incrémenté révèle le
            // volume d'utilisateurs et permet l'énumération.
            $table->uuid('id')->primary();

            $table->string('full_name');
            // Nom normalisé : accents repliés, tokens triés. Sert au contrôle de
            // cohérence entre le nom du compte et le nom revendiqué (M-01, M-02).
            $table->string('full_name_normalized')->index();

            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();

            // Contrôle anti-Sybil principal (D-013). L'unicité est stricte :
            // un numéro, un compte. La limitation par IP est inopérante au
            // Cameroun à cause du CGNAT des opérateurs (M-04).
            $table->string('phone_e164', 20)->unique()->nullable();
            $table->timestamp('phone_verified_at')->nullable();

            $table->string('password');
            $table->text('two_factor_secret')->nullable();       // chiffré applicativement
            $table->text('two_factor_recovery_codes')->nullable(); // chiffré applicativement
            $table->timestamp('two_factor_confirmed_at')->nullable();

            // Séparation administration fonctionnelle / revue sensible (D-014).
            $table->enum('admin_role', ['none', 'functional', 'sensitive', 'both'])
                ->default('none');

            // Fiabilité du Trouveur : retient les signalements suspects en
            // revue avant qu'ils n'entrent dans l'index (M-06).
            $table->smallInteger('reporter_score')->default(50);

            $table->string('locale', 5)->default('fr');
            $table->jsonb('notification_prefs')->default('{}');

            // Blocage progressif après détection d'abus (M-07).
            $table->timestamp('blocked_until')->nullable();

            $table->rememberToken();
            $table->timestamps();

            $table->index('created_at'); // détection de créations en rafale (M-04)
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->uuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
