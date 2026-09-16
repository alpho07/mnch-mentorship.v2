<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramModuleQuiz extends Model
{
    use HasFactory;

    protected $fillable = [
        'program_module_id',
        'type',
        'title',
        'description',
        'pass_mark_percentage',
        'time_limit_minutes',
        'order_sequence',
        'is_active',
    ];

    protected $casts = [
        'pass_mark_percentage' => 'decimal:2',
        'time_limit_minutes' => 'integer',
        'order_sequence' => 'integer',
        'is_active' => 'boolean',
    ];

    public function hasTimeLimit(): bool
    {
        return $this->time_limit_minutes !== null;
    }

    public function programModule(): BelongsTo
    {
        return $this->belongsTo(ProgramModule::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class, 'program_module_quiz_id')
            ->orderBy('order_sequence');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class, 'program_module_quiz_id');
    }

    public function isPreTest(): bool
    {
        return in_array($this->type, ['pre_test', 'both'], true);
    }

    public function isPostTest(): bool
    {
        return in_array($this->type, ['post_test', 'both'], true);
    }
}
