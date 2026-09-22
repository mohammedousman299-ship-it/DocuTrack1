<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('found_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('owner_name');
            $table->string('doc_number')->nullable();
            $table->string('location');
            $table->string('deposit_point');
            $table->text('context')->nullable();
            $table->string('image_path')->nullable();
            $table->string('status')->default('available'); // available | recovered
            $table->timestamps();
        });

        Schema::create('lost_declarations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('full_name');
            $table->string('doc_number')->nullable();
            $table->string('last_location');
            $table->text('description')->nullable();
            $table->string('status')->default('open'); // open | matched
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('found_document_id')->constrained()->cascadeOnDelete();
            $table->string('method');
            $table->string('account_number');
            $table->unsignedInteger('amount');
            $table->string('reference')->unique();
            $table->string('status')->default('paid'); // simulated gateway
            $table->timestamps();
            $table->unique(['user_id', 'found_document_id']);
        });

        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject');
            $table->unsignedTinyInteger('rating');
            $table->text('message');
            $table->string('status')->default('Pending'); // Pending | In Progress | Resolved
            $table->text('admin_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('found_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lost_declaration_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['alerts', 'feedback', 'payments', 'lost_declarations', 'found_documents', 'categories'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
