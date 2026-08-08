<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rag_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rag_document_id')->constrained('rag_documents')->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->string('locator_type', 32)->nullable();
            $table->string('locator', 64)->nullable();
            $table->longText('content');
            $table->string('content_sha256', 64)->index();
            $table->json('embedding')->nullable();
            $table->string('embedding_model')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['rag_document_id', 'chunk_index']);
            $table->index(['rag_document_id', 'created_at']);
            $table->index(['embedding_model', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rag_chunks');
    }
};
