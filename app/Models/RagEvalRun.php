<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RagEvalRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'label',
        'settings',
        'cases_total',
        'cases_passed',
        'accuracy',
        'latency_p50_ms',
        'latency_p95_ms',
        'unsupported_rate',
        'abstain_rate',
        'failures',
        'promoted',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'cases_total' => 'integer',
            'cases_passed' => 'integer',
            'accuracy' => 'float',
            'latency_p50_ms' => 'integer',
            'latency_p95_ms' => 'integer',
            'unsupported_rate' => 'float',
            'abstain_rate' => 'float',
            'failures' => 'array',
            'promoted' => 'boolean',
        ];
    }
}
