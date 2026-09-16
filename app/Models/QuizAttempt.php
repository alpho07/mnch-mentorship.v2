<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'program_module_quiz_id',
        'user_id',
        'attempt_type',
        'score',
        'total_questions',
        'correct_answers',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'score' => 'decimal:2',
        'total_questions' => 'integer',
        'correct_answers' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(ProgramModuleQuiz::class, 'program_module_quiz_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(QuizResponse::class, 'quiz_attempt_id');
    }

    public function isPassed(): bool
    {
        if ($this->score === null || $this->quiz === null) {
            return false;
        }

        return $this->score >= $this->quiz->pass_mark_percentage;
    }

    public function averageScoreWith(QuizAttempt $otherAttempt): float
    {
        return round(($this->score + $otherAttempt->score) / 2, 2);
    }
}
