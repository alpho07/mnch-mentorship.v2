<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RagRetrievalTrace extends Model
{
    use HasFactory;

    protected $fillable = [
        'rag_message_id',
        'rag_conversation_id',
        'user_id',
        'question',
        'question_hash',
        'decision',
        'gate_score',
        'gate_signals',
        'gate_mode',
        'shadow_decision',
        'stages',
        'final_stage',
        'search_count',
        'primary_queries',
        'expanded_queries',
        'lexicon_edges_used',
        'source_count',
        'selected_documents',
        'selected_locators',
        'answer_route',
        'answer_model',
        'cache_hit',
        'cache_kind',
        'cache_similarity',
        'grounding_min_support',
        'sentence_count',
        'unsupported_count',
        'numeric_violation_count',
        'unsupported_sentences',
        'retrieval_ms',
        'answer_ms',
        'total_ms',
        'budget_ms',
        'budget_exhausted',
        'corpus_version',
        'settings_version',
        'fallback_reason',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'gate_score' => 'float',
            'gate_signals' => 'array',
            'stages' => 'array',
            'primary_queries' => 'array',
            'expanded_queries' => 'array',
            'lexicon_edges_used' => 'array',
            'selected_documents' => 'array',
            'selected_locators' => 'array',
            'cache_hit' => 'boolean',
            'cache_similarity' => 'float',
            'grounding_min_support' => 'float',
            'unsupported_sentences' => 'array',
            'budget_exhausted' => 'boolean',
            'corpus_version' => 'integer',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(RagMessage::class, 'rag_message_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(RagConversation::class, 'rag_conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
