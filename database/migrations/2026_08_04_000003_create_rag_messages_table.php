<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rag_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rag_conversation_id')->constrained('rag_conversations')->cascadeOnDelete();
            $table->string('role', 32);
            $table->longText('content');
            $table->json('citations')->nullable();
            $table->json('retrieved_sources')->nullable();
            $table->string('model')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->json('token_usage')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['rag_conversation_id', 'created_at']);
            $table->index(['role', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rag_messages');
    }
};
