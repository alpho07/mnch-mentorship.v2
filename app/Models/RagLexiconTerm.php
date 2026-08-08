<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RagLexiconTerm extends Model
{
    use HasFactory;

    protected $fillable = [
        'term',
        'normalised',
        'document_frequency',
        'chunk_frequency',
        'df_ratio',
        'is_stopword',
        'is_acronym',
        'trigrams',
        'corpus_version',
    ];

    protected function casts(): array
    {
        return [
            'document_frequency' => 'integer',
            'chunk_frequency' => 'integer',
            'df_ratio' => 'float',
            'is_stopword' => 'boolean',
            'is_acronym' => 'boolean',
            'trigrams' => 'array',
            'corpus_version' => 'integer',
        ];
    }
}
