<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Envois de notifications. Justification : DATA_MODEL.md §2.11.
 *
 * AUCUNE colonne ne porte de contenu de niveau N2 ou N3. Le corps du message
 * est reconstruit à l'envoi depuis le gabarit et ne contient que l'annonce
 * d'une correspondance possible et une invitation à se connecter : un SMS
 * s'affiche sur un écran verrouillé, dans un lieu public (M-10).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();

            $table->enum('channel', ['email', 'sms']);
            $table->string('template', 60);

            // Clé dérivée de l'évènement métier, JAMAIS du passage de cron :
            // même rejoué, le destinataire n'est notifié qu'une fois.
            $table->string('idempotency_key')->unique();

            $table->enum('status', ['queued', 'sent', 'failed', 'suppressed'])
                ->default('queued');

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->smallInteger('retry_count')->default(0);
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            // Regroupement et plafond par période, pour éviter le harcèlement
            // et la fuite par accumulation (§7).
            $table->index(['user_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
