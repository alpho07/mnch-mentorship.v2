<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RagChunk extends Model
{
    use HasFactory;

    protected $fillable = [
        'rag_document_id',
        'chunk_index',
        'locator_type',
        'locator',
        'content',
        'content_sha256',
        'embedding',
        'embedding_model',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'chunk_index' => 'integer',
            'embedding' => 'array',
            'metadata' => 'array',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(RagDocument::class, 'rag_document_id');
    }
}
