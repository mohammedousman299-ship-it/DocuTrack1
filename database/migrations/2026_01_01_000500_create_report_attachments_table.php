<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Références des images de documents. JAMAIS le binaire en base, jamais sur le
 * système de fichiers du conteneur (§3.3). Justification : DATA_MODEL.md §2.6.
 *
 * L'image est la donnée la plus sensible du système : elle est fournie par un
 * tiers sur une personne qui n'a pas consenti. Elle n'est visible d'aucun
 * utilisateur, à aucun niveau de divulgation, y compris N3 (D-006).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_attachments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('found_report_id')->constrained()->cascadeOnDelete();

            // Clé dans le stockage objet privé. Non devinable (M-12).
            $table->string('object_key')->unique();
            $table->string('content_hash', 64); // sha256 hexadécimal

            $table->string('mime_type', 100);
            $table->unsignedInteger('byte_size');
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();

            // Preuve que la suppression EXIF serveur a eu lieu. Une photo de
            // pièce d'identité embarque des coordonnées GPS. Un enregistrement
            // dont cette date est nulle ne doit JAMAIS être servi.
            $table->timestamp('exif_stripped_at')->nullable();

            $table->timestamp('expires_at'); // 90 jours : la plus courte du modèle (D-010)
            $table->timestamps();

            $table->index('expires_at');
        });

        DB::statement(
            'ALTER TABLE report_attachments
             ADD CONSTRAINT report_attachments_size_check
             CHECK (byte_size > 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('report_attachments');
    }
};
