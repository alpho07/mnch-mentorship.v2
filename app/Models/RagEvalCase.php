<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RagEvalCase extends Model
{
    use HasFactory;

    protected $fillable = [
        'question',
        'question_hash',
        'origin',
        'frozen',
        'enabled',
        'expected_documents',
        'expected_locators',
        'must_include',
        'must_not_include',
        'expected_decision',
        'expected_route',
        'forbid_title_only',
        'require_citations',
        'max_latency_ms',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'frozen' => 'boolean',
            'enabled' => 'boolean',
            'expected_documents' => 'array',
            'expected_locators' => 'array',
            'must_include' => 'array',
            'must_not_include' => 'array',
            'forbid_title_only' => 'boolean',
            'require_citations' => 'boolean',
            'max_latency_ms' => 'integer',
        ];
    }
}
