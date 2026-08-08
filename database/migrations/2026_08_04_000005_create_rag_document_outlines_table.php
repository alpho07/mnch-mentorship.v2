<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rag_document_outlines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rag_document_id')->constrained('rag_documents')->cascadeOnDelete();
            $table->unsignedInteger('sort_order');
            $table->unsignedTinyInteger('level')->default(1);
            $table->string('type', 32)->default('heading');
            $table->string('title', 500);
            $table->string('locator_type', 32)->nullable();
            $table->string('locator', 64)->nullable();
            $table->text('content')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['rag_document_id', 'sort_order']);
            $table->index(['rag_document_id', 'type']);
            $table->index(['locator_type', 'locator']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rag_document_outlines');
    }
};
