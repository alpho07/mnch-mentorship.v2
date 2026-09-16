<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rag_retrieval_traces')) {
            Schema::create('rag_retrieval_traces', function (Blueprint $table) {
                $table->id();
                $table->foreignId('rag_message_id')->nullable()->constrained('rag_messages')->nullOnDelete();
                $table->foreignId('rag_conversation_id')->nullable()->constrained('rag_conversations')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->text('question');
                $table->string('question_hash', 64)->index();
                $table->string('decision', 16)->default('answer');
                $table->decimal('gate_score', 5, 4)->nullable();
                $table->json('gate_signals')->nullable();
                $table->string('gate_mode', 8)->default('shadow');
                $table->string('shadow_decision', 16)->nullable();
                $table->json('stages')->nullable();
                $table->string('final_stage', 32)->nullable();
                $table->unsignedSmallInteger('search_count')->default(0);
                $table->json('primary_queries')->nullable();
                $table->json('expanded_queries')->nullable();
                $table->json('lexicon_edges_used')->nullable();
                $table->unsignedSmallInteger('source_count')->default(0);
                $table->json('selected_documents')->nullable();
                $table->json('selected_locators')->nullable();
                $table->string('answer_route', 16)->nullable();
                $table->string('answer_model')->nullable();
                $table->boolean('cache_hit')->default(false);
                $table->string('cache_kind', 16)->nullable();
                $table->decimal('cache_similarity', 5, 4)->nullable();
                $table->decimal('grounding_min_support', 5, 4)->nullable();
                $table->unsignedSmallInteger('sentence_count')->default(0);
                $table->unsignedSmallInteger('unsupported_count')->default(0);
                $table->unsignedSmallInteger('numeric_violation_count')->default(0);
                $table->json('unsupported_sentences')->nullable();
                $table->unsignedInteger('retrieval_ms')->default(0);
                $table->unsignedInteger('answer_ms')->default(0);
                $table->unsignedInteger('total_ms')->default(0);
                $table->unsignedInteger('budget_ms')->default(0);
                $table->boolean('budget_exhausted')->default(false);
                $table->unsignedBigInteger('corpus_version')->default(0);
                $table->string('settings_version', 32)->nullable();
                $table->string('fallback_reason')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamps();
                $table->index(['decision', 'created_at']);
                $table->index(['answer_route', 'created_at']);
                $table->index(['corpus_version', 'created_at']);
            });
        }

        if (! Schema::hasTable('rag_lexicon_terms')) {
            Schema::create('rag_lexicon_terms', function (Blueprint $table) {
                $table->id();
                $table->string('term', 128);
                $table->string('normalised', 128)->index();
                $table->unsignedInteger('document_frequency')->default(0);
                $table->unsignedInteger('chunk_frequency')->default(0);
                $table->decimal('df_ratio', 6, 5)->default(0);
                $table->boolean('is_stopword')->default(false);
                $table->boolean('is_acronym')->default(false);
                $table->json('trigrams')->nullable();
                $table->unsignedBigInteger('corpus_version')->default(0);
                $table->timestamps();
                $table->unique(['normalised', 'corpus_version']);
                $table->index(['is_stopword', 'df_ratio']);
            });
        }

        if (! Schema::hasTable('rag_lexicon_edges')) {
            Schema::create('rag_lexicon_edges', function (Blueprint $table) {
                $table->id();
                $table->string('from_term', 128)->index();
                $table->string('to_term', 256);
                $table->string('kind', 32);
                $table->string('source', 16);
                $table->decimal('weight', 8, 5)->default(0);
                $table->unsignedSmallInteger('priority')->default(100);
                $table->boolean('enabled')->default(true);
                $table->unsignedInteger('hits')->default(0);
                $table->unsignedInteger('successes')->default(0);
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('corpus_version')->default(0);
                $table->timestamps();
                $table->index(['from_term', 'enabled', 'priority']);
                $table->index(['kind', 'source']);
            });
        }

        if (! Schema::hasTable('rag_answer_cache')) {
            Schema::create('rag_answer_cache', function (Blueprint $table) {
                $table->id();
                $table->string('question_hash', 64);
                $table->text('question');
                $table->text('question_normalised');
                $table->longText('embedding')->nullable();
                $table->unsignedSmallInteger('embedding_dim')->default(0);
                $table->longText('answer');
                $table->json('citations')->nullable();
                $table->json('retrieved_sources')->nullable();
                $table->string('answer_model')->nullable();
                $table->string('answer_route', 16)->nullable();
                $table->decimal('gate_score', 5, 4)->nullable();
                $table->unsignedBigInteger('corpus_version');
                $table->unsignedInteger('hits')->default(0);
                $table->timestamp('last_hit_at')->nullable();
                $table->timestamps();
                $table->unique(['question_hash', 'corpus_version']);
                $table->index(['corpus_version', 'last_hit_at']);
            });
        }

        if (! Schema::hasTable('rag_settings')) {
            Schema::create('rag_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->json('value');
                $table->string('version', 32);
                $table->string('source', 16);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('rag_eval_cases')) {
            Schema::create('rag_eval_cases', function (Blueprint $table) {
                $table->id();
                $table->text('question');
                $table->string('question_hash', 64)->unique();
                $table->string('origin', 16);
                $table->boolean('frozen')->default(false);
                $table->boolean('enabled')->default(true);
                $table->json('expected_documents')->nullable();
                $table->json('expected_locators')->nullable();
                $table->json('must_include')->nullable();
                $table->json('must_not_include')->nullable();
                $table->string('expected_decision', 16)->nullable();
                $table->string('expected_route', 16)->nullable();
                $table->boolean('forbid_title_only')->default(true);
                $table->boolean('require_citations')->default(true);
                $table->unsignedInteger('max_latency_ms')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('rag_eval_runs')) {
            Schema::create('rag_eval_runs', function (Blueprint $table) {
                $table->id();
                $table->string('label');
                $table->json('settings');
                $table->unsignedSmallInteger('cases_total')->default(0);
                $table->unsignedSmallInteger('cases_passed')->default(0);
                $table->decimal('accuracy', 5, 4)->default(0);
                $table->unsignedInteger('latency_p50_ms')->default(0);
                $table->unsignedInteger('latency_p95_ms')->default(0);
                $table->decimal('unsupported_rate', 5, 4)->default(0);
                $table->decimal('abstain_rate', 5, 4)->default(0);
                $table->json('failures')->nullable();
                $table->boolean('promoted')->default(false);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rag_eval_runs');
        Schema::dropIfExists('rag_eval_cases');
        Schema::dropIfExists('rag_settings');
        Schema::dropIfExists('rag_answer_cache');
        Schema::dropIfExists('rag_lexicon_edges');
        Schema::dropIfExists('rag_lexicon_terms');
        Schema::dropIfExists('rag_retrieval_traces');
    }
};
