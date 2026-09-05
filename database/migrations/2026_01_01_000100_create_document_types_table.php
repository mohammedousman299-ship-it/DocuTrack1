<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catégories de documents, gérées par l'Administrateur (§1.10).
 * Justification des colonnes : docs/DATA_MODEL.md §2.2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_types', function (Blueprint $table): void {
            $table->smallIncrements('id');
            $table->string('code', 40)->unique(); // stable, indépendant du libellé affiché
            $table->string('label_fr');
            $table->string('label_en');

            // 'high' pour passeport et CNI : impose la revue humaine obligatoire
            // avant restitution (M-01). Aucune automatisation sur ces types.
            $table->enum('sensitivity', ['standard', 'high'])->default('standard');

            // Validation permissive (D-012) : longueur et alphabet seulement.
            // Les formats réels des documents camerounais ne sont pas connus et
            // ne seront pas inventés (OPEN_QUESTIONS.md Q-07).
            $table->smallInteger('number_min_length')->nullable();
            $table->smallInteger('number_max_length')->nullable();
            $table->string('number_alphabet', 100)->nullable();

            $table->smallInteger('retention_days')->default(180); // D-010
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE document_types
            ADD CONSTRAINT document_types_number_length_check
            CHECK (
                number_min_length IS NULL
                OR number_max_length IS NULL
                OR number_min_length <= number_max_length
            )
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE document_types
            ADD CONSTRAINT document_types_retention_check
            CHECK (retention_days > 0)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('document_types');
    }
};
