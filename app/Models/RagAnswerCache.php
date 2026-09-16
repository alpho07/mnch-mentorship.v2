<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RagAnswerCache extends Model
{
    use HasFactory;

    protected $table = 'rag_answer_cache';

    protected $fillable = [
        'question_hash',
        'question',
        'question_normalised',
        'embedding',
        'embedding_dim',
        'answer',
        'citations',
        'retrieved_sources',
        'answer_model',
        'answer_route',
        'gate_score',
        'corpus_version',
        'hits',
        'last_hit_at',
    ];

    protected function casts(): array
    {
        return [
            'citations' => 'array',
            'retrieved_sources' => 'array',
            'embedding_dim' => 'integer',
            'gate_score' => 'float',
            'corpus_version' => 'integer',
            'hits' => 'integer',
            'last_hit_at' => 'datetime',
        ];
    }
}
