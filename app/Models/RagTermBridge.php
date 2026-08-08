<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class RagTermBridge extends Model
{
    use HasFactory;

    public const CACHE_KEY = 'rag:term-bridges:v1';

    protected $fillable = [
        'trigger',
        'synonyms',
        'queries',
        'category',
        'priority',
        'enabled',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'synonyms' => 'array',
            'queries' => 'array',
            'priority' => 'integer',
            'enabled' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn (): bool => Cache::forget(self::CACHE_KEY));
        static::deleted(fn (): bool => Cache::forget(self::CACHE_KEY));
    }
}
