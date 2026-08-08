<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RagMessage extends Model
{
    use HasFactory;

    public const ROLE_USER = 'user';
    public const ROLE_ASSISTANT = 'assistant';
    public const ROLE_SYSTEM = 'system';

    protected $fillable = [
        'rag_conversation_id',
        'role',
        'content',
        'citations',
        'retrieved_sources',
        'model',
        'latency_ms',
        'token_usage',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'citations' => 'array',
            'retrieved_sources' => 'array',
            'latency_ms' => 'integer',
            'token_usage' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(RagConversation::class, 'rag_conversation_id');
    }
}
