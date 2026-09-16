<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class RagLexiconEdge extends Model
{
    use HasFactory;

    public const CACHE_KEY = 'rag:lexicon:v1';

    protected $fillable = [
        'from_term',
        'to_term',
        'kind',
        'source',
        'weight',
        'priority',
        'enabled',
        'hits',
        'successes',
        'notes',
        'corpus_version',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'float',
            'priority' => 'integer',
            'enabled' => 'boolean',
            'hits' => 'integer',
            'successes' => 'integer',
            'corpus_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn (): bool => Cache::forget(self::CACHE_KEY));
        static::deleted(fn (): bool => Cache::forget(self::CACHE_KEY));
    }
}
