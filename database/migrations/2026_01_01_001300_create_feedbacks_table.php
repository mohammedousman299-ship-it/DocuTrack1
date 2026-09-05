<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Retours d'expérience (§1.9), gérés par l'Administrateur (§1.10). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedbacks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('subject');
            $table->smallInteger('rating')->nullable();
            $table->text('message');

            $table->enum('status', ['pending', 'in_progress', 'resolved', 'dismissed'])
                ->default('pending');
            $table->text('admin_notes')->nullable();
            $table->foreignUuid('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        DB::statement(
            'ALTER TABLE feedbacks
             ADD CONSTRAINT feedbacks_rating_check
             CHECK (rating IS NULL OR rating BETWEEN 1 AND 5)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('feedbacks');
    }
};
